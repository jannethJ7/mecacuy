<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class MotorAlertasAutomaticas
{
    /**
     * Evalúa todas las reglas de alerta de un módulo.
     *
     * Se usa después de recibir lecturas desde el ESP32 y también desde el botón
     * "Evaluar ahora" del panel. No requiere migraciones nuevas: usa las tablas
     * existentes reglas_alerta y alertas.
     */
    public function evaluarModulo(int $moduloId): array
    {
        $modulo = DB::table('modulos')->where('id', $moduloId)->first();

        if (!$modulo) {
            return [
                'ok' => false,
                'modulo_id' => $moduloId,
                'error' => 'Módulo no encontrado.',
            ];
        }

        $this->asegurarReglasBase($moduloId);

        $reglas = DB::table('reglas_alerta')
            ->where('modulo_id', $moduloId)
            ->where('activo', 1)
            ->orderByRaw("CASE severidad WHEN 'critico' THEN 1 WHEN 'advertencia' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get();

        $resultado = [
            'ok' => true,
            'modulo_id' => $moduloId,
            'evaluadas' => 0,
            'abiertas' => 0,
            'duplicadas' => 0,
            'en_enfriamiento' => 0,
            'cerradas' => 0,
            'omitidas' => [],
        ];

        foreach ($reglas as $regla) {
            if ($regla->tipo === 'comando_fallido') {
                continue;
            }

            $evaluacion = $this->evaluarRegla($regla, $modulo);

            if (($evaluacion['omitida'] ?? false) === true) {
                $resultado['omitidas'][] = [
                    'regla_id' => $regla->id,
                    'tipo' => $regla->tipo,
                    'motivo' => $evaluacion['motivo'] ?? 'omitida',
                ];
                continue;
            }

            $resultado['evaluadas']++;

            if (($evaluacion['activa'] ?? false) === true) {
                $apertura = $this->abrirAlertaSiCorresponde($regla, $evaluacion);
                $resultado[$apertura] = ($resultado[$apertura] ?? 0) + 1;
            } else {
                $resultado['cerradas'] += $this->cerrarAlertasResueltas($regla, $evaluacion);
            }
        }

        return $resultado;
    }

    /**
     * Evalúa reglas de alerta en todos los módulos habilitados.
     */
    public function evaluarTodos(): array
    {
        $modulos = DB::table('modulos')
            ->where('habilitado', 1)
            ->orderBy('id')
            ->get(['id', 'codigo']);

        $resumen = [
            'ok' => true,
            'modulos' => $modulos->count(),
            'evaluadas' => 0,
            'abiertas' => 0,
            'duplicadas' => 0,
            'en_enfriamiento' => 0,
            'cerradas' => 0,
            'errores' => [],
        ];

        foreach ($modulos as $modulo) {
            try {
                $r = $this->evaluarModulo((int) $modulo->id);
                $resumen['evaluadas'] += (int) ($r['evaluadas'] ?? 0);
                $resumen['abiertas'] += (int) ($r['abiertas'] ?? 0);
                $resumen['duplicadas'] += (int) ($r['duplicadas'] ?? 0);
                $resumen['en_enfriamiento'] += (int) ($r['en_enfriamiento'] ?? 0);
                $resumen['cerradas'] += (int) ($r['cerradas'] ?? 0);
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
     * Crea alerta automática cuando un comando IoT falla.
     */
    public function registrarComandoFallido(int $moduloId, ?int $actuadorId, string $nonce, ?string $error = null): array
    {
        $modulo = DB::table('modulos')->where('id', $moduloId)->first();

        if (!$modulo) {
            return [
                'ok' => false,
                'error' => 'Módulo no encontrado.',
            ];
        }

        $this->asegurarReglasBase($moduloId);

        $reglas = DB::table('reglas_alerta')
            ->where('modulo_id', $moduloId)
            ->where('activo', 1)
            ->where('tipo', 'comando_fallido')
            ->where(function ($q) use ($actuadorId) {
                $q->whereNull('actuador_id');

                if ($actuadorId) {
                    $q->orWhere('actuador_id', $actuadorId);
                }
            })
            ->get();

        if ($reglas->isEmpty()) {
            $actuador = $actuadorId ? DB::table('actuadores')->where('id', $actuadorId)->first() : null;

            $regla = (object) [
                'id' => null,
                'modulo_id' => $moduloId,
                'sensor_id' => null,
                'actuador_id' => $actuadorId,
                'tipo' => 'comando_fallido',
                'umbral_min' => null,
                'umbral_max' => null,
                'sin_datos_min' => null,
                'severidad' => 'critico',
                'plantilla_mensaje' => 'Falló un comando IoT en {modulo}.',
                'enfriamiento_min' => 5,
            ];

            $evaluacion = [
                'activa' => true,
                'mensaje' => $this->renderMensaje($regla, $modulo, null, $actuador, null, [
                    'nonce' => $nonce,
                    'error' => $error,
                ]),
                'contexto' => [
                    'tipo' => 'comando_fallido',
                    'nonce' => $nonce,
                    'error' => $error,
                    'modulo_codigo' => $modulo->codigo,
                    'actuador_codigo' => $actuador->codigo ?? null,
                ],
            ];

            $estado = $this->abrirAlertaSiCorresponde($regla, $evaluacion);

            return [
                'ok' => true,
                'reglas' => 0,
                $estado => 1,
            ];
        }

        $resultado = [
            'ok' => true,
            'reglas' => $reglas->count(),
            'abiertas' => 0,
            'duplicadas' => 0,
            'en_enfriamiento' => 0,
        ];

        foreach ($reglas as $regla) {
            $actuador = $regla->actuador_id ? DB::table('actuadores')->where('id', $regla->actuador_id)->first() : null;

            $evaluacion = [
                'activa' => true,
                'mensaje' => $this->renderMensaje($regla, $modulo, null, $actuador, null, [
                    'nonce' => $nonce,
                    'error' => $error,
                ]),
                'contexto' => [
                    'tipo' => 'comando_fallido',
                    'nonce' => $nonce,
                    'error' => $error,
                    'modulo_codigo' => $modulo->codigo,
                    'actuador_codigo' => $actuador->codigo ?? null,
                ],
            ];

            $estado = $this->abrirAlertaSiCorresponde($regla, $evaluacion);
            $resultado[$estado] = ($resultado[$estado] ?? 0) + 1;
        }

        return $resultado;
    }

    private function evaluarRegla(object $regla, object $modulo): array
    {
        $sensor = $regla->sensor_id
            ? DB::table('sensores')->where('id', $regla->sensor_id)->first()
            : null;

        $actuador = $regla->actuador_id
            ? DB::table('actuadores')->where('id', $regla->actuador_id)->first()
            : null;

        if (in_array($regla->tipo, ['arriba', 'abajo', 'fuera_rango', 'sin_datos'], true) && !$sensor) {
            return [
                'omitida' => true,
                'motivo' => 'sensor_no_encontrado',
            ];
        }

        $valor = $sensor && $sensor->valor_actual !== null ? (float) $sensor->valor_actual : null;
        $unidad = $sensor->unidad ?? '';
        $contextoBase = [
            'tipo' => $regla->tipo,
            'modulo_codigo' => $modulo->codigo,
            'sensor_codigo' => $sensor->codigo ?? null,
            'sensor_nombre' => $sensor->nombre ?? null,
            'actuador_codigo' => $actuador->codigo ?? null,
            'valor' => $valor,
            'unidad' => $unidad,
            'umbral_min' => $regla->umbral_min !== null ? (float) $regla->umbral_min : null,
            'umbral_max' => $regla->umbral_max !== null ? (float) $regla->umbral_max : null,
            'evaluado_en' => now()->toDateTimeString(),
        ];

        $activa = false;
        $detalle = [];

        switch ($regla->tipo) {
            case 'arriba':
                if ($valor === null || $regla->umbral_max === null) {
                    return ['omitida' => true, 'motivo' => 'sin_valor_o_umbral_max'];
                }
                $activa = $valor > (float) $regla->umbral_max;
                break;

            case 'abajo':
                if ($valor === null || $regla->umbral_min === null) {
                    return ['omitida' => true, 'motivo' => 'sin_valor_o_umbral_min'];
                }
                $activa = $valor < (float) $regla->umbral_min;
                break;

            case 'fuera_rango':
                if ($valor === null) {
                    return ['omitida' => true, 'motivo' => 'sin_valor_actual'];
                }

                $menorAlMinimo = $regla->umbral_min !== null && $valor < (float) $regla->umbral_min;
                $mayorAlMaximo = $regla->umbral_max !== null && $valor > (float) $regla->umbral_max;
                $activa = $menorAlMinimo || $mayorAlMaximo;
                $detalle['lado'] = $menorAlMinimo ? 'bajo' : ($mayorAlMaximo ? 'alto' : 'normal');
                break;

            case 'sin_datos':
                $minutos = (int) ($regla->sin_datos_min ?: $this->getConfig('stale_min', 10));
                $ultima = $sensor->valor_actual_en ? Carbon::parse($sensor->valor_actual_en) : null;
                $activa = !$ultima || $ultima->lt(now()->subMinutes($minutos));
                $detalle['sin_datos_min'] = $minutos;
                $detalle['ultima_lectura_en'] = $ultima?->toDateTimeString();
                $detalle['minutos_desde_ultima_lectura'] = $ultima ? $ultima->diffInMinutes(now()) : null;
                break;

            case 'modulo_offline':
                $minutos = (int) ($regla->sin_datos_min ?: $this->getConfig('stale_min', 10));
                $ultimo = $modulo->ultimo_contacto ? Carbon::parse($modulo->ultimo_contacto) : null;
                $activa = !$ultimo || $ultimo->lt(now()->subMinutes($minutos));
                $detalle['sin_datos_min'] = $minutos;
                $detalle['ultimo_contacto'] = $ultimo?->toDateTimeString();
                $detalle['minutos_desde_ultimo_contacto'] = $ultimo ? $ultimo->diffInMinutes(now()) : null;
                break;

            default:
                return [
                    'omitida' => true,
                    'motivo' => 'tipo_no_soportado',
                ];
        }

        $contexto = array_merge($contextoBase, $detalle);

        return [
            'activa' => $activa,
            'mensaje' => $this->renderMensaje($regla, $modulo, $sensor, $actuador, $valor, $contexto),
            'contexto' => $contexto,
        ];
    }

    private function abrirAlertaSiCorresponde(object $regla, array $evaluacion): string
    {
        $moduloId = (int) $regla->modulo_id;
        $reglaId = $regla->id ? (int) $regla->id : null;
        $sensorId = $regla->sensor_id ? (int) $regla->sensor_id : null;
        $actuadorId = $regla->actuador_id ? (int) $regla->actuador_id : null;
        $now = now();

        $abierta = DB::table('alertas')
            ->where('modulo_id', $moduloId)
            ->where(function ($q) use ($reglaId, $sensorId, $actuadorId, $regla) {
                if ($reglaId) {
                    $q->where('regla_alerta_id', $reglaId);
                } else {
                    $q->whereNull('regla_alerta_id')
                        ->where('sensor_id', $sensorId)
                        ->where('actuador_id', $actuadorId)
                        ->where('mensaje', $regla->plantilla_mensaje);
                }
            })
            ->whereIn('estado', ['abierta', 'reconocida'])
            ->exists();

        if ($abierta) {
            return 'duplicadas';
        }

        $enfriamientoMin = max(0, (int) ($regla->enfriamiento_min ?? 10));

        if ($enfriamientoMin > 0) {
            $reciente = DB::table('alertas')
                ->where('modulo_id', $moduloId)
                ->where(function ($q) use ($reglaId, $sensorId, $actuadorId, $regla) {
                    if ($reglaId) {
                        $q->where('regla_alerta_id', $reglaId);
                    } else {
                        $q->whereNull('regla_alerta_id')
                            ->where('sensor_id', $sensorId)
                            ->where('actuador_id', $actuadorId)
                            ->where('mensaje', $regla->plantilla_mensaje);
                    }
                })
                ->where('created_at', '>=', $now->copy()->subMinutes($enfriamientoMin))
                ->exists();

            if ($reciente) {
                return 'en_enfriamiento';
            }
        }

        DB::table('alertas')->insert([
            'modulo_id' => $moduloId,
            'regla_alerta_id' => $reglaId,
            'sensor_id' => $sensorId,
            'actuador_id' => $actuadorId,
            'severidad' => $regla->severidad ?? 'advertencia',
            'mensaje' => $evaluacion['mensaje'],
            'contexto' => json_encode($evaluacion['contexto'] ?? [], JSON_UNESCAPED_UNICODE),
            'estado' => 'abierta',
            'reconocida_por_user_id' => null,
            'reconocida_en' => null,
            'cerrada_en' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('auditoria_eventos')->insert([
            'actor_tipo' => 'sistema',
            'actor_id' => null,
            'evento_tipo' => 'alerta.abierta',
            'entidad_tipo' => 'regla_alerta',
            'entidad_id' => $reglaId,
            'data' => json_encode([
                'modulo_id' => $moduloId,
                'sensor_id' => $sensorId,
                'actuador_id' => $actuadorId,
                'severidad' => $regla->severidad ?? 'advertencia',
                'mensaje' => $evaluacion['mensaje'],
                'contexto' => $evaluacion['contexto'] ?? [],
            ], JSON_UNESCAPED_UNICODE),
            'creado_en' => $now,
        ]);

        return 'abiertas';
    }

    private function cerrarAlertasResueltas(object $regla, array $evaluacion): int
    {
        if (!$regla->id) {
            return 0;
        }

        $alertas = DB::table('alertas')
            ->where('regla_alerta_id', $regla->id)
            ->whereIn('estado', ['abierta', 'reconocida'])
            ->pluck('id');

        if ($alertas->isEmpty()) {
            return 0;
        }

        $now = now();

        DB::table('alertas')
            ->whereIn('id', $alertas)
            ->update([
                'estado' => 'cerrada',
                'cerrada_en' => $now,
                'updated_at' => $now,
            ]);

        foreach ($alertas as $alertaId) {
            DB::table('auditoria_eventos')->insert([
                'actor_tipo' => 'sistema',
                'actor_id' => null,
                'evento_tipo' => 'alerta.cerrada_auto',
                'entidad_tipo' => 'alerta',
                'entidad_id' => $alertaId,
                'data' => json_encode([
                    'regla_alerta_id' => $regla->id,
                    'motivo' => 'condicion_resuelta',
                    'contexto_evaluacion' => $evaluacion['contexto'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
                'creado_en' => $now,
            ]);
        }

        return $alertas->count();
    }

    private function renderMensaje(object $regla, object $modulo, ?object $sensor, ?object $actuador, ?float $valor, array $contexto = []): string
    {
        $unidad = $sensor->unidad ?? '';
        $minutos = $contexto['sin_datos_min'] ?? $regla->sin_datos_min ?? '';

        $reemplazos = [
            '{modulo}' => $modulo->codigo ?? 'módulo',
            '{sensor}' => $sensor->codigo ?? 'sensor',
            '{sensor_nombre}' => $sensor->nombre ?? 'sensor',
            '{actuador}' => $actuador->codigo ?? 'actuador',
            '{valor}' => $valor !== null ? $this->formatoNumero($valor) : 'sin dato',
            '{unidad}' => $unidad,
            '{umbral_min}' => $regla->umbral_min !== null ? $this->formatoNumero((float) $regla->umbral_min) : '—',
            '{umbral_max}' => $regla->umbral_max !== null ? $this->formatoNumero((float) $regla->umbral_max) : '—',
            '{minutos}' => (string) $minutos,
            '{tipo}' => $regla->tipo ?? 'alerta',
            '{error}' => $contexto['error'] ?? '',
            '{nonce}' => $contexto['nonce'] ?? '',
        ];

        $mensaje = strtr($regla->plantilla_mensaje ?: 'Alerta automática en {modulo}.', $reemplazos);

        return mb_substr($mensaje, 0, 255);
    }

    private function formatoNumero(float $numero): string
    {
        return rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');
    }

    /**
     * Crea reglas básicas si todavía no existen. Esto permite que el sistema
     * genere alertas aunque el usuario aún no haya configurado reglas manualmente.
     */
    private function asegurarReglasBase(int $moduloId): void
    {
        $modulo = DB::table('modulos')->where('id', $moduloId)->first();

        if (!$modulo) {
            return;
        }

        $sensores = DB::table('sensores')
            ->where('modulo_id', $moduloId)
            ->where('activo', 1)
            ->get();

        foreach ($sensores as $sensor) {
            if ($sensor->codigo === 'S_TEMP' || $sensor->tipo === 'temperatura') {
                $this->upsertReglaAlerta([
                    'modulo_id' => $moduloId,
                    'sensor_id' => $sensor->id,
                    'actuador_id' => null,
                    'tipo' => 'fuera_rango',
                    'umbral_min' => 18,
                    'umbral_max' => 26,
                    'sin_datos_min' => null,
                    'severidad' => 'advertencia',
                    'plantilla_mensaje' => 'Temperatura fuera de rango en {modulo}: {valor}{unidad} (rango {umbral_min}-{umbral_max}{unidad}).',
                    'enfriamiento_min' => 10,
                    'activo' => 1,
                ]);
            }

            if ($sensor->codigo === 'S_HR' || $sensor->tipo === 'humedad') {
                $this->upsertReglaAlerta([
                    'modulo_id' => $moduloId,
                    'sensor_id' => $sensor->id,
                    'actuador_id' => null,
                    'tipo' => 'fuera_rango',
                    'umbral_min' => 45,
                    'umbral_max' => 75,
                    'sin_datos_min' => null,
                    'severidad' => 'advertencia',
                    'plantilla_mensaje' => 'Humedad fuera de rango en {modulo}: {valor}{unidad} (rango {umbral_min}-{umbral_max}{unidad}).',
                    'enfriamiento_min' => 10,
                    'activo' => 1,
                ]);
            }

            if ($sensor->codigo === 'S_AIR' || $sensor->tipo === 'calidad_aire') {
                $this->upsertReglaAlerta([
                    'modulo_id' => $moduloId,
                    'sensor_id' => $sensor->id,
                    'actuador_id' => null,
                    'tipo' => 'arriba',
                    'umbral_min' => null,
                    'umbral_max' => 1200,
                    'sin_datos_min' => null,
                    'severidad' => 'critico',
                    'plantilla_mensaje' => 'Calidad de aire crítica en {modulo}: {valor}{unidad} supera {umbral_max}{unidad}.',
                    'enfriamiento_min' => 10,
                    'activo' => 1,
                ]);
            }

            $this->upsertReglaAlerta([
                'modulo_id' => $moduloId,
                'sensor_id' => $sensor->id,
                'actuador_id' => null,
                'tipo' => 'sin_datos',
                'umbral_min' => null,
                'umbral_max' => null,
                'sin_datos_min' => 15,
                'severidad' => 'advertencia',
                'plantilla_mensaje' => 'Sin datos recientes del sensor {sensor} en {modulo} por más de {minutos} min.',
                'enfriamiento_min' => 15,
                'activo' => 1,
            ]);
        }

        $this->upsertReglaAlerta([
            'modulo_id' => $moduloId,
            'sensor_id' => null,
            'actuador_id' => null,
            'tipo' => 'modulo_offline',
            'umbral_min' => null,
            'umbral_max' => null,
            'sin_datos_min' => 15,
            'severidad' => 'critico',
            'plantilla_mensaje' => 'El módulo {modulo} está sin comunicación por más de {minutos} min.',
            'enfriamiento_min' => 15,
            'activo' => 1,
        ]);

        $this->upsertReglaAlerta([
            'modulo_id' => $moduloId,
            'sensor_id' => null,
            'actuador_id' => null,
            'tipo' => 'comando_fallido',
            'umbral_min' => null,
            'umbral_max' => null,
            'sin_datos_min' => null,
            'severidad' => 'critico',
            'plantilla_mensaje' => 'Falló un comando IoT en {modulo}. Error: {error}',
            'enfriamiento_min' => 5,
            'activo' => 1,
        ]);
    }

    private function upsertReglaAlerta(array $data): void
    {
        $query = DB::table('reglas_alerta')
            ->where('modulo_id', $data['modulo_id'])
            ->where('tipo', $data['tipo']);

        is_null($data['sensor_id'])
            ? $query->whereNull('sensor_id')
            : $query->where('sensor_id', $data['sensor_id']);

        is_null($data['actuador_id'])
            ? $query->whereNull('actuador_id')
            : $query->where('actuador_id', $data['actuador_id']);

        $existe = $query->exists();

        if ($existe) {
            $query->update(array_merge($data, [
                'updated_at' => now(),
            ]));
            return;
        }

        DB::table('reglas_alerta')->insert(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function getConfig(string $clave, mixed $default = null): mixed
    {
        $row = DB::table('config_sistema')->where('clave', $clave)->first();

        if (!$row) {
            return $default;
        }

        $valor = json_decode($row->valor, true);

        return $valor ?? $default;
    }
}
