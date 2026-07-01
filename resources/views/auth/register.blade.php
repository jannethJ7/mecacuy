@extends('layouts.auth-dashboard')

@section('title', 'Crear cuenta')

@section('content')
<div class="app-wrapper d-block">
    <main class="w-100 p-0">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 p-0">
                    <div class="login-form-container">
                        <div class="mb-4">
                            <a class="logo d-inline-block" href="">
                                <img alt="Mecacuy" src="{{ asset('dashboard/assets/images/logo/1.png') }}" width="250">
                            </a>
                        </div>

                        <div class="form_container">
                            <form method="POST" action="{{ route('register') }}" class="app-form rounded-control">
                                @csrf

                                <div class="mb-3 text-center">
                                    <h3 class="text-primary-dark">Crear cuenta</h3>
                                    <p class="f-s-12 text-secondary">
                                        Registrate para acceder al sistema y al dashboard.
                                    </p>
                                </div>

                                {{-- Errores --}}
                                @if ($errors->any())
                                    <div class="alert alert-light-border-primary mb-3">
                                        <ul class="mg-0" style="padding-left: 18px;">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label" for="name">Nombre</label>
                                    <input
                                        id="name"
                                        class="form-control"
                                        type="text"
                                        name="name"
                                        value="{{ old('name') }}"
                                        required
                                        autofocus
                                        autocomplete="name"
                                        placeholder="Tu nombre"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="email">Email</label>
                                    <input
                                        id="email"
                                        class="form-control"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autocomplete="username"
                                        placeholder="tu@email.com"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Contraseña</label>
                                    <input
                                        id="password"
                                        class="form-control"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="********"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                                    <input
                                        id="password_confirmation"
                                        class="form-control"
                                        type="password"
                                        name="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        placeholder="********"
                                    >
                                </div>

                                <div class="d-grid">
                                    <button class="btn btn-light-primary w-100" type="submit">
                                        Registrarme
                                    </button>
                                </div>

                                <div class="text-center mt-3">
                                    <a class="text-secondary text-decoration-underline" href="{{ route('login') }}">
                                        ¿Ya tenés cuenta? Iniciar sesión
                                    </a>
                                </div>

                            </form>
                        </div> {{-- form_container --}}
                    </div> {{-- login-form-container --}}
                </div>
            </div>
        </div>
    </main>
</div>
@endsection