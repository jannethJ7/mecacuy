<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Actuacion;
use App\Models\Alerta;
use App\Models\Lectura;
use App\Models\Modulo;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReporteLecturaController extends Controller
{
    public function index(Request $request): View
    {
        $modulos = Modulo::orderBy('codigo')->get();

        $moduloId = $request->integer('modulo_id') ?: optional($modulos->first())->id;
        $moduloSeleccionado = $modulos->firstWhere('id', $moduloId);

        $desde = $this->dateStart($request->input('desde'));
        $hasta = $this->dateEnd($request->input('hasta'));

        $sensores = Sensor::with('modulo')
            ->when($moduloId, fn (Builder $q) => $q->where('modulo_id', $moduloId))
            ->orderByRaw("FIELD(codigo, 'S_TEMP', 'S_HR', 'S_AIR', 'S_HSUELO', 'S_LUZ', 'S_NIVEL_25', 'S_NIVEL_50', 'S_NIVEL_75', 'S_NIVEL_100')")
            ->orderBy('codigo')
            ->get();

        $sensorId = $request->integer('sensor_id');
        $sensorSeleccionado = $sensores->firstWhere('id', $sensorId)
            ?: $this->sensorPrioritario($sensores);

        $lecturas = collect();
        if ($sensorSeleccionado) {
            $lecturas = Lectura::with('sensor.modulo')
                ->where('sensor_id', $sensorSeleccionado->id)
                ->when($desde, fn (Builder $q) => $q->where('medido_en', '>=', $desde))
                ->when($hasta, fn (Builder $q) => $q->where('medido_en', '<=', $hasta))
                ->latest('medido_en')
                ->limit(300)
                ->get();
        }

        $resumenSensores = $this->resumenPorSensores($sensores, $desde, $hasta);
        $rango = $this->rangoSensor($sensorSeleccionado);
        $kpis = $this->kpisSensor($lecturas, $rango);
        $chart = $this->chartData($lecturas);

        $alertasResumen = $this->resumenAlertas($moduloId, $desde, $hasta);
        $actuacionesResumen = $this->resumenActuaciones($moduloId, $desde, $hasta);
        $conclusion = $this->conclusion($sensorSeleccionado, $kpis, $rango, $alertasResumen, $actuacionesResumen);

        $ultimasLecturas = $lecturas->take(18);

        return view('panel.reportes.lecturas', compact(
            'modulos',
            'moduloId',
            'moduloSeleccionado',
            'sensores',
            'sensorSeleccionado',
            'lecturas',
            'resumenSensores',
            'rango',
            'kpis',
            'chart',
            'alertasResumen',
            'actuacionesResumen',
            'conclusion',
            'ultimasLecturas'
        ));
    }

    private function dateStart(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateEnd(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function sensorPrioritario(Collection $sensores): ?Sensor
    {
        $orden = ['S_TEMP', 'S_HR', 'S_AIR', 'S_HSUELO', 'S_LUZ', 'S_NIVEL_25', 'S_NIVEL_50', 'S_NIVEL_75', 'S_NIVEL_100'];

        foreach ($orden as $codigo) {
            $sensor = $sensores->firstWhere('codigo', $codigo);
            if ($sensor) {
                return $sensor;
            }
        }

        return $sensores->first();
    }

    private function resumenPorSensores(Collection $sensores, ?Carbon $desde, ?Carbon $hasta): Collection
    {
        return $sensores->map(function (Sensor $sensor) use ($desde, $hasta) {
            $query = Lectura::where('sensor_id', $sensor->id)
                ->when($desde, fn (Builder $q) => $q->where('medido_en', '>=', $desde))
                ->when($hasta, fn (Builder $q) => $q->where('medido_en', '<=', $hasta));

            $stats = (clone $query)
                ->selectRaw('COUNT(*) as total, AVG(valor) as promedio, MIN(valor) as minimo, MAX(valor) as maximo')
                ->first();

            $ultima = (clone $query)->latest('medido_en')->first();
            $rango = $this->rangoSensor($sensor);

            return [
                'sensor' => $sensor,
                'total' => (int) ($stats->total ?? 0),
                'promedio' => is_null($stats->promedio) ? null : (float) $stats->promedio,
                'minimo' => is_null($stats->minimo) ? null : (float) $stats->minimo,
                'maximo' => is_null($stats->maximo) ? null : (float) $stats->maximo,
                'ultima' => $ultima,
                'rango' => $rango,
                'estado' => $ultima ? $this->estadoValor((float) $ultima->valor, $rango) : 'sin_datos',
            ];
        });
    }

    private function kpisSensor(Collection $lecturas, array $rango): array
    {
        $valores = $lecturas->pluck('valor')->map(fn ($v) => (float) $v);
        $total = $valores->count();
        $enRango = 0;

        if ($total > 0 && $rango['tipo'] !== 'ninguno') {
            $enRango = $valores->filter(fn ($valor) => $this->estadoValor($valor, $rango) === 'ok')->count();
        }

        return [
            'total' => $total,
            'promedio' => $total ? $valores->avg() : null,
            'maximo' => $total ? $valores->max() : null,
            'minimo' => $total ? $valores->min() : null,
            'ultimo' => optional($lecturas->first())->valor,
            'ultimo_en' => optional($lecturas->first())->medido_en,
            'en_rango' => $enRango,
            'en_rango_pct' => $total && $rango['tipo'] !== 'ninguno' ? round(($enRango / $total) * 100, 1) : null,
        ];
    }

    private function chartData(Collection $lecturas): array
    {
        $serie = $lecturas->take(120)->reverse()->values();

        return [
            'labels' => $serie->map(fn (Lectura $l) => optional($l->medido_en)->format('H:i') ?: optional($l->created_at)->format('H:i'))->values(),
            'values' => $serie->map(fn (Lectura $l) => round((float) $l->valor, 3))->values(),
        ];
    }

    private function resumenAlertas(?int $moduloId, ?Carbon $desde, ?Carbon $hasta): array
    {
        $query = Alerta::query()
            ->when($moduloId, fn (Builder $q) => $q->where('modulo_id', $moduloId))
            ->when($desde, fn (Builder $q) => $q->where('created_at', '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->where('created_at', '<=', $hasta));

        return [
            'total' => (clone $query)->count(),
            'abiertas' => (clone $query)->where('estado', 'abierta')->count(),
            'criticas' => (clone $query)->where('severidad', 'critico')->count(),
            'advertencias' => (clone $query)->where('severidad', 'advertencia')->count(),
            'recientes' => (clone $query)->latest()->limit(5)->get(),
        ];
    }

    private function resumenActuaciones(?int $moduloId, ?Carbon $desde, ?Carbon $hasta): array
    {
        $query = Actuacion::with('actuador')
            ->when($moduloId, fn (Builder $q) => $q->where('modulo_id', $moduloId))
            ->when($desde, fn (Builder $q) => $q->where('ejecutado_en', '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->where('ejecutado_en', '<=', $hasta));

        return [
            'total' => (clone $query)->count(),
            'automaticas' => (clone $query)->where('origen', 'automatico')->count(),
            'manuales' => (clone $query)->where('origen', 'manual')->count(),
            'programadas' => (clone $query)->where('origen', 'programacion')->count(),
            'recientes' => (clone $query)->latest('ejecutado_en')->limit(8)->get(),
        ];
    }

    private function rangoSensor(?Sensor $sensor): array
    {
        if (!$sensor) {
            return ['tipo' => 'ninguno', 'min' => null, 'max' => null, 'texto' => 'Sin sensor seleccionado', 'unidad' => ''];
        }

        $codigo = strtoupper($sensor->codigo);
        $tipo = mb_strtolower((string) $sensor->tipo);
        $unidad = $sensor->unidad ?: '';

        if (str_contains($codigo, 'TEMP') || str_contains($tipo, 'temperatura')) {
            return ['tipo' => 'rango', 'min' => 18, 'max' => 24, 'texto' => 'Rango operativo 18–24 °C', 'unidad' => $unidad ?: '°C'];
        }

        if (str_contains($codigo, 'HR') || str_contains($tipo, 'humedad')) {
            return ['tipo' => 'rango', 'min' => 45, 'max' => 70, 'texto' => 'Rango recomendado 45–70 %', 'unidad' => $unidad ?: '%'];
        }

        if (str_contains($codigo, 'AIR') || str_contains($tipo, 'aire')) {
            return ['tipo' => 'max', 'min' => null, 'max' => 1000, 'texto' => 'Condición preventiva menor a 1000 ppm', 'unidad' => $unidad ?: 'ppm'];
        }

        if (str_contains($codigo, 'NIVEL') || str_contains($tipo, 'nivel')) {
            return ['tipo' => 'min', 'min' => 1, 'max' => null, 'texto' => 'Entrada digital activa = nivel detectado', 'unidad' => $unidad ?: '%'];
        }

        return ['tipo' => 'ninguno', 'min' => null, 'max' => null, 'texto' => 'Sin rango técnico definido', 'unidad' => $unidad];
    }

    private function estadoValor(float $valor, array $rango): string
    {
        return match ($rango['tipo']) {
            'rango' => ($valor >= $rango['min'] && $valor <= $rango['max']) ? 'ok' : 'fuera',
            'max' => $valor <= $rango['max'] ? 'ok' : 'fuera',
            'min' => $valor >= $rango['min'] ? 'ok' : 'fuera',
            default => 'sin_rango',
        };
    }

    private function conclusion(?Sensor $sensor, array $kpis, array $rango, array $alertas, array $actuaciones): string
    {
        if (!$sensor || $kpis['total'] === 0) {
            return 'No existen lecturas suficientes para generar una conclusión técnica del periodo seleccionado.';
        }

        $nombre = $sensor->nombre ?: $sensor->codigo;
        $unidad = $rango['unidad'] ? ' '.$rango['unidad'] : '';
        $promedio = number_format((float) $kpis['promedio'], 2);
        $min = number_format((float) $kpis['minimo'], 2);
        $max = number_format((float) $kpis['maximo'], 2);

        $base = "Durante el periodo analizado se registraron {$kpis['total']} mediciones de {$nombre}. El promedio fue {$promedio}{$unidad}, con mínimo de {$min}{$unidad} y máximo de {$max}{$unidad}.";

        if (!is_null($kpis['en_rango_pct'])) {
            $base .= " El {$kpis['en_rango_pct']} % de las lecturas se mantuvo dentro del criterio configurado ({$rango['texto']}).";
        }

        if ($alertas['total'] > 0) {
            $base .= " El sistema registró {$alertas['total']} alertas, de las cuales {$alertas['criticas']} fueron críticas.";
        } else {
            $base .= ' No se registraron alertas en el periodo filtrado.';
        }

        if ($actuaciones['total'] > 0) {
            $base .= " También se registraron {$actuaciones['total']} actuaciones, evidenciando trazabilidad de acciones manuales, automáticas o programadas.";
        }

        return $base;
    }
}
