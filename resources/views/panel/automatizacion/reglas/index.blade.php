@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Reglas automáticas')
@section('page-title', 'Reglas automáticas')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $reglas instanceof \Illuminate\Pagination\AbstractPaginator ? $reglas->getCollection() : collect($reglas ?? []);
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Automatización',
        'title' => 'Reglas de control',
        'subtitle' => 'Configura decisiones automáticas por módulo y variable crítica.',
        'buttonRoute' => 'panel.automatizacion.reglas.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nueva regla',
        'buttonRoles' => ['admin']
    ])

    <section class="mc-pro-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line"></i>
            <input type="search" placeholder="Buscar por regla, sensor, actuador o módulo..." data-mc-search="#reglasTable">
        </label>

        <div class="mc-pro-toolbar-actions">
            <button type="button" class="mc-pro-chip is-active" data-mc-filter="#reglasTable" data-filter-value="all">Todas</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#reglasTable" data-filter-value="activo">Activas</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#reglasTable" data-filter-value="inactivo">Inactivas</button>

            @if(Route::has('panel.automatizacion.reglas.evaluar'))
                <form method="POST" action="{{ route('panel.automatizacion.reglas.evaluar') }}">
                    @csrf
                    <button class="mc-pro-btn mc-pro-btn-soft" type="submit">
                        <i class="ri-play-circle-line"></i> Evaluar ahora
                    </button>
                </form>
            @endif
        </div>
    </section>

    <section class="mc-pro-card">
        <div id="reglasTable" class="mc-pro-table-wrap">
            <table class="mc-pro-table">
                <thead>
                    <tr>
                        <th>Regla</th>
                        <th>Módulo</th>
                        <th>Sensor</th>
                        <th>Actuador</th>
                        <th>Rango objetivo</th>
                        <th>Estado motor</th>
                        <th>Hist./Retardo</th>
                        <th>Prioridad</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $regla)
                        @php
                            $activo = (bool)($regla->activo ?? true);
                            $search = strtolower(($regla->nombre ?? '') . ' ' . ($regla->modulo->codigo ?? '') . ' ' . ($regla->sensor->codigo ?? '') . ' ' . ($regla->actuador->codigo ?? ''));
                        @endphp
                        <tr data-search-row data-filter="{{ $activo ? 'activo' : 'inactivo' }}" data-search="{{ $search }}">
                            <td data-label="Regla">
                                <strong>{{ $regla->nombre }}</strong>
                                <small>{{ $activo ? 'Activa' : 'Inactiva' }}</small>
                            </td>
                            <td data-label="Módulo">{{ $regla->modulo->codigo ?? '—' }}</td>
                            <td data-label="Sensor">
                                <strong>{{ $regla->sensor->nombre ?? '—' }}</strong>
                                <small>{{ $regla->sensor->codigo ?? '' }}</small>
                            </td>
                            <td data-label="Actuador">
                                <strong>{{ $regla->actuador->nombre ?? '—' }}</strong>
                                <small>{{ $regla->actuador->codigo ?? '' }}</small>
                            </td>
                            <td data-label="Rango">
                                {{ $regla->objetivo_min ?? '—' }} – {{ $regla->objetivo_max ?? '—' }}
                            </td>
                            <td data-label="Estado motor">
                                @php
                                    $latch = is_array($regla->estado->estado_latch ?? null) ? $regla->estado->estado_latch : [];
                                    $activoMotor = (bool)($latch['activo'] ?? false);
                                @endphp
                                <strong>{{ $activoMotor ? 'Activo' : 'Reposo' }}</strong>
                                <small>{{ optional($regla->estado?->evaluado_en)->format('d/m H:i') ?? 'Sin evaluar' }}</small>
                            </td>
                            <td data-label="Hist./Retardo">
                                H: {{ $regla->histeresis ?? 0 }} · {{ $regla->retardo_seg ?? 0 }} s
                            </td>
                            <td data-label="Prioridad">{{ $regla->prioridad }}</td>
                            <td data-label="Acciones">
                                <div class="mc-pro-row-actions">
                                    @if($canAdmin && Route::has('panel.automatizacion.reglas.edit'))
                                        <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.automatizacion.reglas.edit', $regla) }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                    @endif
                                    @if($canAdmin && Route::has('panel.automatizacion.reglas.destroy'))
                                        <form method="POST" action="{{ route('panel.automatizacion.reglas.destroy', $regla) }}" data-mc-confirm="¿Eliminar esta regla?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="mc-pro-icon-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">@include('panel._partials.empty', ['title' => 'No hay reglas', 'message' => 'Crea reglas para automatizar la respuesta del sistema.', 'icon' => 'ri-robot-2-line'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reglas instanceof \Illuminate\Pagination\AbstractPaginator)
            @include('panel._partials.pagination', ['paginator' => $reglas])
        @endif
    </section>
</div>
@endsection
