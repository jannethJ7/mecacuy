<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcesadorTelemetriaIot
{
    public function procesar(object $modulo, array $lecturas): array
    {
        $now = now();
        $guardadas = 0;
        $omitidas = [];

        foreach ($lecturas as $i => $l) {
            $codigoSensor = $l['codigo'] ?? $l['sensor'] ?? null;

            if (!$codigoSensor) {
                $omitidas[] = [
                    'indice' => $i,
                    'codigo' => null,
                    'motivo' => 'codigo_sensor_ausente',
                ];
                continue;
            }

            $sensor = DB::table('sensores')
                ->where('modulo_id', $modulo->id)
                ->where('codigo', $codigoSensor)
                ->first();

            if (!$sensor) {
                $omitidas[] = [
                    'indice' => $i,
                    'codigo' => $codigoSensor,
                    'motivo' => 'sensor_no_encontrado_en_el_modulo',
                ];
                continue;
            }

            if (!(bool) $sensor->activo) {
                $omitidas[] = [
                    'indice' => $i,
                    'codigo' => $codigoSensor,
                    'motivo' => 'sensor_inactivo',
                ];
                continue;
            }

            if (!array_key_exists('valor', $l) || !is_numeric($l['valor'])) {
                $omitidas[] = [
                    'indice' => $i,
                    'codigo' => $codigoSensor,
                    'motivo' => 'valor_invalido',
                ];
                continue;
            }

            $medidoEn = $l['medido_en'] ?? $now;
            if (SupervisionCalefaccion::esSensor($sensor)) {
                if ($sensor->valor_actual_en && \Carbon\Carbon::parse($medidoEn)->lt(\Carbon\Carbon::parse($sensor->valor_actual_en))) {
                    $omitidas[] = ['indice' => $i, 'codigo' => $codigoSensor, 'motivo' => 'verificacion_antigua'];
                    continue;
                }
                // Sin hora de adquisición no se puede descartar un mensaje MQTT retenido/retrasado.
                if (empty($l['medido_en'])) {
                    $l['valor'] = -1;
                    $l['calidad'] = 'error';
                    $l['raw'] = ['driver' => SupervisionCalefaccion::DRIVER, 'verificacion_valida' => false];
                }
            }
            if (NivelAgua::esSensor($sensor)) {
                if ($sensor->valor_actual_en && \Carbon\Carbon::parse($medidoEn)->lt(\Carbon\Carbon::parse($sensor->valor_actual_en))) {
                    $omitidas[] = ['indice' => $i, 'codigo' => $codigoSensor, 'motivo' => 'nivel_agua_antiguo'];
                    continue;
                }
                $rawAgua = $l['raw'] ?? [];
                $valida = !empty($l['medido_en']) && ($l['calidad'] ?? 'ok') === 'ok' && ($rawAgua['driver'] ?? '') === NivelAgua::DRIVER
                    && ($rawAgua['perfil'] ?? '') === NivelAgua::PERFIL && ($rawAgua['nivel_valido'] ?? false) === true
                    && is_numeric($rawAgua['edad_muestra_ms'] ?? null) && $rawAgua['edad_muestra_ms'] >= 0 && $rawAgua['edad_muestra_ms'] <= 2000
                    && in_array((float) $l['valor'], [0.0, 25.0, 50.0, 75.0, 100.0], true);
                if (!$valida) { $l['valor'] = -1; $l['calidad'] = 'error'; }
            }
            $raw = is_array($l['raw'] ?? null) ? $l['raw'] : [];
            $raw['codigo_recibido'] = $codigoSensor;
            $raw['campo_codigo_usado'] = array_key_exists('codigo', $l) ? 'codigo' : 'sensor';

            DB::table('lecturas')->insert([
                'sensor_id' => $sensor->id,
                'valor' => $l['valor'],
                'medido_en' => $medidoEn,
                'recibido_en' => $now,
                'calidad' => $l['calidad'] ?? 'ok',
                'raw' => json_encode($raw),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('sensores')->where('id', $sensor->id)->update([
                'valor_actual' => NivelAgua::esSensor($sensor) && ($l['calidad'] ?? 'ok') !== 'ok' ? null : $l['valor'],
                'valor_actual_en' => NivelAgua::esSensor($sensor) && ($l['calidad'] ?? 'ok') !== 'ok' ? null : $medidoEn,
                'updated_at' => $now,
            ]);

            $guardadas++;
        }

        $resultadoReglas = null;
        $resultadoAlertas = null;

        if ($guardadas > 0) {
            try {
                $resultadoReglas = app(MotorReglasAutomaticas::class)->evaluarModulo((int) $modulo->id);
            } catch (Throwable $e) {
                Log::error('Error al evaluar reglas automáticas después de guardar telemetría.', [
                    'modulo_id' => $modulo->id,
                    'error' => $e->getMessage(),
                ]);
                $resultadoReglas = ['ok' => false, 'error' => 'No se pudieron evaluar las reglas automáticas.'];
            }

            try {
                $resultadoAlertas = app(MotorAlertasAutomaticas::class)->evaluarModulo((int) $modulo->id);
            } catch (Throwable $e) {
                Log::error('Error al evaluar alertas automáticas después de guardar telemetría.', [
                    'modulo_id' => $modulo->id,
                    'error' => $e->getMessage(),
                ]);
                $resultadoAlertas = ['ok' => false, 'error' => 'No se pudieron evaluar las alertas automáticas.'];
            }
        }

        return [
            'ok' => true,
            'recibidas' => count($lecturas),
            'guardadas' => $guardadas,
            'omitidas' => $omitidas,
            'automatizacion' => $resultadoReglas,
            'alertas' => $resultadoAlertas,
        ];
    }
}
