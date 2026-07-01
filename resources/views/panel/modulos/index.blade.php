@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Módulos')
@section('page-title', 'Módulos ESP32')
@section('page-subtitle', 'Administra cada jaula o unidad física de producción')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $modulos instanceof \Illuminate\Pagination\AbstractPaginator ? $modulos->getCollection() : collect($modulos ?? []);
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Gestión multi-módulo',
        'title' => 'Módulos registrados',
        'subtitle' => 'Cada ESP32 se controla como una unidad independiente',
        'buttonRoute' => 'panel.modulos.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nuevo módulo',
        'buttonRoles' => ['admin']
    ])

    <section class="mc-pro-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line"></i>
            <input type="search" placeholder="Buscar por código, nombre, UID o IP..." data-mc-search="#modulosList">
        </label>

        <div class="mc-pro-toolbar-actions">
            <button type="button" class="mc-pro-chip is-active" data-mc-filter="#modulosList" data-filter-value="all">Todos</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#modulosList" data-filter-value="online">Online</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#modulosList" data-filter-value="offline">Offline</button>
        </div>
    </section>

    <section id="modulosList" class="mc-pro-module-grid">
        @forelse($items as $modulo)
            @php
                $online = !empty($modulo->ultimo_contacto) && \Carbon\Carbon::parse($modulo->ultimo_contacto)->gt(now()->subMinutes(10));
                $search = strtolower(($modulo->codigo ?? '') . ' ' . ($modulo->nombre ?? '') . ' ' . ($modulo->uid ?? '') . ' ' . ($modulo->ip ?? ''));
            @endphp

            <article class="mc-pro-module-card" data-search-row data-filter="{{ $online ? 'online' : 'offline' }}" data-search="{{ $search }}">
                <div class="mc-pro-module-top">
                    <span class="mc-pro-module-icon">
                        <i class="ri-cpu-line"></i>
                    </span>
                    <span class="mc-pro-badge {{ $online ? 'is-success' : 'is-muted' }}">
                        {{ $online ? 'ONLINE' : 'OFFLINE' }}
                    </span>
                </div>

                <h3>{{ $modulo->nombre ?? 'Módulo sin nombre' }}</h3>
                <p>{{ $modulo->codigo }} · {{ $modulo->uid }}</p>

                <div class="mc-pro-mini-grid">
                    <div>
                        <small>IP</small>
                        <strong>{{ $modulo->ip ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>RSSI</small>
                        <strong>{{ $modulo->rssi ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Firmware</small>
                        <strong>{{ $modulo->version_firmware ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Último contacto</small>
                        <strong>{{ $modulo->ultimo_contacto ? \Carbon\Carbon::parse($modulo->ultimo_contacto)->diffForHumans() : 'Sin datos' }}</strong>
                    </div>
                </div>

                <div class="mc-pro-card-actions">
                    @if(Route::has('panel.modulos.show'))
                        <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.modulos.show', $modulo) }}">
                            <i class="ri-eye-line"></i> Ver
                        </a>
                    @endif

                    @if($canAdmin && Route::has('panel.modulos.edit'))
                        <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.modulos.edit', $modulo) }}">
                            <i class="ri-pencil-line"></i> Editar
                        </a>
                    @endif

                    @if($canAdmin && Route::has('panel.modulos.destroy'))
                        <form method="POST" action="{{ route('panel.modulos.destroy', $modulo) }}" data-mc-confirm="¿Eliminar este módulo? Se perderá la relación operativa si no hay respaldo.">
                            @csrf
                            @method('DELETE')
                            <button class="mc-pro-icon-danger" title="Eliminar">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            @include('panel._partials.empty', [
                'title' => 'No hay módulos registrados',
                'message' => 'Agrega tu primer ESP32 para iniciar el sistema modular.',
                'icon' => 'ri-cpu-line'
            ])
        @endforelse
    </section>

    @if($modulos instanceof \Illuminate\Pagination\AbstractPaginator)
        @include('panel._partials.pagination', ['paginator' => $modulos])
    @endif
</div>
@endsection
