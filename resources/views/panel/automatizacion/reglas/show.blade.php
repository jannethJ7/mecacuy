@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Detalle de regla')
@section('page-title', 'Detalle de regla automática')
@section('page-subtitle', 'Condición de control y estado interno del motor')
@section('content')
@php
    $latch = is_array($regla->estado->estado_latch ?? null) ? $regla->estado->estado_latch : [];
    $activoMotor = (bool)($latch['activo'] ?? false);
@endphp
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $regla->nombre }}</h3>
        <p>{{ $regla->modulo->codigo ?? 'Sin módulo' }} · {{ $regla->sensor->codigo ?? 'Sin sensor' }} → {{ $regla->actuador->codigo ?? 'Sin actuador' }}</p>

        <div class="mc-pro-mini-grid">
            <div><small>Activo</small><strong>{{ $regla->activo ? 'Sí' : 'No' }}</strong></div>
            <div><small>Objetivo mín.</small><strong>{{ $regla->objetivo_min ?? '—' }}</strong></div>
            <div><small>Objetivo máx.</small><strong>{{ $regla->objetivo_max ?? '—' }}</strong></div>
            <div><small>Histéresis</small><strong>{{ $regla->histeresis ?? 0 }}</strong></div>
            <div><small>Retardo</small><strong>{{ $regla->retardo_seg ?? 0 }} s</strong></div>
            <div><small>Prioridad</small><strong>{{ $regla->prioridad }}</strong></div>
            <div><small>Estado motor</small><strong>{{ $activoMotor ? 'Activo' : 'Reposo' }}</strong></div>
            <div><small>Última evaluación</small><strong>{{ optional($regla->estado?->evaluado_en)->format('d/m/Y H:i') ?? 'Sin evaluar' }}</strong></div>
        </div>

        <div class="mc-pro-card" style="margin-top: 18px;">
            <h4>Estado interno</h4>
            <pre style="white-space: pre-wrap; margin: 0;">{{ json_encode($latch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="mc-pro-card" style="margin-top: 18px;">
            <h4>Payload configurado</h4>
            <pre style="white-space: pre-wrap; margin: 0;">{{ json_encode($regla->payload ?? ['estado_activo' => ['on' => true], 'estado_inactivo' => ['on' => false]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>

        <div class="mc-pro-card-actions">
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.reglas.index') }}">Volver</a>
            @if((auth()->user()->rol ?? null) === 'admin')
                <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.automatizacion.reglas.edit', $regla) }}">Editar</a>
            @endif
            @if(Route::has('panel.automatizacion.reglas.evaluar'))
                <form method="POST" action="{{ route('panel.automatizacion.reglas.evaluar') }}">
                    @csrf
                    <input type="hidden" name="modulo_id" value="{{ $regla->modulo_id }}">
                    <button class="mc-pro-btn mc-pro-btn-primary" type="submit">
                        <i class="ri-play-circle-line"></i> Evaluar módulo
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
