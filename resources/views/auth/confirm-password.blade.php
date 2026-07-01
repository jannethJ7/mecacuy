@extends('layouts.auth-dashboard')

@section('title', 'Confirmar contraseña')

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
                            <form method="POST" action="{{ route('password.confirm') }}" class="app-form rounded-control">
                                @csrf

                                <div class="mb-3 text-center">
                                    <h3 class="text-primary-dark">Confirmar contraseña</h3>
                                    <p class="f-s-12 text-secondary">
                                        Esta es un área segura. Confirmá tu contraseña para continuar.
                                    </p>
                                </div>

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
                                    <label class="form-label" for="password">Contraseña</label>
                                    <input
                                        id="password"
                                        class="form-control"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="********"
                                    >
                                </div>

                                <div>
                                    <button class="btn btn-light-primary w-100" type="submit">
                                        Confirmar
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