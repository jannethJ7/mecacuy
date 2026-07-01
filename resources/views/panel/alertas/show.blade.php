@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Detalle de alerta')
@section('page-title', 'Detalle de alerta')
@section('page-subtitle', 'Revisión técnica del evento')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canOperateAlerts = in_array($rol, ['admin', 'operador'], true);
@endphp
<div class="mc-pro-page">
    @include('panel._partials.flash')

    <section class="mc-pro-card mc-pro-form-card">
        <div class="mc-pro-card-head">
            <div>
                <span class="mc-pro-badge {{ $alerta->severidad === 'critico' ? 'is-danger' : ($alerta->severidad === 'info' ? 'is-info' : 'is-warning') }}">
                    {{ strtoupper($alerta->severidad) }}
                </span>
                <h3>{{ $alerta->mensaje }}</h3>
                <p>Estado: {{ strtoupper($alerta->estado) }}</p>
            </div>
        </div>

        <div class="mc-pro-mini-grid">
            <div><small>Módulo</small><strong>{{ $alerta->modulo->codigo ?? '—' }}</strong></div>
            <div><small>Sensor</small><strong>{{ $alerta->sensor->codigo ?? '—' }}</strong></div>
            <div><small>Actuador</small><strong>{{ $alerta->actuador->codigo ?? '—' }}</strong></div>
            <div><small>Fecha</small><strong>{{ $alerta->created_at ? \Carbon\Carbon::parse($alerta->created_at)->format('d/m/Y H:i') : '—' }}</strong></div>
        </div>

        <div class="mc-pro-code">
            <strong>Contexto JSON</strong>
            <pre>{{ json_encode(is_array($alerta->contexto) ? $alerta->contexto : json_decode($alerta->contexto ?? '{}', true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="mc-pro-form-actions">
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.alertas.index') }}">Volver</a>

            @if($canOperateAlerts && $alerta->estado === 'abierta' && Route::has('panel.alertas.reconocer'))
                <form method="POST" action="{{ route('panel.alertas.reconocer', $alerta) }}">
                    @csrf
                    @method('PATCH')
                    <button class="mc-pro-btn mc-pro-btn-soft">Reconocer</button>
                </form>
            @endif

            @if($canOperateAlerts && $alerta->estado !== 'cerrada' && Route::has('panel.alertas.cerrar'))
                <form method="POST" action="{{ route('panel.alertas.cerrar', $alerta) }}" data-mc-confirm="¿Cerrar esta alerta?">
                    @csrf
                    @method('PATCH')
                    <button class="mc-pro-btn mc-pro-btn-primary">Cerrar alerta</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
