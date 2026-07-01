@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar actuador')
@section('page-title', 'Editar actuador')
@section('page-subtitle', 'Actualizar salida de control')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $actuador->nombre }}</h3>
        <p>{{ $actuador->codigo }} · {{ $actuador->modulo->codigo ?? 'Sin módulo' }}</p>
        <form method="POST" action="{{ route('panel.actuadores.update', $actuador) }}">
            @method('PUT')
            @include('panel.actuadores._form')
        </form>
    </div>
</div>
@endsection
