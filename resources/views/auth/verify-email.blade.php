@extends('layouts.auth-dashboard')

@section('title', 'Verificar email')

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
                            <div class="app-form rounded-control">

                                <div class="mb-3 text-center">
                                    <h3 class="text-primary-dark">Verificá tu correo</h3>
                                    <p class="f-s-12 text-secondary">
                                        Gracias por registrarte. Antes de empezar, revisá tu email y hacé clic en el enlace de verificación.
                                        Si no te llegó, podés reenviarlo.
                                    </p>
                                </div>

                                @if (session('status') === 'verification-link-sent')
                                    <div class="alert alert-light-border-primary mb-3">
                                        Se envió un nuevo enlace de verificación al correo que registraste.
                                    </div>
                                @endif

                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                    <form method="POST" action="{{ route('verification.send') }}" class="flex-grow-1">
                                        @csrf
                                        <button type="submit" class="btn btn-light-primary w-100">
                                            Reenviar verificación
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('logout') }}" class="flex-grow-1">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary w-100">
                                            Cerrar sesión
                                        </button>
                                    </form>
                                </div>

                                <div class="text-center mt-3">
                                    <small class="text-secondary">
                                        Tip: mirá también la carpeta de spam/promociones.
                                    </small>
                                </div>

                            </div>
                        </div> {{-- form_container --}}
                    </div> {{-- login-form-container --}}
                </div>
            </div>
        </div>
    </main>
</div>
@endsection