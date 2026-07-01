@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nuevo sensor')
@section('page-title', 'Nuevo sensor')
@section('page-subtitle', 'Asociar una variable de medición a un módulo')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos del sensor</h3>
        <p>Registra el sensor físico o lógico vinculado al ESP32.</p>
        <form method="POST" action="{{ route('panel.sensores.store') }}">
            @include('panel.sensores._form', ['sensor' => new \App\Models\Sensor()])
        </form>
    </div>
</div>
@endsection
