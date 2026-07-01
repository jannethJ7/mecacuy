@php
    $user = auth()->user();
    $rol = $user->rol ?? 'lector';

    $configUrl = '#';

    if (in_array($rol, ['admin', 'operador'], true) && Route::has('panel.ajustes.sistema')) {
        $configUrl = route('panel.ajustes.sistema');
    } elseif (Route::has('profile.edit')) {
        $configUrl = route('profile.edit');
    }

    $alertasUrl = Route::has('panel.alertas.index') ? route('panel.alertas.index') : '#';

    $notificacionesCount = $notificacionesCount
        ?? $alertas_abiertas
        ?? ($kpis['alertas_abiertas'] ?? 0);

    $alertasTop = $alertasTop ?? $alertas ?? collect();
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.panel.head')
</head>

<body class="mc-panel-body">
    <div class="mc-app">
        @include('layouts.partials.panel.sidebar')

        <main class="mc-main">
            <header class="mc-topbar">
                <div class="mc-topbar-left">
                    <button type="button" class="mc-icon-btn mc-menu-toggle" id="mcSidebarToggle" aria-label="Abrir menú">
                        ☰
                    </button>

                    <div class="mc-title-box">
                        <h1>@yield('page-title', 'Dashboard')</h1>
                        <p>@yield('page-subtitle', 'Sistema de monitoreo y automatización Mecacuy')</p>
                    </div>
                </div>

                <div class="mc-topbar-actions">
                    <div class="mc-dropdown" data-dropdown>
                        <button type="button" class="mc-icon-btn mc-notification-btn" data-dropdown-button aria-label="Notificaciones">
                            🔔

                            @if($notificacionesCount > 0)
                                <span>{{ $notificacionesCount }}</span>
                            @endif
                        </button>

                        <div class="mc-dropdown-menu mc-notification-menu">
                            <div class="mc-dropdown-header">
                                <strong>Notificaciones</strong>
                                <a href="{{ $alertasUrl }}">Ver todas</a>
                            </div>

                            <div class="mc-notification-list">
                                @forelse($alertasTop->take(4) as $alerta)
                                    <a href="{{ $alertasUrl }}" class="mc-notification-item">
                                        <span class="mc-dot"></span>
                                        <div>
                                            <strong>{{ $alerta->titulo ?? $alerta->mensaje ?? 'Alerta del sistema' }}</strong>
                                            <small>{{ $alerta->created_at ?? 'Reciente' }}</small>
                                        </div>
                                    </a>
                                @empty
                                    <div class="mc-empty-notification">
                                        No hay notificaciones nuevas.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="mc-dropdown" data-dropdown>
                        <button type="button" class="mc-user-button" data-dropdown-button>
                            <span class="mc-user-avatar">
                                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                            </span>

                            <span class="mc-user-text">
                                <strong>{{ $user->name ?? 'Usuario' }}</strong>
                                <small>{{ ucfirst($rol) }}</small>
                            </span>

                            <span class="mc-chevron">⌄</span>
                        </button>

                        <div class="mc-dropdown-menu mc-user-menu">
                            <div class="mc-user-menu-header">
                                <span class="mc-user-avatar big">
                                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                </span>

                                <div>
                                    <strong>{{ $user->name ?? 'Usuario' }}</strong>
                                    <small>{{ $user->email ?? 'Sin correo' }}</small>
                                </div>
                            </div>

                            <a href="{{ $configUrl }}" class="mc-dropdown-link">
                                <span>⚙</span>
                                Configuración
                            </a>

                            @if(($rol ?? null) === 'admin' && Route::has('profile.edit'))
                                <a href="{{ route('profile.edit') }}" class="mc-dropdown-link">
                                    <span>🔑</span>
                                    Perfil y API keys
                                </a>
                            @endif

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button type="submit" class="mc-dropdown-link danger">
                                    <span>↵</span>
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <section class="mc-content">
                @yield('content')
            </section>
        </main>
    </div>

    <script src="{{ asset('dashboard/assets/js/mecacuy/panel.js') }}"></script>

    @stack('scripts')
</body>
</html>