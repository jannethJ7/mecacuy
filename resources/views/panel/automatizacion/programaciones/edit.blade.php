@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar programación')
@section('page-title', 'Editar programación')
@section('page-subtitle', 'Actualizar horario automático')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $programacion->nombre }}</h3>
        <p>{{ $programacion->actuador->codigo ?? 'Actuador' }} · {{ $programacion->modulo->codigo ?? 'Módulo' }}</p>
        <form method="POST" action="{{ route('panel.automatizacion.programaciones.update', $programacion) }}">
            @method('PUT')
            @include('panel.automatizacion.programaciones._form')
        </form>
    </div>
</div>
@endsection
