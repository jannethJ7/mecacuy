@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Sensores')
@section('page-title', 'Sensores')
@section('page-subtitle', 'Variables críticas de cada módulo: temperatura, humedad y calidad de aire')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $sensores instanceof \Illuminate\Pagination\AbstractPaginator ? $sensores->getCollection() : collect($sensores ?? []);
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Monitoreo',
        'title' => 'Sensores instalados',
        
        'buttonRoute' => 'panel.sensores.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nuevo sensor',
        'buttonRoles' => ['admin']
    ])

    <section class="mc-pro-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line"></i>
            <input type="search" placeholder="Buscar sensor, código, módulo o tipo..." data-mc-search="#sensoresList">
        </label>

        <div class="mc-pro-toolbar-actions">
            <button type="button" class="mc-pro-chip is-active" data-mc-filter="#sensoresList" data-filter-value="all">Todos</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#sensoresList" data-filter-value="activo">Activos</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#sensoresList" data-filter-value="inactivo">Inactivos</button>
        </div>
    </section>

    <div id="sensoresList" class="mc-pro-sensor-grid">
        @forelse($items as $sensor)
            @php
                $activo = (bool)($sensor->activo ?? true);
                $search = strtolower(($sensor->nombre ?? '') . ' ' . ($sensor->codigo ?? '') . ' ' . ($sensor->tipo ?? '') . ' ' . ($sensor->modulo->codigo ?? ''));
                $value = is_null($sensor->valor_actual) ? '—' : number_format((float)$sensor->valor_actual, 2);
            @endphp

            <article class="mc-pro-sensor-card" data-search-row data-filter="{{ $activo ? 'activo' : 'inactivo' }}" data-search="{{ $search }}">
                <div class="mc-pro-sensor-head">
                    <span class="mc-pro-sensor-icon">
                        <i class="{{ str_contains(strtolower($sensor->tipo ?? ''), 'hum') ? 'ri-water-percent-line' : (str_contains(strtolower($sensor->tipo ?? ''), 'aire') ? 'ri-windy-line' : 'ri-temp-hot-line') }}"></i>
                    </span>
                    <span class="mc-pro-badge {{ $activo ? 'is-success' : 'is-muted' }}">
                        {{ $activo ? 'ACTIVO' : 'INACTIVO' }}
                    </span>
                </div>

                <h3>{{ $sensor->nombre }}</h3>
                <p>{{ $sensor->codigo }} · {{ $sensor->modulo->codigo ?? 'Sin módulo' }}</p>

                <div class="mc-pro-reading">
                    <strong>{{ $value }}</strong>
                    <span>{{ $sensor->unidad ?? '' }}</span>
                </div>

                <div class="mc-pro-mini-grid">
                    <div>
                        <small>Tipo</small>
                        <strong>{{ $sensor->tipo ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Última lectura</small>
                        <strong>{{ $sensor->valor_actual_en ? \Carbon\Carbon::parse($sensor->valor_actual_en)->diffForHumans() : 'Sin datos' }}</strong>
                    </div>
                    <div>
                        <small>GPIO</small>
                        <strong>{{ is_null($sensor->gpio_pin) ? '—' : 'GPIO '.$sensor->gpio_pin }}</strong>
                    </div>
                </div>

                <div class="mc-pro-card-actions">
                    @if(Route::has('panel.lecturas.index'))
                        <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.lecturas.index', ['sensor_id' => $sensor->id]) }}">
                            <i class="ri-line-chart-line"></i> Lecturas
                        </a>
                    @endif

                    @if($canAdmin && Route::has('panel.sensores.edit'))
                        <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.sensores.edit', $sensor) }}">
                            <i class="ri-pencil-line"></i> Editar
                        </a>
                    @endif

                    @if($canAdmin && Route::has('panel.sensores.destroy'))
                        <form method="POST" action="{{ route('panel.sensores.destroy', $sensor) }}" data-mc-confirm="¿Eliminar este sensor?">
                            @csrf
                            @method('DELETE')
                            <button class="mc-pro-icon-danger"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            @include('panel._partials.empty', [
                'title' => 'No hay sensores registrados',
                'message' => 'Agrega sensores por módulo para comenzar el monitoreo.',
                'icon' => 'ri-temp-hot-line'
            ])
        @endforelse
    </div>

    @if($sensores instanceof \Illuminate\Pagination\AbstractPaginator)
        @include('panel._partials.pagination', ['paginator' => $sensores])
    @endif
</div>
@endsection
