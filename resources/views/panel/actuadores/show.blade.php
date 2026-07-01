@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Detalle de actuador')
@section('page-title', 'Detalle de actuador')
@section('page-subtitle', 'Estado, configuración e historial de actuación')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $actuador->nombre }}</h3>
        <p>{{ $actuador->codigo }} · {{ $actuador->modulo->codigo ?? 'Sin módulo' }}</p>
        <div class="mc-pro-mini-grid">
            <div><small>Tipo</small><strong>{{ $actuador->tipo }}</strong></div>
            <div><small>GPIO</small><strong>{{ $actuador->gpio_pin ?? '—' }}</strong></div>
            <div><small>Activo</small><strong>{{ $actuador->activo ? 'Sí' : 'No' }}</strong></div>
            <div><small>Invertido</small><strong>{{ $actuador->invertido ? 'Sí' : 'No' }}</strong></div>
        </div>
        <div class="mc-pro-card-actions">
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.actuadores.index') }}">Volver</a>
            <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.actuadores.edit', $actuador) }}">Editar</a>
        </div>
    </div>

    <div class="mc-pro-card">
        <h3>Actuaciones recientes</h3>
        <div class="mc-pro-table-wrap">
            <table class="mc-pro-table">
                <thead><tr><th>Fecha</th><th>Origen</th><th>Estado nuevo</th><th>Motivo</th></tr></thead>
                <tbody>
                @forelse($actuaciones as $actuacion)
                    <tr>
                        <td>{{ $actuacion->ejecutado_en }}</td>
                        <td>{{ $actuacion->origen }}</td>
                        <td><code>{{ json_encode($actuacion->estado_nuevo, JSON_UNESCAPED_UNICODE) }}</code></td>
                        <td><code>{{ json_encode($actuacion->motivo, JSON_UNESCAPED_UNICODE) }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="4">Sin actuaciones registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('panel._partials.pagination', ['paginator' => $actuaciones])
    </div>
</div>
@endsection
