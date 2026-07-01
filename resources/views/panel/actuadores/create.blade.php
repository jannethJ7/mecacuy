@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nuevo actuador')
@section('page-title', 'Nuevo actuador')
@section('page-subtitle', 'Registrar salida de control del ESP32')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos del actuador</h3>
        <p>Registra relés, válvulas, dosificadores o motores vinculados al módulo.</p>
        <form method="POST" action="{{ route('panel.actuadores.store') }}">
            @include('panel.actuadores._form', ['actuador' => new \App\Models\Actuador()])
        </form>
    </div>
</div>
@endsection
