@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Editar cámara')
@section('page-title', 'Editar cámara')
@section('page-subtitle', 'Actualizar la asociación de video del módulo')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>{{ $camara->nombre }}</h3>
        <p>{{ $camara->codigo }} · {{ $camara->stream_key }}</p>
        <form method="POST" action="{{ route('panel.camaras.update', $camara) }}">
            @method('PUT')
            @include('panel.camaras._form')
        </form>
    </div>
</div>
@endsection
