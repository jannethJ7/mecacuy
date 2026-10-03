<?php

namespace App\Http\Controllers\Iot\V1;

use App\Services\GestorComandosIot;
use App\Services\ProcesadorAckIot;
use App\Services\ProcesadorTelemetriaIot;
use App\Services\MotorAlertasAutomaticas;
use App\Services\MotorProgramaciones;
use App\Services\MotorReglasAutomaticas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Validation\ValidationException;

class ControladorIot
{
    public function guardarLecturas(Request $request)
    {
        $modulo = $request->attributes->get('iot_modulo');

        $data = $request->validate([
            'lecturas' => ['required', 'array', 'min:1'],
            'lecturas.*.codigo' => ['nullable', 'string', 'max:40'],
            'lecturas.*.sensor' => ['nullable', 'string', 'max:40'],
            'lecturas.*.valor' => ['required', 'numeric'],
            'lecturas.*.medido_en' => ['nullable', 'date'],
            'lecturas.*.calidad' => ['nullable', 'in:ok,dudoso,error'],
            'lecturas.*.raw' => ['nullable', 'array'],
        ]);

        $erroresCodigo = [];
        foreach ($data['lecturas'] as $i => $l) {
            if (empty($l['codigo']) && empty($l['sensor'])) {
                $erroresCodigo["lecturas.$i.codigo"] = 'Debe enviar codigo o sensor para identificar la lectura.';
            }
        }

        if (!empty($erroresCodigo)) {
            throw ValidationException::withMessages($erroresCodigo);
        }

        $resultado = app(ProcesadorTelemetriaIot::class)->procesar($modulo, $data['lecturas']);

        return response()->json($resultado);
    }

    /**
     * GET /api/iot/v1/sync
     * Devuelve config + sensores/actuadores + 1 comando pendiente 
     */
    public function sync(Request $request)
    {
        $modulo = $request->attributes->get('iot_modulo');

        $modoGlobal = $this->getConfig('modo_global', 'manual');
        $staleMin   = (int) $this->getConfig('stale_min', 10);

        $resultadoProgramaciones = null;

        try {
            $resultadoProgramaciones = app(MotorProgramaciones::class)->evaluarModulo((int) $modulo->id);
        } catch (Throwable $e) {
            Log::error('Error al evaluar programaciones antes de sincronizar comandos IoT.', [
                'modulo_id' => $modulo->id,
                'error' => $e->getMessage(),
            ]);

            $resultadoProgramaciones = [
                'ok' => false,
                'error' => 'No se pudieron evaluar las programaciones.',
            ];
        }

        $sensores = DB::table('sensores')
            ->where('modulo_id', $modulo->id)
            ->where('activo', 1)
            ->orderBy('id')
            ->get(['codigo','tipo','unidad','gpio_pin','valor_actual','valor_actual_en','meta']);

        $actuadores = DB::table('actuadores')
            ->where('modulo_id', $modulo->id)
            ->where('activo', 1)
            ->orderBy('id')
            ->get(['id','codigo','tipo','gpio_pin','estado_deseado','invertido','meta']);

        $resultadoComandos = null;
        $cmd = null;

        try {
            $entrega = app(GestorComandosIot::class)->prepararSiguiente((int) $modulo->id);
            $cmd = $entrega['comando'] ?? null;
            $resultadoComandos = $entrega['meta'] ?? null;
        } catch (Throwable $e) {
            Log::error('Error al preparar comando IoT para sincronización.', [
                'modulo_id' => $modulo->id,
                'error' => $e->getMessage(),
            ]);

            $resultadoComandos = [
                'ok' => false,
                'error' => 'No se pudo preparar el siguiente comando IoT.',
            ];
        }

        /*
         * IMPORTANTE PARA ESP32:
         * La respuesta normal de /sync debe ser ligera. Antes se devolvían los
         * detalles completos de programaciones y limpieza de comandos. Eso hacía
         * crecer mucho el JSON, podía saturar ArduinoJson/NVS y el firmware se
         * quedaba usando configuración cacheada sin avanzar al ciclo normal.
         *
         * Si necesitas depurar desde navegador/Postman, usa:
         * /api/iot/v1/sync?debug=1
         */
        $respuesta = [
            'ok' => true,
            'config' => [
                'modulo' => [
                    'codigo' => $modulo->codigo,
                    'uid'    => $modulo->uid,
                    'zona_horaria' => $modulo->zona_horaria,
                ],
                'modo_global'   => $modoGlobal,
                'stale_min'     => $staleMin,
                'iot_ack_timeout_seg' => (int) $this->getConfig('iot_ack_timeout_seg', 20),
                'iot_max_intentos' => (int) $this->getConfig('iot_max_intentos', 3),
                'hora_servidor' => now()->toDateTimeString(),
            ],
            'sensores' => $sensores->map(function ($s) {
                return [
                    'codigo' => $s->codigo,
                    'tipo' => $s->tipo,
                    'unidad' => $s->unidad,
                    'gpio_pin' => is_null($s->gpio_pin) ? null : (int) $s->gpio_pin,
                    'activo' => true,
                    'valor_actual' => $s->valor_actual,
                    'valor_actual_en' => $s->valor_actual_en,
                    'meta' => $s->meta ? json_decode($s->meta, true) : null,
                ];
            })->values(),
            'actuadores' => $actuadores->map(function ($a) {
                return [
                    'codigo' => $a->codigo,
                    'tipo' => $a->tipo,
                    'gpio_pin' => is_null($a->gpio_pin) ? null : (int) $a->gpio_pin,
                    'invertido' => (bool)$a->invertido,
                    'estado_deseado' => $a->estado_deseado ? json_decode($a->estado_deseado, true) : null,
                    'meta' => $a->meta ? json_decode($a->meta, true) : null,
                ];
            })->values(),
            'comando' => app(GestorComandosIot::class)->formatoParaApi($cmd),
        ];

        if ($request->boolean('debug')) {
            $respuesta['programaciones'] = $resultadoProgramaciones;
            $respuesta['comandos'] = $resultadoComandos;
        }

        return response()->json($respuesta, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/iot/v1/ack
     * Body:
     * {
     *   "nonce":"....",
     *   "ok":true,
     *   "error": null,
     *   "reportados":[
     *     {"actuador":"D_FAN","estado":{"on":true}}
     *   ]
     * }
     */
    public function ack(Request $request)
    {
        $modulo = $request->attributes->get('iot_modulo');

        $data = $request->validate([
            'nonce' => ['required', 'string', 'max:64'],
            'ok' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:255'],
            'reportados' => ['nullable', 'array'],
            'reportados.*.actuador' => ['required_with:reportados', 'string', 'max:40'],
            'reportados.*.estado' => ['required_with:reportados', 'array'],
        ]);

        $resultado = app(ProcesadorAckIot::class)->procesar(
            $modulo,
            $data['nonce'],
            (bool) $data['ok'],
            $data['error'] ?? null,
            $data['reportados'] ?? []
        );

        return response()->json($resultado);
    }

    private function getConfig(string $clave, $default = null)
    {
        $row = DB::table('config_sistema')->where('clave', $clave)->first();
        if (!$row) return $default;

        $val = json_decode($row->valor, true);
        return $val ?? $default;
    }
}