@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Nueva cámara')
@section('page-title', 'Nueva cámara')
@section('page-subtitle', 'Asociar una fuente de video a una jaula o módulo MecaCuy')
@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')
    <div class="mc-pro-card mc-pro-form-card">
        <h3>Datos de la cámara</h3>
        <p>Laravel administrará la asociación de la cámara; la fuente RTSP se configura de forma segura en MediaMTX.</p>
        <form method="POST" action="{{ route('panel.camaras.store') }}">
            @include('panel.camaras._form')
        </form>
    </div>
</div>
@endsection
