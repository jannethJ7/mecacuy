<?php

namespace App\Services;

use App\Models\Actuador;
use App\Models\ConfigSistema;
use App\Models\EstadoRegla;
use App\Models\Modulo;
use App\Models\ReglaAutomatica;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MotorReglasAutomaticas
{
    /**
     * Evalúa todas las reglas activas de todos los módulos habilitados.
     */
    public function evaluarTodos(): array
    {
        $modo = $this->config('modo_global', 'manual');

        if ($modo !== 'automatico') {
            return [
                'ok' => true,
                'modo' => $modo,
                'omitido' => true,
                'motivo' => 'El sistema no está en modo automático.',
                'reglas_evaluadas' => 0,
                'comandos_creados' => 0,
            ];
        }

        $resumen = $this->resumenBase($modo);
        $modulos = Modulo::query()
            ->where('habilitado', true)
            ->orderBy('id')
            ->get(['id']);

        foreach ($modulos as $modulo) {
            $resultado = $this->evaluarModulo((int) $modulo->id);
            $this->sumarResumen($resumen, $resultado);
        }

        return $resumen;
    }

    /**
     * Evalúa las reglas activas de un módulo y crea comandos IoT cuando corresponde.
     */
    public function evaluarModulo(int $moduloId): array
    {
        $modo = $this->config('modo_global', 'manual');

        if ($modo !== 'automatico') {
            return [
                'ok' => true,
                'modo' => $modo,
                'modulo_id' => $moduloId,
                'omitido' => true,
                'motivo' => 'El sistema no está en modo automático.',
                'reglas_evaluadas' => 0,
                'comandos_creados' => 0,
                'acciones' => [],
                'omitidas' => [],
            ];
        }

        $resultado = $this->resumenBase($modo, $moduloId);

        $reglas = ReglaAutomatica::query()
            ->with(['sensor', 'actuador', 'estado'])
            ->where('modulo_id', $moduloId)
            ->where('activo', true)
            ->orderBy('prioridad')
            ->orderBy('id')
            ->get();

        foreach ($reglas as $regla) {
            $resultado['reglas_evaluadas']++;

            $evaluacion = $this->evaluarRegla($regla);

            if (!empty($evaluacion['omitida'])) {
                $resultado['omitidas'][] = $evaluacion;
                continue;
            }

            if (!empty($evaluacion['comando_creado'])) {
                $resultado['comandos_creados']++;
            }

            $resultado['acciones'][] = $evaluacion;
        }

        return $resultado;
    }

    private function evaluarRegla(ReglaAutomatica $regla): array
    {
        $sensor = $regla->sensor;
        $actuador = $regla->actuador;

        if (!$sensor || !$actuador) {
            $this->marcarEvaluada($regla, [
                'activo' => false,
                'motivo' => 'sensor_o_actuador_no_encontrado',
            ]);

            return $this->omitida($regla, 'sensor_o_actuador_no_encontrado');
        }

        if ((int) $sensor->modulo_id !== (int) $regla->modulo_id || (int) $actuador->modulo_id !== (int) $regla->modulo_id) {
            $this->marcarEvaluada($regla, [
                'activo' => false,
                'motivo' => 'sensor_actuador_fuera_del_modulo',
            ]);

            return $this->omitida($regla, 'sensor_actuador_fuera_del_modulo');
        }

        if (!$sensor->activo || !$actuador->activo) {
            $this->marcarEvaluada($regla, [
                'activo' => false,
                'motivo' => 'sensor_o_actuador_inactivo',
            ]);

            return $this->omitida($regla, 'sensor_o_actuador_inactivo');
        }

        if ($sensor->valor_actual === null) {
            $this->marcarEvaluada($regla, [
                'activo' => false,
                'motivo' => 'sensor_sin_lectura_actual',
            ]);

            return $this->omitida($regla, 'sensor_sin_lectura_actual');
        }

        $estadoRegla = $regla->estado ?: new EstadoRegla(['regla_id' => $regla->id]);
        $latch = is_array($estadoRegla->estado_latch) ? $estadoRegla->estado_latch : [];
        $valor = (float) $sensor->valor_actual;

        $decision = $this->resolverDecision($regla, $valor, $latch);

        if (!empty($decision['omitida'])) {
            $this->guardarEstadoRegla($estadoRegla, $decision['latch']);
            return $this->omitida($regla, $decision['motivo'], $valor);
        }

        $this->guardarEstadoRegla($estadoRegla, $decision['latch']);

        if (!empty($decision['en_retardo'])) {
            return [
                'regla_id' => $regla->id,
                'regla' => $regla->nombre,
                'sensor' => $sensor->codigo,
                'actuador' => $actuador->codigo,
                'valor' => $valor,
                'estado_objetivo' => $decision['activo_objetivo'],
                'comando_creado' => false,
                'motivo' => 'retardo_en_progreso',
                'faltan_segundos' => $decision['faltan_segundos'] ?? null,
            ];
        }

        $payload = is_array($regla->payload) ? $regla->payload : [];

        if (!$decision['activo_objetivo'] && ($payload['apagar_al_normalizar'] ?? true) === false) {
            return [
                'regla_id' => $regla->id,
                'regla' => $regla->nombre,
                'sensor' => $sensor->codigo,
                'actuador' => $actuador->codigo,
                'valor' => $valor,
                'estado_objetivo' => false,
                'comando_creado' => false,
                'motivo' => 'normalizado_sin_apagado_automatico',
            ];
        }

        $estadoNuevo = $this->estadoParaRegla($regla, (bool) $decision['activo_objetivo']);
        $estadoAnterior = $actuador->estado_deseado;

        if ($this->estadosIguales($estadoAnterior, $estadoNuevo)) {
            return [
                'regla_id' => $regla->id,
                'regla' => $regla->nombre,
                'sensor' => $sensor->codigo,
                'actuador' => $actuador->codigo,
                'valor' => $valor,
                'estado_objetivo' => (bool) $decision['activo_objetivo'],
                'estado_enviado' => $estadoNuevo,
                'comando_creado' => false,
                'motivo' => 'el_actuador_ya_tiene_el_estado_deseado',
            ];
        }

        $comandoId = $this->crearComando($regla, $actuador, $estadoAnterior, $estadoNuevo, $valor, (bool) $decision['activo_objetivo']);

        return [
            'regla_id' => $regla->id,
            'regla' => $regla->nombre,
            'sensor' => $sensor->codigo,
            'actuador' => $actuador->codigo,
            'valor' => $valor,
            'estado_objetivo' => (bool) $decision['activo_objetivo'],
            'estado_enviado' => $estadoNuevo,
            'comando_id' => $comandoId,
            'comando_creado' => true,
            'motivo' => 'comando_generado_por_regla',
        ];
    }

    private function resolverDecision(ReglaAutomatica $regla, float $valor, array $latch): array
    {
        $min = $regla->objetivo_min !== null ? (float) $regla->objetivo_min : null;
        $max = $regla->objetivo_max !== null ? (float) $regla->objetivo_max : null;
        $histeresis = max(0.0, (float) ($regla->histeresis ?? 0));
        $activoActual = (bool) ($latch['activo'] ?? false);
        $now = now();

        if ($min === null && $max === null) {
            $latch['activo'] = false;
            $latch['motivo'] = 'regla_sin_rango_objetivo';
            $latch['ultima_evaluacion'] = $now->toDateTimeString();

            return [
                'omitida' => true,
                'motivo' => 'regla_sin_rango_objetivo',
                'latch' => $latch,
            ];
        }

        $activoObjetivo = $this->condicionActiva($valor, $min, $max, $histeresis, $activoActual);
        $retardoSeg = max(0, (int) ($regla->retardo_seg ?? 0));

        $latch['valor'] = $valor;
        $latch['objetivo_min'] = $min;
        $latch['objetivo_max'] = $max;
        $latch['histeresis'] = $histeresis;
        $latch['ultima_evaluacion'] = $now->toDateTimeString();

        if ($activoObjetivo === $activoActual) {
            unset($latch['pendiente_activo'], $latch['pendiente_desde']);
            $latch['activo'] = $activoActual;
            $latch['motivo'] = 'sin_cambio_de_estado';

            return [
                'activo_objetivo' => $activoActual,
                'latch' => $latch,
            ];
        }

        if ($retardoSeg <= 0) {
            unset($latch['pendiente_activo'], $latch['pendiente_desde']);
            $latch['activo'] = $activoObjetivo;
            $latch['motivo'] = 'cambio_aplicado_sin_retardo';

            return [
                'activo_objetivo' => $activoObjetivo,
                'latch' => $latch,
            ];
        }

        $pendienteActivo = array_key_exists('pendiente_activo', $latch)
            ? (bool) $latch['pendiente_activo']
            : null;

        if ($pendienteActivo !== $activoObjetivo || empty($latch['pendiente_desde'])) {
            $latch['pendiente_activo'] = $activoObjetivo;
            $latch['pendiente_desde'] = $now->toDateTimeString();
            $latch['activo'] = $activoActual;
            $latch['motivo'] = 'retardo_iniciado';

            return [
                'activo_objetivo' => $activoActual,
                'en_retardo' => true,
                'faltan_segundos' => $retardoSeg,
                'latch' => $latch,
            ];
        }

        $pendienteDesde = Carbon::parse($latch['pendiente_desde']);
        $segundosTranscurridos = $pendienteDesde->diffInSeconds($now);

        if ($segundosTranscurridos < $retardoSeg) {
            $latch['activo'] = $activoActual;
            $latch['motivo'] = 'retardo_en_progreso';

            return [
                'activo_objetivo' => $activoActual,
                'en_retardo' => true,
                'faltan_segundos' => $retardoSeg - $segundosTranscurridos,
                'latch' => $latch,
            ];
        }

        unset($latch['pendiente_activo'], $latch['pendiente_desde']);
        $latch['activo'] = $activoObjetivo;
        $latch['motivo'] = 'cambio_aplicado_despues_de_retardo';

        return [
            'activo_objetivo' => $activoObjetivo,
            'latch' => $latch,
        ];
    }

    private function condicionActiva(float $valor, ?float $min, ?float $max, float $histeresis, bool $activoActual): bool
    {
        if ($min !== null && $max !== null) {
            if ($valor < $min || $valor > $max) {
                return true;
            }

            if ($activoActual) {
                return !($valor >= ($min + $histeresis) && $valor <= ($max - $histeresis));
            }

            return false;
        }

        if ($max !== null) {
            if ($valor > $max) {
                return true;
            }

            if ($activoActual) {
                return $valor > ($max - $histeresis);
            }

            return false;
        }

        if ($min !== null) {
            if ($valor < $min) {
                return true;
            }

            if ($activoActual) {
                return $valor < ($min + $histeresis);
            }

            return false;
        }

        return false;
    }

    private function estadoParaRegla(ReglaAutomatica $regla, bool $activo): array
    {
        $payload = is_array($regla->payload) ? $regla->payload : [];

        if ($activo) {
            return $payload['estado_activo'] ?? $payload['estado'] ?? ['on' => true];
        }

        return $payload['estado_inactivo'] ?? ['on' => false];
    }

    private function crearComando(ReglaAutomatica $regla, Actuador $actuador, ?array $estadoAnterior, array $estadoNuevo, float $valor, bool $activoObjetivo): int
    {
        return DB::transaction(function () use ($regla, $actuador, $estadoAnterior, $estadoNuevo, $valor, $activoObjetivo) {
            $actuador->update([
                'estado_deseado' => $estadoNuevo,
                'cambiado_en' => now(),
            ]);

            $comandoId = DB::table('comandos_iot')->insertGetId([
                'modulo_id' => $actuador->modulo_id,
                'actuador_id' => $actuador->id,
                'tipo' => 'set_estado',
                'payload' => json_encode([
                    'actuador' => $actuador->codigo,
                    'estado' => $estadoNuevo,
                    'origen' => 'regla',
                    'regla_id' => $regla->id,
                ] + $this->extraComandoDesdeRegla($regla, $estadoNuevo, $activoObjetivo)),
                'estado' => 'pendiente',
                'nonce' => (string) Str::uuid(),
                'intentos' => 0,
                'expira_en' => now()->addMinutes(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('actuaciones')->insert([
                'modulo_id' => $actuador->modulo_id,
                'actuador_id' => $actuador->id,
                'origen' => 'regla',
                'estado_anterior' => json_encode($estadoAnterior),
                'estado_nuevo' => json_encode($estadoNuevo),
                'motivo' => json_encode([
                    'fuente' => 'motor_reglas',
                    'regla_id' => $regla->id,
                    'regla' => $regla->nombre,
                    'sensor_id' => $regla->sensor_id,
                    'valor' => $valor,
                    'activo_objetivo' => $activoObjetivo,
                ]),
                'ejecutado_en' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $comandoId;
        });
    }


    /**
     * Permite que una regla use acciones especiales del firmware.
     * Ejemplo agua: {"accion":"llenar_hasta","nivel_objetivo":50,"timeout_seg":90}
     */
    private function extraComandoDesdeRegla(ReglaAutomatica $regla, array $estadoNuevo, bool $activoObjetivo): array
    {
        $payload = is_array($regla->payload) ? $regla->payload : [];
        $extra = [];

        foreach (['accion', 'duracion_seg', 'nivel_objetivo', 'timeout_seg', 'cantidad_g', 'pasos'] as $campo) {
            if (array_key_exists($campo, $estadoNuevo)) {
                $extra[$campo] = $estadoNuevo[$campo];
            } elseif ($activoObjetivo && array_key_exists($campo, $payload)) {
                $extra[$campo] = $payload[$campo];
            }
        }

        return $extra;
    }

    private function marcarEvaluada(ReglaAutomatica $regla, array $latch): void
    {
        $estadoRegla = $regla->estado ?: new EstadoRegla(['regla_id' => $regla->id]);
        $this->guardarEstadoRegla($estadoRegla, $latch + [
            'ultima_evaluacion' => now()->toDateTimeString(),
        ]);
    }

    private function guardarEstadoRegla(EstadoRegla $estadoRegla, array $latch): void
    {
        $ahora = now();
        $activoAnterior = (bool) (($estadoRegla->estado_latch['activo'] ?? false));
        $activoNuevo = (bool) (($latch['activo'] ?? false));

        $estadoRegla->estado_latch = $latch;
        $estadoRegla->evaluado_en = $ahora;

        if (!$estadoRegla->exists || $activoAnterior !== $activoNuevo) {
            $estadoRegla->cambiado_en = $ahora;
        }

        $estadoRegla->save();
    }

    private function omitida(ReglaAutomatica $regla, string $motivo, ?float $valor = null): array
    {
        return [
            'regla_id' => $regla->id,
            'regla' => $regla->nombre,
            'sensor' => $regla->sensor->codigo ?? null,
            'actuador' => $regla->actuador->codigo ?? null,
            'valor' => $valor,
            'omitida' => true,
            'comando_creado' => false,
            'motivo' => $motivo,
        ];
    }

    private function estadosIguales(?array $a, ?array $b): bool
    {
        return $this->jsonOrdenado($a ?? []) === $this->jsonOrdenado($b ?? []);
    }

    private function jsonOrdenado(array $data): string
    {
        ksort($data);
        return json_encode($data);
    }

    private function config(string $clave, mixed $default = null): mixed
    {
        $row = ConfigSistema::query()->where('clave', $clave)->first();

        if (!$row) {
            return $default;
        }

        return $row->valor ?? $default;
    }

    private function resumenBase(string $modo, ?int $moduloId = null): array
    {
        return [
            'ok' => true,
            'modo' => $modo,
            'modulo_id' => $moduloId,
            'omitido' => false,
            'reglas_evaluadas' => 0,
            'comandos_creados' => 0,
            'acciones' => [],
            'omitidas' => [],
        ];
    }

    private function sumarResumen(array &$total, array $parcial): void
    {
        $total['reglas_evaluadas'] += (int) ($parcial['reglas_evaluadas'] ?? 0);
        $total['comandos_creados'] += (int) ($parcial['comandos_creados'] ?? 0);
        $total['acciones'] = array_merge($total['acciones'], $parcial['acciones'] ?? []);
        $total['omitidas'] = array_merge($total['omitidas'], $parcial['omitidas'] ?? []);
    }
}
