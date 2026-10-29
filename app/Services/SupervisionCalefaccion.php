<?php

namespace App\Services;

use App\Models\Actuador;
use App\Models\Sensor;
use Carbon\Carbon;

/** Estado eléctrico independiente de estado_reportado (que solamente confirma el GPIO). */
class SupervisionCalefaccion
{
    public const TIPO = 'verificacion_calefaccion';
    public const DRIVER = 'acs712_ac';

    public static function esSensor(object $sensor): bool
    {
        return $sensor->tipo === self::TIPO;
    }

    public static function predeterminados(string $actuador): array
    {
        return [
            'driver' => self::DRIVER,
            'actuador_codigo' => $actuador,
            // Valores iniciales para acondicionamiento a 3,3 V. NO son una calibración.
            'calibrado' => false,
            'umbral_encendido_mv' => 50,
            'umbral_apagado_mv' => 25,
            'cero_mv' => 1667,
            'cero_min_mv' => 1300,
            'cero_max_mv' => 2000,
            'espera_seg' => 3,
            'vigencia_seg' => 30,
            'revision' => random_int(1, 2147483647),
        ];
    }

    public function paraActuador(Actuador $actuador): array
    {
        $sensor = $actuador->modulo->sensores->first(fn ($s) =>
            self::esSensor($s) && data_get($s->meta, 'actuador_codigo') === $actuador->codigo
        );

        return $sensor ? $this->paraSensor($sensor, $actuador) : $this->resultado('no_configurada', 'Sin verificación eléctrica configurada');
    }

    public function paraSensor(Sensor $sensor, ?Actuador $actuador = null): array
    {
        $meta = $sensor->meta ?? [];
        $base = ['sensor_id' => $sensor->id, 'sensor_codigo' => $sensor->codigo];
        if (!$sensor->activo) {
            return $base + $this->resultado('sin_verificar', 'Verificación deshabilitada');
        }
        if (($meta['calibrado'] ?? false) !== true) {
            return $base + $this->resultado('sin_calibrar', 'Pendiente de instalación y calibración');
        }

        $actuador ??= $sensor->modulo->actuadores->firstWhere('codigo', $meta['actuador_codigo'] ?? '');
        if (!$actuador || !$actuador->activo) {
            return $base + $this->resultado('sin_verificar', 'Actuador de calefacción no disponible');
        }

        $lectura = $sensor->lecturas()->orderByDesc('medido_en')->orderByDesc('id')->first();
        if (!$lectura) {
            return $base + $this->resultado('sin_verificar', 'Sin medición de verificación');
        }

        $base['medido_en'] = $lectura->medido_en?->toIso8601String();
        $vigencia = max(15, min(120, (int) ($meta['vigencia_seg'] ?? 30)));
        $limite = now()->subSeconds($vigencia);
        if (!$lectura->medido_en || !$lectura->recibido_en || $lectura->medido_en->lt($limite)
            || $lectura->recibido_en->lt($limite) || $lectura->medido_en->gt(now()->addSeconds(5))) {
            return $base + $this->resultado('sin_verificar', 'Sin datos recientes de verificación');
        }

        $raw = $lectura->raw ?? [];
        if ($lectura->calidad !== 'ok' || ($raw['verificacion_valida'] ?? false) !== true
            || ($raw['driver'] ?? '') !== self::DRIVER
            || !isset($meta['revision'], $raw['revision'])
            || (int) $raw['revision'] !== (int) $meta['revision']
            || !isset($raw['edad_muestra_ms']) || !is_numeric($raw['edad_muestra_ms'])
            || (float) $raw['edad_muestra_ms'] < 0 || (float) $raw['edad_muestra_ms'] > 1500
            || !in_array((string) $lectura->valor, ['0', '1', '0.000', '1.000'], true)) {
            return $base + $this->resultado('sin_verificar', 'Medición de verificación no válida');
        }

        $consumo = (float) $lectura->valor === 1.0;
        $base['consumo_detectado'] = $consumo;
        $orden = data_get($actuador->estado_deseado, 'on');
        if (!is_bool($orden) && !in_array($orden, [0, 1], true)) {
            return $base + $this->resultado('sin_verificar', 'Sin orden de calefacción definida');
        }
        $base['orden_encendido'] = (bool) $orden;
        $espera = max(2, min(30, (int) ($meta['espera_seg'] ?? 3)));
        if ($actuador->cambiado_en && $lectura->medido_en->lt($actuador->cambiado_en->copy()->addSeconds($espera))) {
            return $base + $this->resultado('pendiente', 'Esperando verificación después de la orden');
        }

        if (!(bool) $orden && $consumo) {
            return $base + $this->resultado('no_apaga', 'Falla de apagado: continúa consumiendo', 'critico');
        }
        if ((bool) $orden && !$consumo) {
            return $base + $this->resultado('no_enciende', 'Falla de encendido: no se detecta consumo', 'advertencia');
        }

        return $base + $this->resultado($consumo ? 'encendida' : 'apagada',
            $consumo ? 'Encendido eléctrico verificado' : 'Apagado eléctrico verificado', 'info', true);
    }

    public static function textoLectura(mixed $valor, ?string $calidad = 'ok'): string
    {
        if ($calidad !== 'ok' || !is_numeric($valor)) {
            return 'Sin verificación válida';
        }
        return match ((float) $valor) {
            1.0 => 'Consumo detectado',
            0.0 => 'Sin consumo detectable',
            default => 'Sin verificación válida',
        };
    }

    private function resultado(string $codigo, string $etiqueta, string $severidad = 'advertencia', bool $verificado = false): array
    {
        return compact('codigo', 'etiqueta', 'severidad', 'verificado');
    }
}
