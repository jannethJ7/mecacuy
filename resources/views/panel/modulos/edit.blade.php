@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar módulo')
@section('page-title', 'Editar módulo')
@section('page-subtitle', 'Actualizar datos del ESP32')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $modulo->nombre ?? $modulo->codigo }}</h3>
        <p>Actualiza los datos operativos del módulo.</p>
        <form method="POST" action="{{ route('panel.modulos.update', $modulo) }}">
            @method('PUT')
            @include('panel.modulos._form')
        </form>
    </div>
</div>
@endsection
