<?php

namespace App\Services;

use App\Models\Lectura;
use App\Models\Sensor;
use Illuminate\Support\Collection;

class SeriesLecturasService
{
    /**
     * Series estándar que el panel debe mostrar como tendencia ambiental.
     */
    public function definicionesBase(): array
    {
        return [
            'temperatura' => [
                'titulo' => 'Temperatura',
                'subtitulo' => 'Ambiente interno',
                'unidad' => '°C',
                'decimales' => 1,
                'codigos' => ['S_TEMP', 'TEMP', 'TEMPERATURA'],
                'tipos' => ['temperatura'],
                'icono' => 'ri-temp-hot-line',
            ],
            'humedad' => [
                'titulo' => 'Humedad relativa',
                'subtitulo' => 'Condición de cama y ambiente',
                'unidad' => '%',
                'decimales' => 0,
                'codigos' => ['S_HR', 'S_HUM', 'HUMEDAD'],
                'tipos' => ['humedad'],
                'icono' => 'ri-water-percent-line',
            ],
            'aire' => [
                'titulo' => 'Calidad de aire',
                'subtitulo' => 'Amoniaco / gases',
                'unidad' => 'ppm',
                'decimales' => 0,
                'codigos' => ['S_NH3', 'S_AMONIACO', 'NH3', 'S_AIR'],
                'tipos' => ['calidad_aire', 'aire', 'amoniaco'],
                'icono' => 'ri-windy-line',
            ],
            'agua' => [
                'titulo' => 'Nivel de agua',
                'subtitulo' => 'Depósito / bebedero',
                'unidad' => '%',
                'decimales' => 0,
                'codigos' => ['S_NIVEL', 'S_AGUA', 'NIVEL_AGUA', 'S_WATER'],
                'tipos' => ['nivel_agua', 'agua'],
                'icono' => 'ri-drop-line',
            ],
        ];
    }

    /**
     * Genera series por módulo usando los sensores ya cargados del módulo.
     */
    public function porSensores(Collection $sensores, int $limite = 24): array
    {
        $series = [];

        foreach ($this->definicionesBase() as $clave => $definicion) {
            $sensor = $this->sensorDeColeccion($sensores, $definicion);
            $lecturas = collect();

            if ($sensor) {
                $lecturas = $sensor->lecturas()
                    ->latest('medido_en')
                    ->limit($limite)
                    ->get()
                    ->sortBy('medido_en')
                    ->values();
            }

            $series[$clave] = $this->formatearSerie($definicion, $lecturas, $sensor);
        }

        return $series;
    }

    /**
     * Genera series para dashboard tomando las últimas lecturas reales de todos los módulos.
     */
    public function paraDashboard(int $limite = 30): array
    {
        $series = [];

        foreach ($this->definicionesBase() as $clave => $definicion) {
            $sensores = $this->querySensoresPorDefinicion($definicion)
                ->with(['modulo', 'ultimaLectura'])
                ->orderBy('modulo_id')
                ->orderBy('codigo')
                ->get();

            $lecturas = collect();

            if ($sensores->isNotEmpty()) {
                $lecturas = Lectura::query()
                    ->with(['sensor.modulo'])
                    ->whereIn('sensor_id', $sensores->pluck('id'))
                    ->latest('medido_en')
                    ->limit($limite)
                    ->get()
                    ->sortBy('medido_en')
                    ->values();
            }

            $serie = $this->formatearSerie($definicion, $lecturas, $sensores->first());
            $serie['sensores_count'] = $sensores->count();
            $serie['modulos_count'] = $sensores->pluck('modulo_id')->unique()->count();
            $serie['labels'] = $lecturas->map(function ($lectura) {
                $hora = optional($lectura->medido_en)->format('H:i') ?: '--:--';
                $modulo = $lectura->sensor?->modulo?->codigo ?: 'Módulo';

                return $hora . ' · ' . $modulo;
            })->values()->all();
            $serie['sensor_actual'] = $lecturas->last()?->sensor;

            $series[$clave] = $serie;
        }

        return $series;
    }

    private function querySensoresPorDefinicion(array $definicion)
    {
        $codigos = array_map('strtoupper', $definicion['codigos'] ?? []);
        $tipos = array_map('strtolower', $definicion['tipos'] ?? []);

        return Sensor::query()
            ->where('activo', true)
            ->where(function ($query) use ($codigos, $tipos) {
                foreach ($codigos as $index => $codigo) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('UPPER(codigo) = ?', [$codigo]);
                }

                foreach ($tipos as $tipo) {
                    $query->orWhereRaw('LOWER(tipo) = ?', [$tipo]);
                }
            });
    }

    private function sensorDeColeccion(Collection $sensores, array $definicion): ?Sensor
    {
        $codigos = array_map('strtoupper', $definicion['codigos'] ?? []);
        $tipos = array_map('strtolower', $definicion['tipos'] ?? []);

        return $sensores->first(function ($sensor) use ($codigos, $tipos) {
            $codigo = strtoupper((string) $sensor->codigo);
            $tipo = strtolower((string) $sensor->tipo);

            return in_array($codigo, $codigos, true) || in_array($tipo, $tipos, true);
        });
    }

    private function formatearSerie(array $definicion, Collection $lecturas, ?Sensor $sensor = null): array
    {
        $valores = $lecturas->pluck('valor')
            ->map(fn ($valor) => (float) $valor)
            ->values();

        $min = $valores->count() ? $valores->min() : null;
        $max = $valores->count() ? $valores->max() : null;
        $avg = $valores->count() ? $valores->avg() : null;
        $rango = ($min !== null && $max !== null && $max > $min) ? ($max - $min) : null;
        $decimales = (int) ($definicion['decimales'] ?? 1);
        $unidad = $sensor?->unidad ?: ($definicion['unidad'] ?? '');
        $ultima = $lecturas->last();

        return [
            'titulo' => $definicion['titulo'] ?? 'Lectura',
            'subtitulo' => $definicion['subtitulo'] ?? null,
            'unidad' => $unidad,
            'decimales' => $decimales,
            'icono' => $definicion['icono'] ?? 'ri-line-chart-line',
            'sensor' => $sensor,
            'actual' => $ultima?->valor ?? $sensor?->ultimaLectura?->valor,
            'ultima_en' => $ultima?->medido_en ?? $sensor?->ultimaLectura?->medido_en,
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'values' => $valores->all(),
            'labels' => $lecturas->map(fn ($lectura) => optional($lectura->medido_en)->format('H:i') ?: '--:--')->values()->all(),
            'puntos' => $lecturas->map(function ($lectura) use ($min, $rango) {
                $valor = (float) $lectura->valor;

                return [
                    'valor' => $valor,
                    'porcentaje' => $rango ? max(8, min(100, (($valor - $min) / $rango) * 92 + 8)) : 55,
                    'hora' => optional($lectura->medido_en)->format('H:i') ?: '--:--',
                    'modulo' => $lectura->sensor?->modulo?->codigo,
                ];
            })->all(),
            'cantidad' => $lecturas->count(),
        ];
    }
}
