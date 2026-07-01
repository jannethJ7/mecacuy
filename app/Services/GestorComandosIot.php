<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GestorComandosIot
{
    private const ACK_TIMEOUT_SEG_DEFAULT = 20;
    private const MAX_INTENTOS_DEFAULT = 3;

    /**
     * Prepara el siguiente comando que debe recibir el ESP32.
     *
     * Flujo robusto:
     * - pendiente  -> enviado
     * - enviado sin ACK dentro de la ventana -> reenviado con el mismo nonce
     * - enviado sin ACK y sin intentos restantes -> fallido
     * - pendiente/enviado vencido por expira_en -> expirado
     */
    public function prepararSiguiente(int $moduloId): array
    {
        $limpieza = $this->depurarModulo($moduloId);
        $now = now();
        $ackTimeoutSeg = $this->ackTimeoutSeg();
        $maxIntentos = $this->maxIntentos();
        $corteReintento = $now->copy()->subSeconds($ackTimeoutSeg);

        $resultado = [
            'ok' => true,
            'modulo_id' => $moduloId,
            'ack_timeout_seg' => $ackTimeoutSeg,
            'max_intentos' => $maxIntentos,
            'limpieza' => $limpieza,
            'entregado' => null,
        ];

        $cmd = DB::transaction(function () use ($moduloId, $now, $corteReintento, $maxIntentos, &$resultado) {
            $cmd = DB::table('comandos_iot')
                ->where('modulo_id', $moduloId)
                ->where('estado', 'pendiente')
                ->where(function ($q) use ($now) {
                    $q->whereNull('ejecutar_en')
                        ->orWhere('ejecutar_en', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('expira_en')
                        ->orWhere('expira_en', '>', $now);
                })
                ->orderByRaw('CASE WHEN ejecutar_en IS NULL THEN 0 ELSE 1 END')
                ->orderBy('ejecutar_en')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $esReintento = false;

            if (!$cmd) {
                $cmd = DB::table('comandos_iot')
                    ->where('modulo_id', $moduloId)
                    ->where('estado', 'enviado')
                    ->where('intentos', '<', $maxIntentos)
                    ->where(function ($q) use ($corteReintento) {
                        $q->whereNull('enviado_en')
                            ->orWhere('enviado_en', '<=', $corteReintento);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('expira_en')
                            ->orWhere('expira_en', '>', $now);
                    })
                    ->orderBy('enviado_en')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $esReintento = (bool) $cmd;
            }

            if (!$cmd) {
                return null;
            }

            $nuevoIntento = ((int) $cmd->intentos) + 1;

            DB::table('comandos_iot')
                ->where('id', $cmd->id)
                ->update([
                    'estado' => 'enviado',
                    'intentos' => $nuevoIntento,
                    'enviado_en' => $now,
                    'ultimo_error' => $esReintento
                        ? 'Reenviado automáticamente por falta de ACK del ESP32.'
                        : null,
                    'updated_at' => $now,
                ]);

            $cmd->estado = 'enviado';
            $cmd->intentos = $nuevoIntento;
            $cmd->enviado_en = $now->toDateTimeString();
            $cmd->ultimo_error = $esReintento
                ? 'Reenviado automáticamente por falta de ACK del ESP32.'
                : null;

            $resultado['entregado'] = [
                'id' => (int) $cmd->id,
                'nonce' => $cmd->nonce,
                'reintento' => $esReintento,
                'intento' => $nuevoIntento,
                'max_intentos' => $this->maxIntentos(),
            ];

            return $cmd;
        });

        return [
            'comando' => $cmd,
            'meta' => $resultado,
        ];
    }

    /**
     * Depura comandos vencidos o enviados sin ACK luego de agotar intentos.
     */
    public function depurarModulo(int $moduloId): array
    {
        $now = now();
        $ackTimeoutSeg = $this->ackTimeoutSeg();
        $maxIntentos = $this->maxIntentos();
        $corteFallo = $now->copy()->subSeconds($ackTimeoutSeg);

        $resultado = [
            'ok' => true,
            'modulo_id' => $moduloId,
            'expirados' => 0,
            'fallidos_por_ack' => 0,
            'alertas' => [],
        ];

        $expirados = DB::table('comandos_iot')
            ->where('modulo_id', $moduloId)
            ->whereIn('estado', ['pendiente', 'enviado'])
            ->whereNotNull('expira_en')
            ->where('expira_en', '<=', $now)
            ->get(['id', 'nonce', 'actuador_id', 'estado', 'intentos']);

        foreach ($expirados as $cmd) {
            DB::table('comandos_iot')
                ->where('id', $cmd->id)
                ->whereIn('estado', ['pendiente', 'enviado'])
                ->update([
                    'estado' => 'expirado',
                    'ultimo_error' => 'Comando expirado antes de ser confirmado por el ESP32.',
                    'updated_at' => $now,
                ]);

            $resultado['expirados']++;
            $resultado['alertas'][] = $this->notificarComandoFallido(
                $moduloId,
                $cmd->actuador_id ? (int) $cmd->actuador_id : null,
                $cmd->nonce,
                'Comando IoT expirado sin confirmación del ESP32.'
            );
        }

        $fallidos = DB::table('comandos_iot')
            ->where('modulo_id', $moduloId)
            ->where('estado', 'enviado')
            ->where('intentos', '>=', $maxIntentos)
            ->where(function ($q) use ($corteFallo) {
                $q->whereNull('enviado_en')
                    ->orWhere('enviado_en', '<=', $corteFallo);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expira_en')
                    ->orWhere('expira_en', '>', $now);
            })
            ->get(['id', 'nonce', 'actuador_id', 'intentos']);

        foreach ($fallidos as $cmd) {
            DB::table('comandos_iot')
                ->where('id', $cmd->id)
                ->where('estado', 'enviado')
                ->update([
                    'estado' => 'fallido',
                    'ultimo_error' => 'No se recibió ACK del ESP32 después de '.$cmd->intentos.' intento(s).',
                    'updated_at' => $now,
                ]);

            $resultado['fallidos_por_ack']++;
            $resultado['alertas'][] = $this->notificarComandoFallido(
                $moduloId,
                $cmd->actuador_id ? (int) $cmd->actuador_id : null,
                $cmd->nonce,
                'No se recibió ACK del ESP32 después de '.$cmd->intentos.' intento(s).'
            );
        }

        return $resultado;
    }

    public function depurarTodos(): array
    {
        $modulos = DB::table('modulos')
            ->where('habilitado', 1)
            ->orderBy('id')
            ->get(['id', 'codigo']);

        $resumen = [
            'ok' => true,
            'modulos' => $modulos->count(),
            'expirados' => 0,
            'fallidos_por_ack' => 0,
            'errores' => [],
        ];

        foreach ($modulos as $modulo) {
            try {
                $r = $this->depurarModulo((int) $modulo->id);
                $resumen['expirados'] += (int) ($r['expirados'] ?? 0);
                $resumen['fallidos_por_ack'] += (int) ($r['fallidos_por_ack'] ?? 0);
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

    public function registrarAck(int $moduloId, string $nonce, bool $ok, ?string $error = null): array
    {
        $cmd = DB::table('comandos_iot')
            ->where('modulo_id', $moduloId)
            ->where('nonce', $nonce)
            ->first();

        if (!$cmd) {
            return [
                'ok' => false,
                'estado' => 'no_encontrado',
                'mensaje' => 'No se encontró un comando con ese nonce para el módulo autenticado.',
                'nonce' => $nonce,
            ];
        }

        $now = now();
        $nuevoEstado = $ok ? 'confirmado' : 'fallido';
        $mensajeError = $ok ? null : ($error ?: 'Comando fallido reportado por el ESP32.');

        DB::table('comandos_iot')
            ->where('id', $cmd->id)
            ->update([
                'estado' => $nuevoEstado,
                'confirmado_en' => $ok ? $now : null,
                'ultimo_error' => $mensajeError,
                'updated_at' => $now,
            ]);

        $alertaComando = null;

        if (!$ok) {
            $alertaComando = $this->notificarComandoFallido(
                $moduloId,
                $cmd->actuador_id ? (int) $cmd->actuador_id : null,
                $nonce,
                $mensajeError
            );
        }

        return [
            'ok' => true,
            'estado' => $nuevoEstado,
            'comando_id' => (int) $cmd->id,
            'nonce' => $nonce,
            'intentos' => (int) $cmd->intentos,
            'alerta_comando' => $alertaComando,
        ];
    }

    public function formatoParaApi(?object $cmd): ?array
    {
        if (!$cmd) {
            return null;
        }

        return [
            'id' => (int) $cmd->id,
            'nonce' => $cmd->nonce,
            'tipo' => $cmd->tipo,
            'payload' => is_string($cmd->payload) ? json_decode($cmd->payload, true) : $cmd->payload,
            'ejecutar_en' => $cmd->ejecutar_en ?? null,
            'actuador_id' => $cmd->actuador_id,
            'intento' => (int) $cmd->intentos,
            'max_intentos' => $this->maxIntentos(),
            'ack_timeout_seg' => $this->ackTimeoutSeg(),
        ];
    }

    private function notificarComandoFallido(int $moduloId, ?int $actuadorId, string $nonce, string $error): array
    {
        try {
            return app(MotorAlertasAutomaticas::class)->registrarComandoFallido(
                $moduloId,
                $actuadorId,
                $nonce,
                $error
            );
        } catch (Throwable $e) {
            Log::error('Error al generar alerta por comando IoT fallido o expirado.', [
                'modulo_id' => $moduloId,
                'actuador_id' => $actuadorId,
                'nonce' => $nonce,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'No se pudo generar la alerta del comando fallido.',
            ];
        }
    }

    private function ackTimeoutSeg(): int
    {
        return max(5, min(3600, $this->configInt('iot_ack_timeout_seg', self::ACK_TIMEOUT_SEG_DEFAULT)));
    }

    private function maxIntentos(): int
    {
        return max(1, min(10, $this->configInt('iot_max_intentos', self::MAX_INTENTOS_DEFAULT)));
    }

    private function configInt(string $clave, int $default): int
    {
        $valor = DB::table('config_sistema')->where('clave', $clave)->value('valor');

        if ($valor === null) {
            return $default;
        }

        $decoded = json_decode($valor, true);

        return is_numeric($decoded) ? (int) $decoded : $default;
    }
}
