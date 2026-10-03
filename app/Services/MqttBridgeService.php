<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MqttBridgeService
{
    public function habilitado(): bool
    {
        return (bool) config('mqtt.enabled')
            && filled(config('mqtt.emqx_api_base'))
            && filled(config('mqtt.emqx_api_key'))
            && filled(config('mqtt.emqx_api_secret'));
    }

    public function topic(string $moduloCodigo, string $canal): string
    {
        $prefix = trim((string) config('mqtt.topic_prefix', 'mecacuy'), '/');
        return $prefix.'/'.trim($moduloCodigo, '/').'/'.trim($canal, '/');
    }

    public function publicar(string $topic, array $payload, int $qos = 1, bool $retain = false): array
    {
        if (!$this->habilitado()) {
            return ['ok' => false, 'estado' => 'deshabilitado'];
        }

        try {
            $response = Http::withBasicAuth(
                    (string) config('mqtt.emqx_api_key'),
                    (string) config('mqtt.emqx_api_secret')
                )
                ->acceptJson()
                ->timeout((int) config('mqtt.http_timeout', 4))
                ->post(config('mqtt.emqx_api_base').'/publish', [
                    'topic' => $topic,
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'payload_encoding' => 'plain',
                    'qos' => $qos,
                    'retain' => $retain,
                ]);

            if ($response->status() === 200) {
                return ['ok' => true, 'estado' => 'publicado', 'status' => 200];
            }

            // EMQX responde 202 cuando no existe un suscriptor coincidente.
            // En ese caso dejamos el comando pendiente para que REST /sync actúe
            // como canal de respaldo.
            return [
                'ok' => false,
                'estado' => $response->status() === 202 ? 'sin_suscriptor' : 'error_http',
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ];
        } catch (Throwable $e) {
            Log::warning('No se pudo publicar en EMQX; REST quedará como respaldo.', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'estado' => 'excepcion', 'error' => $e->getMessage()];
        }
    }

    public function despacharComando(int $comandoId): array
    {
        if (!$this->habilitado()) {
            return ['ok' => false, 'estado' => 'deshabilitado'];
        }

        $cmd = DB::table('comandos_iot as c')
            ->join('modulos as m', 'm.id', '=', 'c.modulo_id')
            ->where('c.id', $comandoId)
            ->select('c.*', 'm.codigo as modulo_codigo')
            ->first();

        if (!$cmd || $cmd->estado !== 'pendiente') {
            return ['ok' => false, 'estado' => 'no_despachable'];
        }

        if ($cmd->ejecutar_en && now()->lt(Carbon::parse($cmd->ejecutar_en))) {
            return ['ok' => false, 'estado' => 'programado_para_futuro'];
        }

        if ($cmd->expira_en && now()->gte(Carbon::parse($cmd->expira_en))) {
            return ['ok' => false, 'estado' => 'expirado'];
        }

        $payload = app(GestorComandosIot::class)->formatoParaApi($cmd);
        $resultado = $this->publicar(
            $this->topic($cmd->modulo_codigo, 'command'),
            $payload,
            1,
            false
        );

        if (!($resultado['ok'] ?? false)) {
            DB::table('comandos_iot')->where('id', $comandoId)->update([
                'ultimo_error' => 'MQTT no disponible ('.($resultado['estado'] ?? 'error').'). Se usará REST /sync.',
                'updated_at' => now(),
            ]);
            return $resultado;
        }

        DB::table('comandos_iot')
            ->where('id', $comandoId)
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'enviado',
                'intentos' => DB::raw('intentos + 1'),
                'enviado_en' => now(),
                'ultimo_error' => null,
                'updated_at' => now(),
            ]);

        return $resultado + ['comando_id' => $comandoId];
    }
}
