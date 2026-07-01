@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar regla')
@section('page-title', 'Editar regla automática')
@section('page-subtitle', 'Actualizar lógica de control')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $regla->nombre }}</h3>
        <p>{{ $regla->sensor->codigo ?? 'Sensor' }} → {{ $regla->actuador->codigo ?? 'Actuador' }}</p>
        <form method="POST" action="{{ route('panel.automatizacion.reglas.update', $regla) }}">
            @method('PUT')
            @include('panel.automatizacion.reglas._form')
        </form>
    </div>
</div>
@endsection
