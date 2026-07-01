@extends('layouts.auth-dashboard')

@section('title', 'Recuperar contraseña')

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
                            <form method="POST" action="{{ route('password.email') }}" class="app-form rounded-control">
                                @csrf

                                <div class="mb-3 text-center">
                                    <h3 class="text-primary-dark">¿Olvidaste tu contraseña?</h3>
                                    <p class="f-s-12 text-secondary">
                                        Ingresá tu email y te enviaremos un enlace para restablecer tu contraseña.
                                    </p>
                                </div>

                                {{-- Session status (cuando envía el link) --}}
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
                                    <label class="form-label" for="email">Email</label>
                                    <input
                                        id="email"
                                        class="form-control"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="tu@email.com"
                                    >
                                    <div class="form-text text">
                                        Revisá también tu carpeta de spam si no te llega.
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button class="btn btn-light-primary w-100" type="submit">
                                        Enviar link de recuperación
                                    </button>
                                </div>

                                <div class="text-center mt-3">
                                    <a class="text-secondary text-decoration-underline" href="{{ route('login') }}">
                                        Volver al login
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