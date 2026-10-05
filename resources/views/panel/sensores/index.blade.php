@extends('layouts.panel')

@include('panel._partials.pro-assets')
@include('panel._partials.compact-assets')

@section('title', 'Sensores')
@section('page-title', 'Sensores')
@section('page-subtitle', 'Variables críticas de cada módulo: temperatura, humedad y calidad de aire')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $sensores instanceof \Illuminate\Pagination\AbstractPaginator ? $sensores->getCollection() : collect($sensores ?? []);
@endphp

<div class="mc-pro-page mc-compact-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Monitoreo',
        'title' => 'Sensores instalados',
        
        'buttonRoute' => 'panel.sensores.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nuevo sensor',
        'buttonRoles' => ['admin']
    ])

    <form method="GET" action="{{ route('panel.sensores.index') }}" class="mc-pro-toolbar mc-compact-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line" aria-hidden="true"></i>
            <input type="search" name="buscar" value="{{ request('buscar') }}" aria-label="Buscar registros" placeholder="Buscar nombre, código, módulo o tipo...">
        </label>
        <label class="mc-compact-select">Módulo
            <select name="modulo_id">
                <option value="">Todos los módulos</option>
                @foreach($modulos as $modulo)
                    <option value="{{ $modulo->id }}" @selected((string)request('modulo_id') === (string)$modulo->id)>{{ $modulo->codigo }} · {{ $modulo->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="mc-compact-select">Estado
            <select name="estado"><option value="">Todos</option><option value="activo" @selected(request("estado") === "activo")>Activos</option><option value="inactivo" @selected(request("estado") === "inactivo")>Inactivos</option></select>
        </label>
        <div class="mc-pro-toolbar-actions">
            <button type="submit" class="mc-pro-btn mc-pro-btn-soft"><i class="ri-filter-line" aria-hidden="true"></i> Filtrar</button>
            @if(request()->filled('buscar') || request()->filled('modulo_id') || request()->filled('estado'))
                <a href="{{ route('panel.sensores.index') }}" class="mc-pro-btn mc-pro-btn-ghost">Limpiar</a>
            @endif
        </div>
    </form>

    <div id="sensoresList" class="mc-compact-list">
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

                <div class="mc-compact-identity">
                <h3>{{ $sensor->nombre }}</h3>
                <p>{{ $sensor->codigo }} · {{ $sensor->modulo->codigo ?? 'Sin módulo' }}</p>
                </div>

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
                        <strong>{{ $sensor->valor_actual_en ? \Carbon\Carbon::parse($sensor->valor_actual_en)->locale('es')->diffForHumans() : 'Sin datos' }}</strong>
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
                            <button type="submit" class="mc-pro-icon-danger" aria-label="Eliminar registro" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
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
