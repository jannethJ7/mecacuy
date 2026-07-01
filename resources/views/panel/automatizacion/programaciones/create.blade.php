@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nueva programación')
@section('page-title', 'Nueva programación')
@section('page-subtitle', 'Crear horario automático')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos de programación</h3>
        <p>Define días, hora, duración y actuador a ejecutar.</p>
        <form method="POST" action="{{ route('panel.automatizacion.programaciones.store') }}">
            @include('panel.automatizacion.programaciones._form', ['programacion' => new \App\Models\Programacion()])
        </form>
    </div>
</div>
@endsection
