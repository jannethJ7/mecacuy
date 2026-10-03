<?php

namespace App\Http\Controllers\Iot\V1;

use App\Services\ProcesadorAckIot;
use App\Services\ProcesadorTelemetriaIot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MqttWebhookController
{
    public function __invoke(Request $request)
    {
        $esperado = (string) config('mqtt.webhook_secret');
        $recibido = (string) $request->header('X-MECACUY-MQTT-SECRET');

        if ($esperado === '' || $recibido === '' || !hash_equals($esperado, $recibido)) {
            return response()->json(['ok' => false, 'error' => 'Webhook MQTT no autorizado.'], 401);
        }

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'payload' => ['required'],
            'clientid' => ['nullable', 'string', 'max:255'],
            'qos' => ['nullable', 'integer', 'between:0,2'],
            'timestamp' => ['nullable'],
        ]);

        $payload = $data['payload'];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                return response()->json(['ok' => false, 'error' => 'Payload MQTT no es JSON válido.'], 422);
            }
            $payload = $decoded;
        }

        if (!is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Payload MQTT inválido.'], 422);
        }

        $partes = explode('/', trim($data['topic'], '/'));
        $prefix = trim((string) config('mqtt.topic_prefix', 'mecacuy'), '/');

        if (count($partes) !== 3 || $partes[0] !== $prefix) {
            return response()->json(['ok' => false, 'error' => 'Topic MQTT fuera del esquema MecaCuy.'], 422);
        }

        [, $moduloCodigo, $canal] = $partes;

        $modulo = DB::table('modulos')
            ->where('codigo', $moduloCodigo)
            ->where('habilitado', 1)
            ->first();

        if (!$modulo) {
            return response()->json(['ok' => false, 'error' => 'Módulo MQTT no encontrado.'], 404);
        }

        $rules = match ($canal) {
            'telemetry' => [
                'lecturas' => ['required', 'array', 'min:1', 'max:500'],
                'lecturas.*' => ['required', 'array'],
                'lecturas.*.codigo' => ['required_without:lecturas.*.sensor', 'nullable', 'string', 'max:40'],
                'lecturas.*.sensor' => ['required_without:lecturas.*.codigo', 'nullable', 'string', 'max:40'],
                'lecturas.*.valor' => ['required', 'numeric'],
                'lecturas.*.medido_en' => ['nullable', 'date'],
                'lecturas.*.calidad' => ['nullable', 'in:ok,dudoso,error'],
                'lecturas.*.raw' => ['nullable', 'array'],
            ],
            'ack' => [
                'nonce' => ['required', 'string', 'max:64'],
                'ok' => ['required', 'boolean'],
                'error' => ['nullable', 'string', 'max:255'],
                'reportados' => ['nullable', 'array', 'max:100'],
                'reportados.*' => ['required', 'array'],
                'reportados.*.actuador' => ['required', 'string', 'max:40'],
                'reportados.*.estado' => ['required', 'array'],
            ],
            default => [],
        };
        // Usa el resultado validado y evita actualizar contacto con mensajes inválidos.
        if ($rules !== []) {
            $validated = Validator::make($payload, $rules)->validate();
            $payload = array_replace($payload, $validated);
        }

        if (in_array($canal, ['telemetry', 'ack', 'status'], true)) {
            $this->actualizarContacto($modulo, $payload, $data['clientid'] ?? null, $canal);
        }

        if ($canal === 'telemetry') {
            $lecturas = $payload['lecturas'] ?? null;
            if (!is_array($lecturas) || empty($lecturas)) {
                return response()->json(['ok' => false, 'error' => 'telemetry requiere lecturas[].'], 422);
            }

            return response()->json(
                app(ProcesadorTelemetriaIot::class)->procesar($modulo, $lecturas)
                + ['canal' => 'mqtt']
            );
        }

        if ($canal === 'ack') {
            if (empty($payload['nonce']) || !array_key_exists('ok', $payload)) {
                return response()->json(['ok' => false, 'error' => 'ack requiere nonce y ok.'], 422);
            }

            return response()->json(
                app(ProcesadorAckIot::class)->procesar(
                    $modulo,
                    (string) $payload['nonce'],
                    (bool) $payload['ok'],
                    isset($payload['error']) ? (string) $payload['error'] : null,
                    is_array($payload['reportados'] ?? null) ? $payload['reportados'] : []
                ) + ['canal' => 'mqtt']
            );
        }

        if ($canal === 'status') {
            return response()->json([
                'ok' => true,
                'canal' => 'mqtt',
                'status' => $payload['status'] ?? $payload['online'] ?? null,
            ]);
        }

        return response()->json(['ok' => true, 'ignorado' => true, 'canal' => $canal]);
    }

    private function actualizarContacto(object $modulo, array $payload, ?string $clientId, string $canal): void
    {
        $meta = json_decode($modulo->meta ?? '{}', true);
        if (!is_array($meta)) {
            $meta = [];
        }

        $meta['mqtt'] = array_filter([
            'ultimo_canal' => $canal,
            'client_id' => $clientId,
            'status' => $payload['status'] ?? null,
            'ultimo_mensaje_en' => now()->toIso8601String(),
        ], static fn ($v) => $v !== null);

        try {
            $update = [
                'rssi' => isset($payload['rssi']) && is_numeric($payload['rssi']) ? (int) $payload['rssi'] : $modulo->rssi,
                'version_firmware' => !empty($payload['fw']) ? (string) $payload['fw'] : $modulo->version_firmware,
                'meta' => json_encode($meta),
                'updated_at' => now(),
            ];

            // Un LWT offline lo publica el broker, no el dispositivo. No lo
            // contamos como contacto real para no falsear la presencia del ESP32.
            if (($payload['status'] ?? null) !== 'offline') {
                $update['ultimo_contacto'] = now();
            }

            DB::table('modulos')->where('id', $modulo->id)->update($update);
        } catch (\Throwable $e) {
            Log::warning('No se pudo actualizar telemetría de contacto MQTT.', [
                'modulo_id' => $modulo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
