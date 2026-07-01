{{-- resources/views/profile/edit.blade.php --}}

@extends('layouts.panel')

@section('title', 'Perfil')
@section('page-title', 'Configuración de perfil')
@section('page-subtitle', 'Administra tu información personal, contraseña y seguridad de cuenta')

@section('content')

<div class="mc-profile-grid">

    <div class="mc-card">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="mc-card">
        @include('profile.partials.update-password-form')
    </div>

    @if(($user->rol ?? null) === 'admin')
        <div class="mc-card">
            @include('profile.partials.iot-api-keys')
        </div>
    @endif

    <div class="mc-card mc-card-danger">
        @include('profile.partials.delete-user-form')
    </div>

</div>

@endsection