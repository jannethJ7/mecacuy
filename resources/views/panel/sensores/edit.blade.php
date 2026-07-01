@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar sensor')
@section('page-title', 'Editar sensor')
@section('page-subtitle', 'Actualizar datos del sensor')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $sensor->nombre }}</h3>
        <p>{{ $sensor->codigo }} · {{ $sensor->modulo->codigo ?? 'Sin módulo' }}</p>
        <form method="POST" action="{{ route('panel.sensores.update', $sensor) }}">
            @method('PUT')
            @include('panel.sensores._form')
        </form>
    </div>
</div>
@endsection
