@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Detalle de programación')
@section('page-title', 'Detalle de programación')
@section('page-subtitle', 'Horario y ejecuciones')
@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
@endphp
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <div class="mc-pro-alert-top">
            <span class="mc-pro-badge {{ $programacion->activo ? 'is-success' : 'is-muted' }}">
                {{ $programacion->activo ? 'ACTIVA' : 'INACTIVA' }}
            </span>
            <span class="mc-pro-badge is-info">P{{ $programacion->prioridad }}</span>
        </div>

        <h3>{{ $programacion->nombre }}</h3>
        <p>{{ $programacion->modulo->codigo ?? 'Sin módulo' }} · {{ $programacion->actuador->codigo ?? 'Sin actuador' }}</p>
        <div class="mc-pro-mini-grid">
            <div><small>Días</small><strong>{{ implode(', ', $programacion->dias ?? []) }}</strong></div>
            <div><small>Hora</small><strong>{{ substr($programacion->hora_inicio, 0, 5) }}</strong></div>
            <div><small>Duración</small><strong>{{ $programacion->duracion_seg }} s</strong></div>
            <div><small>Estado enviado</small><strong><code>{{ json_encode($programacion->estado_deseado, JSON_UNESCAPED_UNICODE) }}</code></strong></div>
        </div>
        <div class="mc-pro-card-actions">
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.programaciones.index') }}">Volver</a>
            @if($canAdmin && Route::has('panel.automatizacion.programaciones.edit'))
                <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.automatizacion.programaciones.edit', $programacion) }}">Editar</a>
            @endif
        </div>
    </div>

    <div class="mc-pro-card">
        <h3>Ejecuciones recientes</h3>
        <p class="mc-pro-muted">Si una ejecución tiene fin vacío, significa que el sistema ya creó el comando de activación y todavía falta crear el apagado automático.</p>
        <div class="mc-pro-table-wrap">
            <table class="mc-pro-table">
                <thead><tr><th>Inicio programado</th><th>Fin programado</th><th>Estado</th><th>Nota</th></tr></thead>
                <tbody>
                @forelse($ejecuciones as $ejecucion)
                    <tr>
                        <td>{{ $ejecucion->inicio_en }}</td>
                        <td>{{ $ejecucion->fin_en ?? 'Pendiente' }}</td>
                        <td>
                            <span class="mc-pro-badge {{ $ejecucion->estado === 'ok' ? 'is-success' : ($ejecucion->estado === 'fallido' ? 'is-danger' : 'is-muted') }}">
                                {{ strtoupper($ejecucion->estado) }}
                            </span>
                        </td>
                        <td>{{ $ejecucion->nota ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Sin ejecuciones registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('panel._partials.pagination', ['paginator' => $ejecuciones])
    </div>
</div>
@endsection
