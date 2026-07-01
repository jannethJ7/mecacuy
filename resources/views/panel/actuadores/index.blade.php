@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Actuadores')
@section('page-title', 'Actuadores')
@section('page-subtitle', 'Control manual y revisión de estado deseado/reportado')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $actuadores instanceof \Illuminate\Pagination\AbstractPaginator ? $actuadores->getCollection() : collect($actuadores ?? []);
    $modo = $modo ?? ($config['modo_global'] ?? 'manual');
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Control',
        'title' => 'Actuadores del sistema',
        'subtitle' => 'Ventilador, calefactor, agua y dosificador de croquetas por módulo.',
        'buttonRoute' => 'panel.actuadores.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nuevo actuador',
        'buttonRoles' => ['admin']
    ])

    <section class="mc-pro-mode {{ $modo === 'manual' ? 'is-manual' : 'is-auto' }}">
        <i class="{{ $modo === 'manual' ? 'ri-hand' : 'ri-robot-2-line' }}"></i>
        <div>
            <strong>Modo actual: {{ strtoupper($modo) }}</strong>
            <p>
                {{ $modo === 'manual'
                    ? 'Puedes usar controles manuales si el endpoint está habilitado.'
                    : 'El control manual está bloqueado mientras el sistema opera en automático.' }}
            </p>
        </div>
        @if(Route::has('panel.ajustes.sistema'))
            <a href="{{ route('panel.ajustes.sistema') }}">Cambiar modo</a>
        @endif
    </section>

    <section class="mc-pro-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line"></i>
            <input type="search" placeholder="Buscar actuador, módulo, código o tipo..." data-mc-search="#actuadoresList">
        </label>

        <div class="mc-pro-toolbar-actions">
            <button type="button" class="mc-pro-chip is-active" data-mc-filter="#actuadoresList" data-filter-value="all">Todos</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#actuadoresList" data-filter-value="on">ON</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#actuadoresList" data-filter-value="off">OFF</button>
        </div>
    </section>

    <div id="actuadoresList" class="mc-pro-actuator-grid">
        @forelse($items as $actuador)
            @php
                $deseado = is_array($actuador->estado_deseado)
                    ? $actuador->estado_deseado
                    : json_decode($actuador->estado_deseado ?? '{}', true);

                $reportado = is_array($actuador->estado_reportado)
                    ? $actuador->estado_reportado
                    : json_decode($actuador->estado_reportado ?? '{}', true);

                $on = (bool)($deseado['on'] ?? false);
                $reportedOn = array_key_exists('on', $reportado ?? []) ? (bool)$reportado['on'] : null;
                $search = strtolower(($actuador->nombre ?? '') . ' ' . ($actuador->codigo ?? '') . ' ' . ($actuador->tipo ?? '') . ' ' . ($actuador->modulo->codigo ?? ''));
                $canManual = in_array($rol, ['admin', 'operador'], true) && $modo === 'manual' && Route::has('panel.actuadores.manual');
            @endphp

            <article class="mc-pro-actuator-card {{ $on ? 'is-on' : 'is-off' }}" data-search-row data-filter="{{ $on ? 'on' : 'off' }}" data-search="{{ $search }}">
                <div class="mc-pro-actuator-head">
                    <span class="mc-pro-actuator-icon">
                        <i class="{{ str_contains(strtolower($actuador->nombre ?? ''), 'vent') ? 'ri-windy-line' : (str_contains(strtolower($actuador->nombre ?? ''), 'cal') ? 'ri-fire-line' : (str_contains(strtolower($actuador->nombre ?? ''), 'agua') ? 'ri-drop-line' : 'ri-toggle-line')) }}"></i>
                    </span>
                    <span class="mc-pro-badge {{ $on ? 'is-success' : 'is-muted' }}" data-state-label>
                        {{ $on ? 'ON' : 'OFF' }}
                    </span>
                </div>

                <h3>{{ $actuador->nombre }}</h3>
                <p>{{ $actuador->codigo }} · {{ $actuador->modulo->codigo ?? 'Sin módulo' }}</p>

                <div class="mc-pro-mini-grid">
                    <div>
                        <small>Tipo</small>
                        <strong>{{ $actuador->tipo }}</strong>
                    </div>
                    <div>
                        <small>GPIO</small>
                        <strong>{{ $actuador->gpio_pin ?? '—' }}</strong>
                    </div>
                    <div>
                        <small>Reportado</small>
                        <strong>{{ is_null($reportedOn) ? '—' : ($reportedOn ? 'ON' : 'OFF') }}</strong>
                    </div>
                    <div>
                        <small>Cambiado</small>
                        <strong>{{ $actuador->cambiado_en ? \Carbon\Carbon::parse($actuador->cambiado_en)->diffForHumans() : '—' }}</strong>
                    </div>
                </div>

                <div class="mc-pro-toggle-wrap {{ !$canManual ? 'is-disabled' : '' }}">
                    <button
                        type="button"
                        class="mc-pro-toggle {{ !$on ? 'is-active' : '' }}"
                        data-mc-switch
                        data-url="{{ $canManual ? route('panel.actuadores.manual', $actuador) : '' }}"
                        data-state="0"
                        @disabled(!$canManual)
                    >
                        OFF
                    </button>
                    <button
                        type="button"
                        class="mc-pro-toggle {{ $on ? 'is-active' : '' }}"
                        data-mc-switch
                        data-url="{{ $canManual ? route('panel.actuadores.manual', $actuador) : '' }}"
                        data-state="1"
                        @disabled(!$canManual)
                    >
                        ON
                    </button>
                </div>

                @if(!$canManual)
                    <small class="mc-pro-help">
                        {{ $modo !== 'manual'
                            ? 'Bloqueado por modo automático.'
                            : 'Para control AJAX agrega la ruta panel.actuadores.manual.' }}
                    </small>
                @endif

                <div class="mc-pro-card-actions">
                    @if($canAdmin && Route::has('panel.actuadores.edit'))
                        <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.actuadores.edit', $actuador) }}">
                            <i class="ri-pencil-line"></i> Editar
                        </a>
                    @endif

                    @if($canAdmin && Route::has('panel.actuadores.destroy'))
                        <form method="POST" action="{{ route('panel.actuadores.destroy', $actuador) }}" data-mc-confirm="¿Eliminar este actuador?">
                            @csrf
                            @method('DELETE')
                            <button class="mc-pro-icon-danger"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            @include('panel._partials.empty', [
                'title' => 'No hay actuadores registrados',
                'message' => 'Agrega relés, válvulas o dosificadores asociados a cada módulo.',
                'icon' => 'ri-toggle-line'
            ])
        @endforelse
    </div>

    @if($actuadores instanceof \Illuminate\Pagination\AbstractPaginator)
        @include('panel._partials.pagination', ['paginator' => $actuadores])
    @endif
</div>
@endsection
