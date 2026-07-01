@extends('layouts.auth-dashboard')

@section('title', 'Iniciar sesión')

@section('content')
<div class="app-wrapper d-block">
    <main class="w-100 p-0">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 p-0">
                    <div class="login-form-container">
                        <div class="mb-4">
                            <a class="logo d-inline-block" href="{{ route('home') }}">
                                <img alt="Mecacuy" src="{{ asset('dashboard/assets/images/logo/1.png') }}" width="250">
                            </a>
                        </div>

                        <div class="form_container">
                            <form method="POST" action="{{ route('login') }}" class="app-form rounded-control">
                                @csrf

                                <div class="mb-3 text-center">
                                    <h3 class="text-primary-dark">Inicia sesión en tu cuenta</h3>
                                    <p class="f-s-12 text-secondary">
                                        Ingresá al sistema para acceder al dashboard.
                                    </p>
                                </div>

                                {{-- Session status (ej: link de reset enviado) --}}
                                @if (session('status'))
                                    <div class="alert alert-light-border-primary mb-3">
                                        {{ session('status') }}
                                    </div>
                                @endif

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
                                    <label class="form-label" for="email">Dirección de correo electrónico</label>
                                    <input
                                        id="email"
                                        class="form-control"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                    >
                                    <div class="form-text text">Nunca compartiremos tu correo electrónico con nadie más.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Contraseña</label>
                                    <input
                                        id="password"
                                        class="form-control"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                    >
                                </div>

                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" id="remember_me" type="checkbox" name="remember">
                                        <label class="form-check-label" for="remember_me">Recordarme</label>
                                    </div>

                                    @if (Route::has('password.request'))
                                        <a class="text-secondary text-decoration-underline"
                                           href="{{ route('password.request') }}">
                                            ¿Olvidaste tu contraseña?
                                        </a>
                                    @endif
                                </div>

                                <div>
                                    <button class="btn btn-light-primary w-100" type="submit">
                                        Iniciar sesión
                                    </button>
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