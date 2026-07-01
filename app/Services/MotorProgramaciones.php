<?php

namespace App\Services;

use App\Models\Actuacion;
use App\Models\Actuador;
use App\Models\ComandoIot;
use App\Models\ConfigSistema;
use App\Models\EjecucionProgramacion;
use App\Models\Modulo;
use App\Models\Programacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MotorProgramaciones
{
    private const VENTANA_EJECUCION_SEG = 90;

    /**
     * Evalúa programaciones de todos los módulos habilitados.
     *
     * Se puede llamar desde el scheduler de Laravel, desde el botón del panel o
     * desde el endpoint /sync que consulta el ESP32.
     */
    public function evaluarTodos(?Carbon $momento = null): array
    {
        $momento = $momento ?: now();
        $modulos = Modulo::query()
            ->where('habilitado', true)
            ->orderBy('id')
            ->get(['id', 'codigo']);

        $resumen = [
            'ok' => true,
            'modo' => $this->config('modo_global', 'manual'),
            'modulos' => $modulos->count(),
            'programaciones_evaluadas' => 0,
            'inicios_creados' => 0,
            'finalizaciones_creadas' => 0,
            'omitidas' => 0,
            'errores' => [],
        ];

        foreach ($modulos as $modulo) {
            try {
                $resultado = $this->evaluarModulo((int) $modulo->id, $momento);
                $resumen['programaciones_evaluadas'] += (int) ($resultado['programaciones_evaluadas'] ?? 0);
                $resumen['inicios_creados'] += (int) ($resultado['inicios_creados'] ?? 0);
                $resumen['finalizaciones_creadas'] += (int) ($resultado['finalizaciones_creadas'] ?? 0);
                $resumen['omitidas'] += count($resultado['omitidas'] ?? []);
            } catch (Throwable $e) {
                $resumen['ok'] = false;
                $resumen['errores'][] = [
                    'modulo_id' => $modulo->id,
                    'codigo' => $modulo->codigo,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resumen;
    }

    /**
     * Evalúa programaciones de un módulo.
     *
     * Seguridad: las finalizaciones pendientes se ejecutan aunque el modo global
     * ya no sea automático, para no dejar electroválvulas, motores o relés
     * encendidos por un cambio de modo realizado a mitad de ciclo.
     */
    public function evaluarModulo(int $moduloId, ?Carbon $momento = null): array
    {
        $momento = $momento ?: now();
        $modo = $this->config('modo_global', 'manual');

        $resultado = [
            'ok' => true,
            'modo' => $modo,
            'modulo_id' => $moduloId,
            'programaciones_evaluadas' => 0,
            'inicios_creados' => 0,
            'finalizaciones_creadas' => 0,
            'acciones' => [],
            'omitidas' => [],
        ];

        $resultadoFinalizaciones = $this->finalizarEjecucionesVencidas($moduloId, $momento);
        $resultado['finalizaciones_creadas'] = (int) ($resultadoFinalizaciones['finalizaciones_creadas'] ?? 0);
        $resultado['acciones'] = array_merge($resultado['acciones'], $resultadoFinalizaciones['acciones'] ?? []);
        $resultado['omitidas'] = array_merge($resultado['omitidas'], $resultadoFinalizaciones['omitidas'] ?? []);

        if ($modo !== 'automatico') {
            $resultado['omitido'] = true;
            $resultado['motivo'] = 'El sistema no está en modo automático. No se inician nuevas programaciones.';
            return $resultado;
        }

        $programaciones = Programacion::query()
            ->with(['modulo', 'actuador'])
            ->where('modulo_id', $moduloId)
            ->where('activo', true)
            ->orderBy('prioridad')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();

        foreach ($programaciones as $programacion) {
            $resultado['programaciones_evaluadas']++;
            $evaluacion = $this->evaluarInicio($programacion, $momento);

            if (!empty($evaluacion['omitida'])) {
                $resultado['omitidas'][] = $evaluacion;
                continue;
            }

            if (!empty($evaluacion['inicio_creado'])) {
                $resultado['inicios_creados']++;
            }

            $resultado['acciones'][] = $evaluacion;
        }

        return $resultado;
    }

    private function evaluarInicio(Programacion $programacion, Carbon $momento): array
    {
        $modulo = $programacion->modulo;
        $actuador = $programacion->actuador;

        if (!$modulo || !$actuador) {
            return $this->omitida($programacion, 'modulo_o_actuador_no_encontrado');
        }

        if ((int) $actuador->modulo_id !== (int) $programacion->modulo_id) {
            return $this->omitida($programacion, 'actuador_fuera_del_modulo');
        }

        if (!$modulo->habilitado || !$actuador->activo) {
            return $this->omitida($programacion, 'modulo_o_actuador_inactivo');
        }

        $zonaHoraria = $this->zonaHoraria($modulo);
        $ahoraLocal = $momento->copy()->timezone($zonaHoraria);
        $dias = $this->normalizarDias($programacion->dias ?? []);
        $codigoHoy = $this->codigoDia($ahoraLocal);

        if (empty($dias)) {
            return $this->omitida($programacion, 'programacion_sin_dias_configurados');
        }

        if (!in_array($codigoHoy, $dias, true)) {
            return $this->omitida($programacion, 'no_corresponde_al_dia_actual');
        }

        $inicioLocal = $this->inicioLocal($programacion, $ahoraLocal, $zonaHoraria);
        $segundosDesdeInicio = $inicioLocal->diffInSeconds($ahoraLocal, false);

        if ($segundosDesdeInicio < 0) {
            return $this->omitida($programacion, 'todavia_no_llega_la_hora');
        }

        if ($segundosDesdeInicio > self::VENTANA_EJECUCION_SEG) {
            return $this->omitida($programacion, 'fuera_de_ventana_de_ejecucion');
        }

        $inicioProgramado = $inicioLocal->toDateTimeString();

        $yaEjecutada = EjecucionProgramacion::query()
            ->where('programacion_id', $programacion->id)
            ->where('inicio_en', $inicioProgramado)
            ->exists();

        if ($yaEjecutada) {
            return $this->omitida($programacion, 'programacion_ya_ejecutada_en_esta_hora');
        }

        return DB::transaction(function () use ($programacion, $actuador, $inicioProgramado, $ahoraLocal) {
            $estadoAnterior = $actuador->estado_deseado;
            $estadoNuevo = $this->estadoDeseado($programacion);
            $requiereFinalizacion = $this->requiereFinalizacion($estadoNuevo, (int) $programacion->duracion_seg);

            $ejecucion = EjecucionProgramacion::create([
                'programacion_id' => $programacion->id,
                'inicio_en' => $inicioProgramado,
                'fin_en' => $requiereFinalizacion ? null : $ahoraLocal->toDateTimeString(),
                'estado' => 'ok',
                'nota' => $requiereFinalizacion
                    ? 'Inicio programado: comando de activación creado. Pendiente de apagado automático.'
                    : 'Acción puntual programada: comando creado.',
            ]);

            $comandoId = $this->crearComando(
                $programacion,
                $actuador,
                $estadoNuevo,
                'programacion_inicio',
                [
                    'ejecucion_id' => $ejecucion->id,
                    'inicio_programado' => $inicioProgramado,
                    'duracion_seg' => (int) $programacion->duracion_seg,
                ]
            );

            $actuador->update([
                'estado_deseado' => $estadoNuevo,
                'cambiado_en' => now(),
            ]);

            Actuacion::create([
                'modulo_id' => $programacion->modulo_id,
                'actuador_id' => $programacion->actuador_id,
                'origen' => 'programacion',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'motivo' => [
                    'programacion_id' => $programacion->id,
                    'ejecucion_id' => $ejecucion->id,
                    'fase' => 'inicio',
                    'nombre' => $programacion->nombre,
                    'duracion_seg' => (int) $programacion->duracion_seg,
                ],
                'ejecutado_en' => now(),
            ]);

            return [
                'programacion_id' => $programacion->id,
                'programacion' => $programacion->nombre,
                'actuador' => $actuador->codigo,
                'inicio_programado' => $inicioProgramado,
                'estado_enviado' => $estadoNuevo,
                'ejecucion_id' => $ejecucion->id,
                'comando_id' => $comandoId,
                'inicio_creado' => true,
                'finalizacion_pendiente' => $requiereFinalizacion,
                'motivo' => 'comando_generado_por_programacion',
            ];
        });
    }

    private function finalizarEjecucionesVencidas(?int $moduloId, Carbon $momento): array
    {
        $query = EjecucionProgramacion::query()
            ->with(['programacion.actuador', 'programacion.modulo'])
            ->where('estado', 'ok')
            ->whereNull('fin_en')
            ->orderBy('inicio_en');

        if ($moduloId) {
            $query->whereHas('programacion', fn ($q) => $q->where('modulo_id', $moduloId));
        }

        $resultado = [
            'finalizaciones_creadas' => 0,
            'acciones' => [],
            'omitidas' => [],
        ];

        foreach ($query->get() as $ejecucion) {
            $programacion = $ejecucion->programacion;
            $actuador = $programacion?->actuador;
            $modulo = $programacion?->modulo;

            if (!$programacion || !$actuador || !$modulo) {
                $ejecucion->update([
                    'estado' => 'fallido',
                    'fin_en' => now(),
                    'nota' => 'No se pudo finalizar: programación, módulo o actuador no encontrado.',
                ]);
                $resultado['omitidas'][] = [
                    'ejecucion_id' => $ejecucion->id,
                    'motivo' => 'programacion_modulo_o_actuador_no_encontrado',
                    'omitida' => true,
                ];
                continue;
            }

            $zonaHoraria = $this->zonaHoraria($modulo);
            $inicioRaw = $ejecucion->getRawOriginal('inicio_en') ?: $ejecucion->inicio_en;
            $inicioLocal = Carbon::parse($inicioRaw, $zonaHoraria);
            $finLocal = $inicioLocal->copy()->addSeconds((int) $programacion->duracion_seg);
            $ahoraLocal = $momento->copy()->timezone($zonaHoraria);

            if ($ahoraLocal->lessThan($finLocal)) {
                continue;
            }

            $estadoActual = $actuador->estado_deseado;
            $estadoApagado = $this->estadoApagado($programacion);

            DB::transaction(function () use ($ejecucion, $programacion, $actuador, $estadoActual, $estadoApagado, $finLocal) {
                $comandoId = $this->crearComando(
                    $programacion,
                    $actuador,
                    $estadoApagado,
                    'programacion_fin',
                    [
                        'ejecucion_id' => $ejecucion->id,
                        'fin_programado' => $finLocal->toDateTimeString(),
                    ]
                );

                $actuador->update([
                    'estado_deseado' => $estadoApagado,
                    'cambiado_en' => now(),
                ]);

                $ejecucion->update([
                    'fin_en' => $finLocal->toDateTimeString(),
                    'estado' => 'ok',
                    'nota' => 'Finalización programada: comando de apagado creado.',
                ]);

                Actuacion::create([
                    'modulo_id' => $programacion->modulo_id,
                    'actuador_id' => $programacion->actuador_id,
                    'origen' => 'programacion',
                    'estado_anterior' => $estadoActual,
                    'estado_nuevo' => $estadoApagado,
                    'motivo' => [
                        'programacion_id' => $programacion->id,
                        'ejecucion_id' => $ejecucion->id,
                        'comando_id' => $comandoId,
                        'fase' => 'fin',
                        'nombre' => $programacion->nombre,
                    ],
                    'ejecutado_en' => now(),
                ]);
            });

            $resultado['finalizaciones_creadas']++;
            $resultado['acciones'][] = [
                'programacion_id' => $programacion->id,
                'programacion' => $programacion->nombre,
                'actuador' => $actuador->codigo,
                'ejecucion_id' => $ejecucion->id,
                'estado_enviado' => $estadoApagado,
                'finalizacion_creada' => true,
                'motivo' => 'comando_de_apagado_generado_por_programacion',
            ];
        }

        return $resultado;
    }

    private function crearComando(Programacion $programacion, Actuador $actuador, array $estado, string $fase, array $extra = []): int
    {
        $payload = [
            'actuador' => $actuador->codigo,
            'estado' => $estado,
            'programacion_id' => $programacion->id,
            'fase' => $fase,
        ] + $this->extraComandoDesdeEstado($estado) + $extra;

        $comando = ComandoIot::create([
            'modulo_id' => $programacion->modulo_id,
            'actuador_id' => $programacion->actuador_id,
            'tipo' => 'set_estado',
            'payload' => $payload,
            'estado' => 'pendiente',
            'nonce' => (string) Str::uuid(),
            'intentos' => 0,
            'expira_en' => now()->addMinutes(5),
        ]);

        return (int) $comando->id;
    }


    /**
     * Copia campos de control al nivel superior del payload IoT.
     * El firmware los lee en payload.accion, payload.duracion_seg,
     * payload.nivel_objetivo y payload.timeout_seg.
     */
    private function extraComandoDesdeEstado(array $estado): array
    {
        $permitidos = ['accion', 'duracion_seg', 'nivel_objetivo', 'timeout_seg', 'cantidad_g', 'pasos'];
        $extra = [];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $estado)) {
                $extra[$campo] = $estado[$campo];
            }
        }

        return $extra;
    }

    private function estadoDeseado(Programacion $programacion): array
    {
        $estado = $programacion->estado_deseado;

        if (is_string($estado)) {
            $estado = json_decode($estado, true);
        }

        return is_array($estado) ? $estado : ['on' => true];
    }

    private function estadoApagado(Programacion $programacion): array
    {
        $estado = $this->estadoDeseado($programacion);
        $estado['on'] = false;

        return $estado;
    }

    private function requiereFinalizacion(array $estado, int $duracionSeg): bool
    {
        return $duracionSeg > 0 && array_key_exists('on', $estado) && (bool) $estado['on'] === true;
    }

    private function normalizarDias(array|string|null $dias): array
    {
        if (is_string($dias)) {
            $dias = json_decode($dias, true) ?: [];
        }

        $mapa = [
            'lu' => 'lu', 'lun' => 'lu', 'lunes' => 'lu', 'mon' => 'lu', 'monday' => 'lu',
            'ma' => 'ma', 'mar' => 'ma', 'martes' => 'ma', 'tue' => 'ma', 'tuesday' => 'ma',
            'mi' => 'mi', 'mie' => 'mi', 'miércoles' => 'mi', 'miercoles' => 'mi', 'wed' => 'mi', 'wednesday' => 'mi',
            'ju' => 'ju', 'jue' => 'ju', 'jueves' => 'ju', 'thu' => 'ju', 'thursday' => 'ju',
            'vi' => 'vi', 'vie' => 'vi', 'viernes' => 'vi', 'fri' => 'vi', 'friday' => 'vi',
            'sa' => 'sa', 'sab' => 'sa', 'sábado' => 'sa', 'sabado' => 'sa', 'sat' => 'sa', 'saturday' => 'sa',
            'do' => 'do', 'dom' => 'do', 'domingo' => 'do', 'sun' => 'do', 'sunday' => 'do',
        ];

        return collect($dias)
            ->map(fn ($dia) => $mapa[strtolower(trim((string) $dia))] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function codigoDia(Carbon $momento): string
    {
        return [
            1 => 'lu',
            2 => 'ma',
            3 => 'mi',
            4 => 'ju',
            5 => 'vi',
            6 => 'sa',
            7 => 'do',
        ][(int) $momento->isoWeekday()];
    }

    private function inicioLocal(Programacion $programacion, Carbon $ahoraLocal, string $zonaHoraria): Carbon
    {
        $hora = substr((string) $programacion->hora_inicio, 0, 8);
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        return Carbon::parse($ahoraLocal->toDateString().' '.$hora, $zonaHoraria);
    }

    private function zonaHoraria(Modulo $modulo): string
    {
        return $modulo->zona_horaria
            ?: $this->config('zona_horaria_default', config('app.timezone', 'America/La_Paz'));
    }

    private function config(string $clave, mixed $default = null): mixed
    {
        $row = ConfigSistema::query()->where('clave', $clave)->first();

        if (!$row) {
            return $default;
        }

        return $row->valor ?? $default;
    }

    private function omitida(Programacion $programacion, string $motivo): array
    {
        return [
            'programacion_id' => $programacion->id,
            'programacion' => $programacion->nombre,
            'motivo' => $motivo,
            'omitida' => true,
        ];
    }
}
