@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Lecturas')
@section('page-title', 'Lecturas')
@section('page-subtitle', 'Histórico de mediciones y gráfico por sensor')

@section('content')
@php
    $items = $lecturas instanceof \Illuminate\Pagination\AbstractPaginator ? $lecturas->getCollection() : collect($lecturas ?? []);
    $chartLabels = $items->take(80)->reverse()->map(fn($l) => \Carbon\Carbon::parse($l->medido_en ?? $l->created_at)->format('H:i'))->values();
    $chartValues = $items->take(80)->reverse()->map(fn($l) => (float) $l->valor)->values();
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Series temporales',
        'title' => 'Lecturas del sistema',
        'subtitle' => 'Consulta mediciones por sensor, periodo y calidad de dato.'
    ])

    <section class="mc-pro-card mc-pro-filter-card">
        <form method="GET" action="{{ route('panel.lecturas.index') }}" class="mc-pro-filter-form mc-pro-filter-form-lecturas">
            <input type="hidden" name="per_page" value="{{ $perPage ?? request('per_page', 20) }}">

            <div class="mc-pro-field">
                <label for="field_sensor_id">Sensor</label>
                <select id="field_sensor_id" name="sensor_id" data-mc-select-placement="down">
                    <option value="">Todos los sensores</option>
                    @foreach(($sensores ?? []) as $sensor)
                        <option value="{{ $sensor->id }}" @selected((string) request('sensor_id', $sensorId ?? '') === (string) $sensor->id)>
                            {{ $sensor->nombre }} · {{ $sensor->codigo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mc-pro-field">
                <label for="field_from">Desde</label>
                <input id="field_from" type="datetime-local" name="from" value="{{ request('from', isset($from) && $from ? \Carbon\Carbon::parse($from)->format('Y-m-d\TH:i') : '') }}">
            </div>

            <div class="mc-pro-field">
                <label for="field_to">Hasta</label>
                <input id="field_to" type="datetime-local" name="to" value="{{ request('to', isset($to) && $to ? \Carbon\Carbon::parse($to)->format('Y-m-d\TH:i') : '') }}">
            </div>

            <div class="mc-pro-filter-actions">
                <button class="mc-pro-btn mc-pro-btn-primary" type="submit">
                    <i class="ri-filter-3-line"></i> Filtrar
                </button>
                <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.lecturas.index') }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="mc-pro-grid-2">
        <div class="mc-pro-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Gráfico rápido</h3>
                    <p>{{ $items->count() }} registros cargados en esta página</p>
                </div>
            </div>

            <canvas
                class="mc-pro-chart"
                height="220"
                data-mc-line-chart
                data-labels='@json($chartLabels)'
                data-values='@json($chartValues)'
            ></canvas>
        </div>

        <div class="mc-pro-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Última lectura</h3>
                    <p>Resumen del dato más reciente según el filtro</p>
                </div>
            </div>

            @php $ultima = $items->first(); @endphp

            @if($ultima)
                <div class="mc-pro-reading-big">
                    <strong>{{ number_format((float) $ultima->valor, 2) }}</strong>
                    <span>{{ $ultima->sensor->unidad ?? '' }}</span>
                </div>

                <div class="mc-pro-mini-grid">
                    <div>
                        <small>Sensor</small>
                        <strong>{{ $ultima->sensor->nombre ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Código</small>
                        <strong>{{ $ultima->sensor->codigo ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Medido</small>
                        <strong>{{ $ultima->medido_en ? \Carbon\Carbon::parse($ultima->medido_en)->diffForHumans() : '—' }}</strong>
                    </div>
                    <div>
                        <small>Calidad</small>
                        <strong>{{ strtoupper($ultima->calidad ?? 'ok') }}</strong>
                    </div>
                </div>
            @else
                @include('panel._partials.empty', ['title' => 'Sin lecturas', 'message' => 'No hay datos para el filtro seleccionado.', 'icon' => 'ri-line-chart-line'])
            @endif
        </div>
    </section>

    <section class="mc-pro-datatable-card">
        <form method="GET" action="{{ route('panel.lecturas.index') }}" class="mc-pro-datatable-top">
            <input type="hidden" name="sensor_id" value="{{ request('sensor_id') }}">
            <input type="hidden" name="from" value="{{ request('from') }}">
            <input type="hidden" name="to" value="{{ request('to') }}">

            <label class="mc-pro-page-size">
                <span>Mostrar</span>
                <select name="per_page" data-mc-native-select onchange="this.form.submit()">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <span>registros</span>
            </label>

            <label class="mc-pro-datatable-search">
                <span>Buscar:</span>
                <input type="search" name="q" value="{{ $q ?? request('q') }}" placeholder="Sensor, código, valor...">
            </label>
        </form>

        <div class="mc-pro-datatable-wrap">
            <table class="mc-pro-datatable">
                <thead>
                    <tr>
                        <th>Sensor</th>
                        <th>Módulo</th>
                        <th>Valor</th>
                        <th>Calidad</th>
                        <th>Fecha de medición</th>
                        <th>Recibido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $lectura)
                        @php
                            $calidad = $lectura->calidad ?? 'ok';
                            $icon = match($lectura->sensor->tipo ?? '') {
                                'temperatura' => 'ri-temp-hot-line',
                                'humedad', 'humedad_suelo' => 'ri-drop-line',
                                'luz' => 'ri-sun-line',
                                'nivel_agua' => 'ri-goblet-line',
                                default => 'ri-radar-line',
                            };
                        @endphp
                        <tr>
                            <td data-label="Sensor">
                                <div class="mc-pro-table-identity">
                                    <span class="mc-pro-row-icon"><i class="{{ $icon }}"></i></span>
                                    <div>
                                        <strong>{{ $lectura->sensor->nombre ?? '—' }}</strong>
                                        <small>{{ $lectura->sensor->codigo ?? '—' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Módulo">{{ $lectura->sensor->modulo->codigo ?? '—' }}</td>
                            <td data-label="Valor">
                                <strong class="mc-pro-table-value">{{ number_format((float) $lectura->valor, 3) }}</strong>
                                <small>{{ $lectura->sensor->unidad ?? '' }}</small>
                            </td>
                            <td data-label="Calidad">
                                <span class="mc-pro-status-pill {{ $calidad === 'ok' ? 'is-new' : 'is-pending' }}">{{ strtoupper($calidad) }}</span>
                            </td>
                            <td data-label="Fecha de medición" class="is-date-ok">{{ $lectura->medido_en ? \Carbon\Carbon::parse($lectura->medido_en)->format('d/m/Y H:i') : '—' }}</td>
                            <td data-label="Recibido">{{ $lectura->recibido_en ? \Carbon\Carbon::parse($lectura->recibido_en)->format('d/m/Y H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('panel._partials.empty', ['title' => 'Sin lecturas', 'message' => 'No hay registros para mostrar.', 'icon' => 'ri-line-chart-line'])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('panel._partials.pagination', ['paginator' => $lecturas])
    </section>
</div>
@endsection
