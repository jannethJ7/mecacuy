@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Reporte de lecturas')
@section('page-title', 'Reporte de lecturas')
@section('page-subtitle', 'Análisis técnico para validación, impresión y capturas del prototipo')

@section('content')
@php
    $unidad = $rango['unidad'] ?? ($sensorSeleccionado->unidad ?? '');
    $fmt = fn($v) => is_null($v) ? '—' : number_format((float) $v, 2);
    $estadoClase = fn($estado) => match($estado) {
        'ok' => 'is-success',
        'fuera' => 'is-danger',
        'sin_datos' => 'is-muted',
        default => 'is-info',
    };
    $estadoTexto = fn($estado) => match($estado) {
        'ok' => 'Normal',
        'fuera' => 'Fuera de rango',
        'sin_datos' => 'Sin datos',
        default => 'Referencial',
    };
@endphp

<div class="mc-pro-page mc-report-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Reportes',
        'title' => 'Análisis de lecturas',
        'subtitle' => 'Cada sensor se analiza por separado para evitar mezclar °C, %, ppm y señales digitales.'
    ])

    <form method="GET" action="{{ route('panel.reportes.lecturas') }}" class="mc-pro-card mc-report-filter-card">
        <div class="mc-pro-card-head">
            <div>
                <h3>Filtros del reporte</h3>
                <p>Selecciona el módulo, sensor y periodo que quieres mostrar en las capturas.</p>
            </div>
            <div class="mc-report-actions">
                <a href="{{ route('panel.reportes.lecturas') }}" class="mc-pro-btn mc-pro-btn-ghost">
                    <i class="ri-refresh-line"></i> Limpiar
                </a>
                <button type="button" class="mc-pro-btn mc-pro-btn-soft" onclick="window.print()">
                    <i class="ri-printer-line"></i> Imprimir
                </button>
            </div>
        </div>

        <div class="mc-pro-form-grid">
            <label class="mc-pro-field" for="reporte_modulo_id">
                <span>Módulo</span>
                <select id="reporte_modulo_id" name="modulo_id" onchange="this.form.submit()">
                    @foreach($modulos as $modulo)
                        <option value="{{ $modulo->id }}" @selected((int) $moduloId === (int) $modulo->id)>
                            {{ $modulo->codigo }} · {{ $modulo->nombre }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="mc-pro-field" for="reporte_sensor_id">
                <span>Sensor analizado</span>
                <select id="reporte_sensor_id" name="sensor_id">
                    @foreach($sensores as $sensor)
                        <option value="{{ $sensor->id }}" @selected(optional($sensorSeleccionado)->id === $sensor->id)>
                            {{ $sensor->nombre }} · {{ $sensor->codigo }} {{ $sensor->unidad ? '(' . $sensor->unidad . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="mc-pro-field" for="reporte_desde">
                <span>Desde</span>
                <input id="reporte_desde" type="date" name="desde" value="{{ request('desde') }}">
            </label>

            <label class="mc-pro-field" for="reporte_hasta">
                <span>Hasta</span>
                <input id="reporte_hasta" type="date" name="hasta" value="{{ request('hasta') }}">
            </label>
        </div>

        <div class="mc-report-sensor-tabs" aria-label="Sensores disponibles para reporte">
            @foreach($sensores as $sensor)
                <a class="mc-report-sensor-tab @class(['is-active' => optional($sensorSeleccionado)->id === $sensor->id])"
                   href="{{ route('panel.reportes.lecturas', array_filter(['modulo_id' => $moduloId, 'sensor_id' => $sensor->id, 'desde' => request('desde'), 'hasta' => request('hasta')])) }}">
                    <i class="{{ str_contains(strtolower($sensor->codigo), 'temp') ? 'ri-temp-hot-line' : (str_contains(strtolower($sensor->codigo), 'hr') ? 'ri-drop-line' : (str_contains(strtolower($sensor->codigo), 'air') ? 'ri-windy-line' : 'ri-pulse-line')) }}"></i>
                    <span>{{ $sensor->codigo }}</span>
                </a>
            @endforeach
        </div>

        <div class="mc-pro-form-actions">
            <button class="mc-pro-btn" type="submit">
                <i class="ri-filter-3-line"></i> Aplicar filtros
            </button>
        </div>
    </form>

    <section class="mc-report-selected-card">
        <div>
            <span class="mc-pro-eyebrow">Sensor seleccionado</span>
            <h3>{{ optional($sensorSeleccionado)->nombre ?? 'Sin sensor' }}</h3>
            <p>
                {{ optional($sensorSeleccionado)->codigo ?? '—' }}
                @if(optional($sensorSeleccionado)->unidad)
                    · Unidad: {{ $sensorSeleccionado->unidad }}
                @endif
                · {{ $rango['texto'] ?? 'Sin rango configurado' }}
            </p>
        </div>
        <span class="mc-report-pill">
            <i class="ri-cpu-line"></i>
            {{ optional($moduloSeleccionado)->codigo ?? 'Módulo' }}
        </span>
    </section>

    <section class="mc-pro-kpis mc-report-kpis">
        <article class="mc-pro-kpi">
            <span><i class="ri-database-2-line"></i></span>
            <div><small>Registros</small><strong>{{ $kpis['total'] }}</strong><em>Lecturas del sensor</em></div>
        </article>
        <article class="mc-pro-kpi is-info">
            <span><i class="ri-function-line"></i></span>
            <div><small>Promedio</small><strong>{{ $fmt($kpis['promedio']) }}</strong><em>{{ $unidad ?: 'Valor medio' }}</em></div>
        </article>
        <article class="mc-pro-kpi is-success">
            <span><i class="ri-arrow-up-line"></i></span>
            <div><small>Máximo</small><strong>{{ $fmt($kpis['maximo']) }}</strong><em>{{ $unidad ?: 'Pico registrado' }}</em></div>
        </article>
        <article class="mc-pro-kpi is-warning">
            <span><i class="ri-arrow-down-line"></i></span>
            <div><small>Mínimo</small><strong>{{ $fmt($kpis['minimo']) }}</strong><em>{{ $unidad ?: 'Menor registro' }}</em></div>
        </article>
    </section>

    <section class="mc-pro-grid-2 mc-report-main-grid">
        <article class="mc-pro-card mc-report-chart-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Tendencia de {{ optional($sensorSeleccionado)->codigo ?? 'sensor' }}</h3>
                    <p>Gráfico lineal del sensor seleccionado. No mezcla unidades diferentes.</p>
                </div>
                <span class="mc-pro-badge is-info">{{ $unidad ?: 'sin unidad' }}</span>
            </div>

            <canvas
                class="mc-pro-chart is-large"
                height="285"
                data-mc-line-chart
                data-labels='@json($chart['labels'])'
                data-values='@json($chart['values'])'
            ></canvas>
        </article>

        <aside class="mc-pro-card mc-report-health-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Estado técnico</h3>
                    <p>Resumen para interpretar si el sensor trabajó dentro del criterio.</p>
                </div>
            </div>

            <div class="mc-report-range-box">
                <span>Criterio usado</span>
                <strong>{{ $rango['texto'] ?? 'Sin criterio' }}</strong>
            </div>

            @if(!is_null($kpis['en_rango_pct']))
                <div class="mc-report-progress">
                    <div class="mc-report-progress-top">
                        <span>Lecturas dentro del criterio</span>
                        <strong>{{ $kpis['en_rango_pct'] }}%</strong>
                    </div>
                    <div class="mc-report-progress-bar">
                        <i style="width: {{ min(100, max(0, $kpis['en_rango_pct'])) }}%"></i>
                    </div>
                    <small>{{ $kpis['en_rango'] }} de {{ $kpis['total'] }} registros dentro del rango.</small>
                </div>
            @else
                <p class="mc-report-muted">Este sensor no tiene rango numérico técnico configurado; se muestra como lectura referencial.</p>
            @endif

            <div class="mc-report-mini-stats">
                <div>
                    <small>Alertas</small>
                    <strong>{{ $alertasResumen['total'] }}</strong>
                    <span>{{ $alertasResumen['criticas'] }} críticas</span>
                </div>
                <div>
                    <small>Actuaciones</small>
                    <strong>{{ $actuacionesResumen['total'] }}</strong>
                    <span>{{ $actuacionesResumen['automaticas'] }} automáticas</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="mc-pro-card mc-report-conclusion-card">
        <div class="mc-report-conclusion-icon"><i class="ri-file-chart-line"></i></div>
        <div>
            <span class="mc-pro-eyebrow">Conclusión automática</span>
            <p>{{ $conclusion }}</p>
        </div>
    </section>

    <section class="mc-pro-card mc-report-summary-card">
        <div class="mc-pro-card-head">
            <div>
                <h3>Resumen por sensor</h3>
                <p>Comparación individual de cada variable del módulo seleccionado.</p>
            </div>
        </div>

        <div class="mc-pro-datatable-wrap">
            <table class="mc-pro-datatable mc-report-table">
                <thead>
                    <tr>
                        <th>Sensor</th>
                        <th>Registros</th>
                        <th>Promedio</th>
                        <th>Mínimo</th>
                        <th>Máximo</th>
                        <th>Última lectura</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resumenSensores as $fila)
                        @php($sensor = $fila['sensor'])
                        <tr>
                            <td>
                                <div class="mc-pro-table-identity">
                                    <strong>{{ $sensor->nombre }}</strong>
                                </div>
                            </td>
                            <td>{{ $fila['total'] }}</td>
                            <td class="mc-pro-table-value">{{ $fmt($fila['promedio']) }}</td>
                            <td>{{ $fmt($fila['minimo']) }}</td>
                            <td>{{ $fmt($fila['maximo']) }}</td>
                            <td>
                                @if($fila['ultima'])
                                    <strong>{{ number_format((float) $fila['ultima']->valor, 2) }} {{ $sensor->unidad }}</strong>
                                    <small>{{ optional($fila['ultima']->medido_en)->format('d/m/Y H:i') }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="mc-pro-badge {{ $estadoClase($fila['estado']) }}">{{ $estadoTexto($fila['estado']) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No existen sensores para el módulo seleccionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection
