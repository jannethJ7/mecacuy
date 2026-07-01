@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Detalle de sensor')
@section('page-title', 'Detalle de sensor')
@section('page-subtitle', 'Lecturas recientes y configuración')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $sensor->nombre }}</h3>
        <p>{{ $sensor->codigo }} · {{ $sensor->modulo->codigo ?? 'Sin módulo' }}</p>
        <div class="mc-pro-mini-grid">
            <div><small>Tipo</small><strong>{{ $sensor->tipo }}</strong></div>
            <div><small>Unidad</small><strong>{{ $sensor->unidad ?? '—' }}</strong></div>
            <div><small>GPIO</small><strong>{{ is_null($sensor->gpio_pin) ? '—' : 'GPIO '.$sensor->gpio_pin }}</strong></div>
            <div><small>Valor actual</small><strong>{{ $sensor->valor_actual ?? '—' }}</strong></div>
            <div><small>Última medición</small><strong>{{ $sensor->valor_actual_en ?? '—' }}</strong></div>
        </div>
        <div class="mc-pro-card-actions">
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.sensores.index') }}">Volver</a>
            <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.sensores.edit', $sensor) }}">Editar</a>
        </div>
    </div>

    <div class="mc-pro-card">
        <h3>Lecturas recientes</h3>
        <div class="mc-pro-table-wrap">
            <table class="mc-pro-table">
                <thead><tr><th>Fecha</th><th>Valor</th><th>Calidad</th></tr></thead>
                <tbody>
                @forelse($lecturas as $lectura)
                    <tr>
                        <td>{{ $lectura->medido_en }}</td>
                        <td>{{ $lectura->valor }} {{ $sensor->unidad }}</td>
                        <td>{{ $lectura->calidad }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Sin lecturas registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('panel._partials.pagination', ['paginator' => $lecturas])
    </div>
</div>
@endsection
