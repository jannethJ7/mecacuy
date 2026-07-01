@php
    $user = auth()->user();
    $rol = $user->rol ?? 'lector';

    $items = [
        [
            'label' => 'Dashboard',
            'route' => 'panel.dashboard',
            'active' => 'panel.dashboard',
            'icon' => 'ri-dashboard-line',
            'roles' => ['admin', 'operador', 'lector'],
        ],
        [
            'label' => 'Módulos',
            'route' => 'panel.modulos.index',
            'active' => 'panel.modulos.*',
            'icon' => 'ri-cpu-line',
            'roles' => ['admin', 'operador'],
        ],
        [
            'label' => 'Sensores',
            'route' => 'panel.sensores.index',
            'active' => 'panel.sensores.*',
            'icon' => 'ri-temp-hot-line',
            'roles' => ['admin', 'operador'],
        ],
        [
            'label' => 'Actuadores',
            'route' => 'panel.actuadores.index',
            'active' => 'panel.actuadores.*',
            'icon' => 'ri-toggle-line',
            'roles' => ['admin', 'operador'],
        ],
        [
            'label' => 'Lecturas',
            'route' => 'panel.lecturas.index',
            'active' => 'panel.lecturas.*',
            'icon' => 'ri-pulse-line',
            'roles' => ['admin', 'operador', 'lector'],
        ],
        [
            'label' => 'Alertas',
            'route' => 'panel.alertas.index',
            'active' => 'panel.alertas.*',
            'icon' => 'ri-alarm-warning-line',
            'roles' => ['admin', 'operador', 'lector'],
        ],
        [
            'label' => 'Automatización',
            'route' => 'panel.automatizacion.reglas.index',
            'active' => 'panel.automatizacion.*',
            'icon' => 'ri-robot-2-line',
            'roles' => ['admin', 'operador'],
        ],
        [
            'label' => 'Ajustes',
            'route' => 'panel.ajustes.sistema',
            'active' => 'panel.ajustes.*',
            'icon' => 'ri-settings-3-line',
            'roles' => ['admin', 'operador'],
        ],
        [
            'label' => 'Reportes',
            'route' => 'panel.reportes.lecturas',
            'active' => 'panel.reportes.*',
            'icon' => 'ri-bar-chart-box-line',
            'roles' => ['admin', 'operador', 'lector'],
        ],
    ];
@endphp

<aside class="mc-sidebar" id="mcSidebar">
    <div class="mc-sidebar-card">
        <div class="mc-sidebar-brand">
            <a href="{{ Route::has('panel.dashboard') ? route('panel.dashboard') : url('/') }}">
                <img
                    src="{{ asset('dashboard/assets/images/logo/1.png') }}"
                    alt="Mecacuy"
                    class="mc-sidebar-logo"
                >
            </a>

            <button type="button" class="mc-sidebar-close" id="mcSidebarClose" aria-label="Cerrar menú">
                ×
            </button>
        </div>

        <nav class="mc-sidebar-menu">
            @foreach ($items as $item)
                @if (in_array($rol, $item['roles']))
                    @php
                        $exists = Route::has($item['route']);
                        $isActive = request()->routeIs($item['active']);
                    @endphp

                    <a
                        href="{{ $exists ? route($item['route']) : '#' }}"
                        class="mc-nav-item {{ $isActive ? 'active' : '' }} {{ !$exists ? 'disabled' : '' }}"
                    >
                        <span class="mc-nav-icon">
                            <i class="{{ $item['icon'] }}"></i>
                        </span>
                        <span class="mc-nav-label">{{ $item['label'] }}</span>

                        @if (!$exists)
                            <small>pend.</small>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

    </div>
</aside>

<div class="mc-sidebar-overlay" id="mcSidebarOverlay"></div>