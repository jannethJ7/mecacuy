@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nueva regla')
@section('page-title', 'Nueva regla automática')
@section('page-subtitle', 'Configurar condición de control')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos de la regla</h3>
        <p>Define el sensor, actuador, rango objetivo, histéresis y retardo.</p>
        <form method="POST" action="{{ route('panel.automatizacion.reglas.store') }}">
            @include('panel.automatizacion.reglas._form', ['regla' => new \App\Models\ReglaAutomatica()])
        </form>
    </div>
</div>
@endsection
