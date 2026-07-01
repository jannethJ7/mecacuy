@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nuevo módulo')
@section('page-title', 'Nuevo módulo')
@section('page-subtitle', 'Registrar un nuevo ESP32 en el sistema')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos del módulo</h3>
        <p>Completa la identificación del ESP32. Luego podrás asociar sensores, actuadores y reglas.</p>
        <form method="POST" action="{{ route('panel.modulos.store') }}">
            @include('panel.modulos._form', ['modulo' => new \App\Models\Modulo()])
        </form>
    </div>
</div>
@endsection
