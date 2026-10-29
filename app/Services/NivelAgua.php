<?php

namespace App\Services;

use App\Models\Actuador;
use App\Models\Modulo;
use App\Models\Sensor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NivelAgua
{
    public const CODIGO = 'S_NIVEL';
    public const TIPO = 'nivel_agua';
    public const DRIVER = 'nivel_agua_fijo';
    public const PERFIL = 'clavel_4_niveles';
    public const PINES = [25 => 23, 50 => 27, 75 => 34, 100 => 35];

    public static function esSensor(object $sensor): bool
    {
        $meta = is_array($sensor->meta) ? $sensor->meta : (json_decode($sensor->meta ?? '{}', true) ?: []);
        return $sensor->tipo === self::TIPO && ($meta['driver'] ?? null) === self::DRIVER;
    }

    public static function nivelIndividual(string $codigo): ?int
    {
        return preg_match('/^(?:S_NIVEL_|NIVEL_|S_WATER_|N_)(25|50|75|100)$/i', $codigo, $m) ? (int) $m[1] : null;
    }

    public static function metadatos(): array
    {
        return ['driver' => self::DRIVER, 'perfil' => self::PERFIL, 'pines' => self::PINES,
            'agua_activa' => 'HIGH', 'muestras' => 15, 'intervalo_muestra_ms' => 10];
    }

    public static function tienePerfil(int $moduloId): bool
    {
        return Sensor::where('modulo_id', $moduloId)->where('tipo', self::TIPO)->where('activo', true)->exists();
    }

    public static function validarPinIndividual(int $moduloId, ?int $gpio): void
    {
        if ($gpio !== null && in_array($gpio, self::PINES, true) && self::tienePerfil($moduloId)) {
            throw ValidationException::withMessages(['gpio_pin' => 'Este GPIO está reservado por el conjunto de nivel de agua. No se asigna por separado.']);
        }
    }

    public function validarConjunto(int $moduloId, ?int $sensorId = null): void
    {
        $conflictos = Actuador::where('modulo_id', $moduloId)->where('activo', true)->whereIn('gpio_pin', self::PINES)->pluck('codigo')->all();
        foreach (Sensor::where('modulo_id', $moduloId)->where('activo', true)->whereIn('gpio_pin', self::PINES)->get() as $sensor) {
            if ($sensor->id !== $sensorId && self::nivelIndividual($sensor->codigo) === null) {
                $conflictos[] = $sensor->codigo;
            }
        }
        if ($conflictos) {
            throw ValidationException::withMessages(['tipo' => 'Los pines fijos del tanque están ocupados por: '.implode(', ', $conflictos).'. No se reasignaron automáticamente.']);
        }
    }

    /** Conserva las lecturas antiguas; las cuatro entradas dejan de ser configuraciones separadas. */
    public function consolidar(Sensor $conjunto): array
    {
        $resumen = ['individuales_archivados' => 0, 'reglas_convertidas' => [], 'reglas_por_revisar' => []];
        $individuales = Sensor::where('modulo_id', $conjunto->modulo_id)->where('id', '!=', $conjunto->id)->get()
            ->filter(fn ($s) => self::nivelIndividual($s->codigo) !== null);
        foreach ($individuales as $sensor) {
            $nivel = self::nivelIndividual($sensor->codigo);
            $sensor->update(['activo' => false, 'meta' => array_replace($sensor->meta ?? [], ['nivel_agua_reemplazado_por' => self::CODIGO])]);
            ++$resumen['individuales_archivados'];
            foreach (DB::table('reglas_automaticas')->where('sensor_id', $sensor->id)->get() as $regla) {
                // Solo se convierte una condición binaria inequívoca y sin histéresis.
                $bajo = $regla->objetivo_min !== null && $regla->objetivo_max === null && (float) $regla->objetivo_min > 0 && (float) $regla->objetivo_min <= 1;
                $alto = $regla->objetivo_max !== null && $regla->objetivo_min === null && (float) $regla->objetivo_max >= 0 && (float) $regla->objetivo_max < 1;
                if (($bajo || $alto) && (float) $regla->histeresis === 0.0) {
                    DB::table('reglas_automaticas')->where('id', $regla->id)->update([
                        'sensor_id' => $conjunto->id,
                        'objetivo_min' => $bajo ? $nivel : null,
                        'objetivo_max' => $alto ? $nivel - 25 : null,
                        'updated_at' => now(),
                    ]);
                    DB::table('estado_reglas')->where('regla_id', $regla->id)->delete();
                    $resumen['reglas_convertidas'][] = $regla->id;
                } else {
                    DB::table('reglas_automaticas')->where('id', $regla->id)->update(['activo' => false, 'updated_at' => now()]);
                    $resumen['reglas_por_revisar'][] = $regla->id;
                }
            }
            DB::table('reglas_alerta')->where('sensor_id', $sensor->id)->update(['activo' => false, 'updated_at' => now()]);
        }
        return $resumen;
    }

    public function configurar(Modulo $modulo): array
    {
        return DB::transaction(function () use ($modulo) {
            $sensor = $modulo->sensores()->where('codigo', self::CODIGO)->first();
            $this->validarConjunto($modulo->id, $sensor?->id);
            if (!$sensor) {
                $sensor = new Sensor(['modulo_id' => $modulo->id, 'codigo' => self::CODIGO, 'nombre' => 'Nivel de agua del tanque']);
            }
            if (!self::esSensor($sensor)) {
                $sensor->valor_actual = null;
                $sensor->valor_actual_en = null;
            }
            $sensor->fill(['tipo' => self::TIPO, 'unidad' => '%', 'activo' => true, 'gpio_pin' => null, 'meta' => self::metadatos()])->save();
            return ['sensor' => self::CODIGO, 'perfil' => self::PERFIL] + $this->consolidar($sensor);
        });
    }
}
