<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedSistemaPro extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Config inicial
            $this->upsertConfig('modo_global', 'manual');          // manual | automatico (tu decisión)
            $this->upsertConfig('stale_min', 10);
            $this->upsertConfig('retention_days', 30);
            $this->upsertConfig('zona_horaria_default', 'America/La_Paz');
            $this->upsertConfig('iot_ack_timeout_seg', 20);
            $this->upsertConfig('iot_max_intentos', 3);

            // Módulo inicial (1 ESP32). Idempotente: se puede ejecutar el seeder varias veces.
            DB::table('modulos')->updateOrInsert(
                ['codigo' => 'MOD-001'],
                [
                    'nombre' => 'Módulo 1 - Jaula de cuyes',
                    'uid' => 'ESP32-MOD-001', // luego lo reemplazás por UID real
                    'habilitado' => 1,
                    'zona_horaria' => 'America/La_Paz',
                    'meta' => json_encode(['notas' => 'Módulo inicial de prueba']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $moduloId = DB::table('modulos')->where('codigo', 'MOD-001')->value('id');

            // Sensores prioritarios
            $sensores = [
                ['codigo' => 'S_TEMP', 'nombre' => 'Temperatura', 'tipo' => 'temperatura', 'unidad' => '°C'],
                ['codigo' => 'S_HR',   'nombre' => 'Humedad relativa', 'tipo' => 'humedad', 'unidad' => '%'],
                ['codigo' => 'S_AIR',  'nombre' => 'Calidad de aire', 'tipo' => 'calidad_aire', 'unidad' => 'ppm'],
            ];

            foreach ($sensores as $s) {
                DB::table('sensores')->updateOrInsert(
                    ['modulo_id' => $moduloId, 'codigo' => $s['codigo']],
                    [
                        'nombre' => $s['nombre'],
                        'tipo' => $s['tipo'],
                        'unidad' => $s['unidad'],
                        'activo' => 1,
                        'meta' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Actuadores
            $actuadores = [
                ['codigo' => 'D_FAN',   'nombre' => 'Ventilador', 'tipo' => 'rele'],
                ['codigo' => 'D_HEAT',  'nombre' => 'Calefactor', 'tipo' => 'rele'],
                ['codigo' => 'D_WATER', 'nombre' => 'Agua',       'tipo' => 'valvula'],
                ['codigo' => 'D_FEED',  'nombre' => 'Croquetas',  'tipo' => 'dosificador'],
            ];

            foreach ($actuadores as $a) {
                DB::table('actuadores')->updateOrInsert(
                    ['modulo_id' => $moduloId, 'codigo' => $a['codigo']],
                    [
                        'nombre' => $a['nombre'],
                        'tipo' => $a['tipo'],
                        'activo' => 1,
                        'gpio_pin' => null,
                        'invertido' => 0,
                        'estado_deseado' => json_encode(['on' => false]),
                        'estado_reportado' => null,
                        'cambiado_en' => null,
                        'meta' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Reglas base de alerta. Son idempotentes y sirven para que el panel
            // genere alertas sin configurar manualmente cada umbral desde cero.
            $sensorTempId = DB::table('sensores')->where('modulo_id', $moduloId)->where('codigo', 'S_TEMP')->value('id');
            $sensorHrId = DB::table('sensores')->where('modulo_id', $moduloId)->where('codigo', 'S_HR')->value('id');
            $sensorAirId = DB::table('sensores')->where('modulo_id', $moduloId)->where('codigo', 'S_AIR')->value('id');

            if ($sensorTempId) {
                $this->upsertReglaAlerta($moduloId, $sensorTempId, null, 'fuera_rango', 18, 26, null, 'advertencia', 'Temperatura fuera de rango en {modulo}: {valor}{unidad} (rango {umbral_min}-{umbral_max}{unidad}).', 10);
                $this->upsertReglaAlerta($moduloId, $sensorTempId, null, 'sin_datos', null, null, 15, 'advertencia', 'Sin datos recientes del sensor {sensor} en {modulo} por más de {minutos} min.', 15);
            }

            if ($sensorHrId) {
                $this->upsertReglaAlerta($moduloId, $sensorHrId, null, 'fuera_rango', 45, 75, null, 'advertencia', 'Humedad fuera de rango en {modulo}: {valor}{unidad} (rango {umbral_min}-{umbral_max}{unidad}).', 10);
                $this->upsertReglaAlerta($moduloId, $sensorHrId, null, 'sin_datos', null, null, 15, 'advertencia', 'Sin datos recientes del sensor {sensor} en {modulo} por más de {minutos} min.', 15);
            }

            if ($sensorAirId) {
                $this->upsertReglaAlerta($moduloId, $sensorAirId, null, 'arriba', null, 1200, null, 'critico', 'Calidad de aire crítica en {modulo}: {valor}{unidad} supera {umbral_max}{unidad}.', 10);
                $this->upsertReglaAlerta($moduloId, $sensorAirId, null, 'sin_datos', null, null, 15, 'advertencia', 'Sin datos recientes del sensor {sensor} en {modulo} por más de {minutos} min.', 15);
            }

            $this->upsertReglaAlerta($moduloId, null, null, 'modulo_offline', null, null, 15, 'critico', 'El módulo {modulo} está sin comunicación por más de {minutos} min.', 15);
            $this->upsertReglaAlerta($moduloId, null, null, 'comando_fallido', null, null, null, 'critico', 'Falló un comando IoT en {modulo}. Error: {error}', 5);

            // API key del módulo (mostrar solo si se crea por primera vez)
            $credencialExiste = DB::table('modulos_credenciales')
                ->where('modulo_id', $moduloId)
                ->exists();

            $apiKeyPlano = null;

            if (!$credencialExiste) {
                $apiKeyPlano = Str::random(40);

                DB::table('modulos_credenciales')->insert([
                    'modulo_id' => $moduloId,
                    'api_key_hash' => Hash::make($apiKeyPlano),
                    'revocado_en' => null,
                    'ultimo_uso_en' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command?->info("✅ Módulo listo: MOD-001 (uid=ESP32-MOD-001)");

            if ($apiKeyPlano) {
                $this->command?->warn("🔑 API KEY (guardala ahora, no se mostrará de nuevo):");
                $this->command?->line($apiKeyPlano);
            } else {
                $this->command?->warn("🔑 El módulo ya tenía API KEY; no se modificó para no desconectar el ESP32.");
            }
        });
    }

    private function upsertReglaAlerta(int $moduloId, ?int $sensorId, ?int $actuadorId, string $tipo, $umbralMin, $umbralMax, $sinDatosMin, string $severidad, string $mensaje, int $enfriamientoMin): void
    {
        $query = DB::table('reglas_alerta')
            ->where('modulo_id', $moduloId)
            ->where('tipo', $tipo);

        is_null($sensorId)
            ? $query->whereNull('sensor_id')
            : $query->where('sensor_id', $sensorId);

        is_null($actuadorId)
            ? $query->whereNull('actuador_id')
            : $query->where('actuador_id', $actuadorId);

        $data = [
            'umbral_min' => $umbralMin,
            'umbral_max' => $umbralMax,
            'sin_datos_min' => $sinDatosMin,
            'severidad' => $severidad,
            'plantilla_mensaje' => $mensaje,
            'enfriamiento_min' => $enfriamientoMin,
            'activo' => 1,
            'updated_at' => now(),
        ];

        if ($query->exists()) {
            $query->update($data);
            return;
        }

        DB::table('reglas_alerta')->insert(array_merge($data, [
            'modulo_id' => $moduloId,
            'sensor_id' => $sensorId,
            'actuador_id' => $actuadorId,
            'tipo' => $tipo,
            'created_at' => now(),
        ]));
    }

    private function upsertConfig(string $clave, $valor): void
    {
        DB::table('config_sistema')->updateOrInsert(
            ['clave' => $clave],
            [
                'valor' => json_encode($valor),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}