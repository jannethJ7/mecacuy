@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Programaciones')
@section('page-title', 'Programaciones')
@section('page-subtitle', 'Horarios para alimentación, agua y acciones repetitivas')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $canOperate = in_array($rol, ['admin', 'operador'], true);
    $items = $programaciones instanceof \Illuminate\Pagination\AbstractPaginator ? $programaciones->getCollection() : collect($programaciones ?? []);
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Automatización horaria',
        'title' => 'Programaciones',
        'subtitle' => 'Acciones ejecutadas por días y horarios configurados.',
        'buttonRoute' => 'panel.automatizacion.programaciones.create',
        'buttonIcon' => 'ri-add-circle-line',
        'buttonText' => 'Nueva programación',
        'buttonRoles' => ['admin']
    ])

    @if($canOperate && Route::has('panel.automatizacion.programaciones.evaluar'))
        <form method="POST" action="{{ route('panel.automatizacion.programaciones.evaluar') }}" class="mc-pro-toolbar">
            @csrf
            <div>
                <strong>Motor horario</strong>
                <span>Evalúa programaciones vencidas, crea comandos IoT y agenda el apagado automático.</span>
            </div>
            <button class="mc-pro-btn mc-pro-btn-soft">
                <i class="ri-play-circle-line"></i> Ejecutar ahora
            </button>
        </form>
    @endif

    <section class="mc-pro-timeline">
        @forelse($items as $programacion)
            @php
                $diasRaw = is_array($programacion->dias)
                    ? $programacion->dias
                    : json_decode($programacion->dias ?? '[]', true);

                $mapaDias = [
                    'lun' => 'lu', 'mar' => 'ma', 'mie' => 'mi', 'jue' => 'ju', 'vie' => 'vi', 'sab' => 'sa', 'dom' => 'do',
                ];

                $dias = collect($diasRaw ?? [])->map(fn ($d) => $mapaDias[$d] ?? $d)->all();
            @endphp

            <article class="mc-pro-time-card" data-search-row>
                <div class="mc-pro-time-hour">
                    <strong>{{ substr($programacion->hora_inicio, 0, 5) }}</strong>
                    <span>{{ $programacion->duracion_seg }} s</span>
                </div>

                <div class="mc-pro-time-body">
                    <div class="mc-pro-alert-top">
                        <span class="mc-pro-badge {{ $programacion->activo ? 'is-success' : 'is-muted' }}">
                            {{ $programacion->activo ? 'ACTIVA' : 'INACTIVA' }}
                        </span>
                        <span class="mc-pro-badge is-info">P{{ $programacion->prioridad }}</span>
                    </div>

                    <h3>{{ $programacion->nombre }}</h3>
                    <p>{{ $programacion->actuador->nombre ?? 'Actuador' }} · {{ $programacion->modulo->codigo ?? 'Módulo' }}</p>

                    <div class="mc-pro-days">
                        @foreach(['lu','ma','mi','ju','vi','sa','do'] as $dia)
                            <span class="{{ in_array($dia, $dias ?? []) ? 'is-on' : '' }}">{{ strtoupper($dia) }}</span>
                        @endforeach
                    </div>

                    <div class="mc-pro-card-actions">
                        @if(Route::has('panel.automatizacion.programaciones.show'))
                            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.programaciones.show', $programacion) }}">
                                <i class="ri-eye-line"></i> Ver ejecuciones
                            </a>
                        @endif
                        @if($canAdmin && Route::has('panel.automatizacion.programaciones.edit'))
                            <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.automatizacion.programaciones.edit', $programacion) }}">
                                <i class="ri-pencil-line"></i> Editar
                            </a>
                        @endif
                        @if($canAdmin && Route::has('panel.automatizacion.programaciones.destroy'))
                            <form method="POST" action="{{ route('panel.automatizacion.programaciones.destroy', $programacion) }}" data-mc-confirm="¿Eliminar esta programación?">
                                @csrf
                                @method('DELETE')
                                <button class="mc-pro-icon-danger"><i class="ri-delete-bin-line"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            @include('panel._partials.empty', [
                'title' => 'No hay programaciones',
                'message' => 'Crea horarios para alimentar, suministrar agua o activar dispositivos.',
                'icon' => 'ri-calendar-schedule-line'
            ])
        @endforelse
    </section>

    @if($programaciones instanceof \Illuminate\Pagination\AbstractPaginator)
        @include('panel._partials.pagination', ['paginator' => $programaciones])
    @endif
</div>
@endsection
