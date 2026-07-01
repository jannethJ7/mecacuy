@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Dashboard')
@section('page-title', 'Panel Mecacuy')
@section('page-subtitle', 'Monitoreo ambiental, actuadores y alertas por módulo')

@section('content')
@php
    $modulosCol = isset($modulos)
        ? ($modulos instanceof \Illuminate\Pagination\AbstractPaginator ? $modulos->getCollection() : collect($modulos))
        : collect();

    $sensoresCol = isset($sensores) ? collect($sensores) : collect();
    $actuadoresCol = isset($actuadores) ? collect($actuadores) : collect();
    $alertasCol = isset($alertas) ? collect($alertas) : collect();
    $seriesCol = collect($seriesDashboard ?? []);
    $actividadCol = collect($actividadReciente ?? []);

    $modulosTotal = $kpis['modulos_total'] ?? ($modulos_total ?? $modulosCol->count());
    $modulosOnline = $kpis['modulos_online'] ?? ($modulos_online ?? $modulosCol->filter(function($m) {
        return !empty($m->ultimo_contacto) && \Carbon\Carbon::parse($m->ultimo_contacto)->gt(now()->subMinutes(10));
    })->count());
    $alertasAbiertas = $kpis['alertas_abiertas'] ?? ($alertas_abiertas ?? $alertasCol->where('estado', 'abierta')->count());
    $alertasCriticas = $kpis['alertas_criticas'] ?? ($alertas_criticas ?? $alertasCol->where('severidad', 'critico')->count());
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    <section class="mc-pro-hero">
        <div class="mc-pro-hero-content">
            
            <h2>Centro de control inteligente para jaulas modulares</h2>
            

            <div class="mc-pro-hero-actions">
                @if(Route::has('panel.modulos.index'))
                    <a class="mc-pro-btn mc-pro-btn-primary" href="{{ route('panel.modulos.index') }}">
                        <i class="ri-cpu-line"></i> Ver módulos
                    </a>
                @endif

                @if(Route::has('panel.alertas.index'))
                    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.alertas.index') }}">
                        <i class="ri-alarm-warning-line"></i> Revisar alertas
                    </a>
                @endif

                @if(Route::has('panel.ajustes.sistema') && in_array(auth()->user()->rol ?? 'lector', ['admin', 'operador'], true))
                    <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.ajustes.sistema') }}">
                        <i class="ri-settings-3-line"></i> Cambiar modo
                    </a>
                @endif
            </div>
        </div>

        <div class="mc-pro-hero-panel">
            <div class="mc-pro-orbit">
                <span><i class="ri-temp-hot-line"></i></span>
                <span><i class="ri-water-percent-line"></i></span>
                <span><i class="ri-windy-line"></i></span>
                <span><i class="ri-remote-control-line"></i></span>
            </div>
            <strong>{{ $modulosOnline }}/{{ $modulosTotal }}</strong>
            <small>Módulos online</small>
        </div>
    </section>

    <section class="mc-pro-kpis">
        <article class="mc-pro-kpi">
            <span><i class="ri-cpu-line"></i></span>
            <div>
                <small>Módulos</small>
                <strong>{{ $modulosTotal }}</strong>
                <em>Total registrado</em>
            </div>
        </article>

        <article class="mc-pro-kpi is-success">
            <span><i class="ri-wifi-line"></i></span>
            <div>
                <small>Online</small>
                <strong>{{ $modulosOnline }}</strong>
                <em>Contacto reciente</em>
            </div>
        </article>

        <article class="mc-pro-kpi is-warning">
            <span><i class="ri-alarm-warning-line"></i></span>
            <div>
                <small>Alertas abiertas</small>
                <strong>{{ $alertasAbiertas }}</strong>
                <em>Pendientes</em>
            </div>
        </article>

        <article class="mc-pro-kpi is-danger">
            <span><i class="ri-error-warning-line"></i></span>
            <div>
                <small>Críticas</small>
                <strong>{{ $alertasCriticas }}</strong>
                <em>Prioridad alta</em>
            </div>
        </article>
    </section>

    <section class="mc-pro-card">
        <div class="mc-pro-card-head">
            <div>
                <h3>Tendencias recientes</h3>
                <p>Gráficas generadas con las últimas lecturas reales recibidas desde los ESP32.</p>
            </div>
            @if(Route::has('panel.lecturas.index'))
                <a href="{{ route('panel.lecturas.index') }}">Ver histórico</a>
            @endif
        </div>

        <div class="mc-pro-trend-grid">
            @forelse($seriesCol as $clave => $serie)
                @php
                    $valoresSerie = collect($serie['values'] ?? []);
                    $labelsSerie = $serie['labels'] ?? [];
                    $decimalesSerie = (int) ($serie['decimales'] ?? 1);
                    $unidadSerie = $serie['unidad'] ?? '';
                    $actualSerie = $serie['actual'] ?? null;
                    $ultimaSerie = $serie['ultima_en'] ?? null;
                    $sensorActual = $serie['sensor_actual'] ?? ($serie['sensor'] ?? null);
                    $moduloActual = $sensorActual?->modulo ?? null;
                @endphp

                <article class="mc-pro-trend-card {{ $valoresSerie->isEmpty() ? 'is-empty' : '' }}">
                    <div class="mc-pro-trend-top">
                        <span><i class="{{ $serie['icono'] ?? 'ri-line-chart-line' }}"></i></span>
                        <div>
                            <h4>{{ $serie['titulo'] ?? 'Lectura' }}</h4>
                            <small>{{ $serie['subtitulo'] ?? 'Tendencia del sensor' }}</small>
                        </div>
                    </div>

                    <div class="mc-pro-trend-value">
                        <strong>{{ $actualSerie !== null ? number_format((float) $actualSerie, $decimalesSerie) : '—' }}</strong>
                        <span>{{ $unidadSerie }}</span>
                    </div>

                    @if($valoresSerie->isNotEmpty())
                        <canvas
                            class="mc-pro-chart mc-pro-chart-compact"
                            height="170"
                            data-mc-line-chart
                            data-labels='@json($labelsSerie)'
                            data-values='@json($valoresSerie->values()->all())'
                        ></canvas>

                        <div class="mc-pro-trend-meta">
                            <span>Min: {{ number_format((float) ($serie['min'] ?? 0), $decimalesSerie) }} {{ $unidadSerie }}</span>
                            <span>Prom: {{ number_format((float) ($serie['avg'] ?? 0), $decimalesSerie) }} {{ $unidadSerie }}</span>
                            <span>Max: {{ number_format((float) ($serie['max'] ?? 0), $decimalesSerie) }} {{ $unidadSerie }}</span>
                        </div>

                        <small class="mc-pro-trend-foot">
                            {{ $serie['cantidad'] ?? 0 }} lecturas ·
                            {{ $serie['modulos_count'] ?? 0 }} módulo(s) ·
                            Última: {{ $ultimaSerie ? \Carbon\Carbon::parse($ultimaSerie)->diffForHumans() : 'sin fecha' }}
                            @if($moduloActual)
                                · {{ $moduloActual->codigo }}
                            @endif
                        </small>
                    @else
                        <div class="mc-pro-chart-empty">
                            <i class="ri-line-chart-line"></i>
                            <span>Sin lecturas reales todavía</span>
                        </div>
                    @endif
                </article>
            @empty
                @include('panel._partials.empty', [
                    'title' => 'Sin series',
                    'message' => 'Aún no hay sensores activos para generar tendencias.',
                    'icon' => 'ri-line-chart-line'
                ])
            @endforelse
        </div>
    </section>

    <section class="mc-pro-grid-2">
        <div class="mc-pro-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Estado por módulo</h3>
                    <p>Resumen de conectividad y operación</p>
                </div>
                @if(Route::has('panel.modulos.index'))
                    <a href="{{ route('panel.modulos.index') }}">Abrir</a>
                @endif
            </div>

            <div class="mc-pro-list">
                @forelse($modulosCol->take(5) as $modulo)
                @php
                    $online = !empty($modulo->ultimo_contacto) && \Carbon\Carbon::parse($modulo->ultimo_contacto)->gt(now()->subMinutes(10));
                    $moduloId = $modulo->id ?? null;
                @endphp

                <a class="mc-pro-list-row" href="{{ Route::has('panel.modulos.show') && $moduloId ? route('panel.modulos.show', ['modulo' => $moduloId]) : '#' }}">
                    <span class="mc-pro-status {{ $online ? 'is-online' : 'is-offline' }}"></span>
                    <div>
                        <strong>{{ $modulo->nombre ?? $modulo->codigo }}</strong>
                        <small>{{ $modulo->uid ?? 'Sin UID' }}</small>
                    </div>
                    <em>{{ $online ? 'ONLINE' : 'OFFLINE' }}</em>
                </a>
            @empty
                @include('panel._partials.empty', [
                    'title' => 'Sin módulos',
                    'message' => 'Registra tu primer ESP32 para comenzar.',
                    'icon' => 'ri-cpu-line'
                ])
            @endforelse
            </div>
        </div>

        <div class="mc-pro-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Alertas recientes</h3>
                    <p>Eventos que requieren seguimiento</p>
                </div>
                @if(Route::has('panel.alertas.index'))
                    <a href="{{ route('panel.alertas.index') }}">Abrir</a>
                @endif
            </div>

            <div class="mc-pro-list">
                @forelse($alertasCol->take(5) as $alerta)
                @php
                    $alertaId = $alerta->id ?? null;
                    $moduloAlerta = $alerta->modulo ?? null;
                    $fechaAlerta = $alerta->created_at ?? null;
                @endphp

                <a class="mc-pro-list-row" href="{{ Route::has('panel.alertas.show') && $alertaId ? route('panel.alertas.show', ['alerta' => $alertaId]) : '#' }}">
                    <span class="mc-pro-severity {{ $alerta->severidad ?? 'advertencia' }}"></span>
                    <div>
                        <strong>{{ $alerta->mensaje ?? 'Alerta del sistema' }}</strong>
                        <small>
                            {{ $moduloAlerta?->codigo ?? 'Sistema' }} ·
                            {{ $fechaAlerta ? \Carbon\Carbon::parse($fechaAlerta)->diffForHumans() : 'Reciente' }}
                        </small>
                    </div>
                    <em>{{ strtoupper($alerta->estado ?? 'abierta') }}</em>
                </a>
            @empty
                @include('panel._partials.empty', [
                    'title' => 'Sin alertas',
                    'message' => 'El sistema no reporta eventos pendientes.',
                    'icon' => 'ri-shield-check-line'
                ])
            @endforelse
            </div>
        </div>
    </section>

    <section class="mc-pro-card mc-pro-activity-card">
        <div class="mc-pro-card-head">
            <div>
                <h3>Actividad reciente del sistema</h3>
                <p>Línea de tiempo real</p>
            </div>
            @if(Route::has('panel.actuadores.index'))
                <a href="{{ route('panel.actuadores.index') }}">Ver actuadores</a>
            @endif
        </div>

        <div class="mc-pro-activity-feed">
            @forelse($actividadCol as $evento)
                @php
                    $fechaEvento = $evento['fecha'] ?? null;
                    $urlEvento = '#';

                    if (($evento['url_tipo'] ?? null) === 'alerta' && Route::has('panel.alertas.show') && !empty($evento['entidad_id'])) {
                        $urlEvento = route('panel.alertas.show', ['alerta' => $evento['entidad_id']]);
                    } elseif (!empty($evento['modulo_id']) && Route::has('panel.modulos.show')) {
                        $urlEvento = route('panel.modulos.show', ['modulo' => $evento['modulo_id']]);
                    } elseif (Route::has('panel.actuadores.index')) {
                        $urlEvento = route('panel.actuadores.index');
                    }
                @endphp

                <a class="mc-pro-activity-row {{ $evento['severidad'] ?? 'info' }}" href="{{ $urlEvento }}">
                    <span class="mc-pro-activity-icon">
                        <i class="{{ $evento['icono'] ?? 'ri-pulse-line' }}"></i>
                    </span>

                    <div>
                        <strong>{{ $evento['titulo'] ?? 'Evento del sistema' }}</strong>
                        <small>{{ $evento['detalle'] ?? '' }}</small>
                    </div>

                    <em>
                        {{ $fechaEvento ? \Carbon\Carbon::parse($fechaEvento)->diffForHumans() : 'sin fecha' }}
                        @if(!empty($evento['estado']))
                            <span>{{ $evento['estado'] }}</span>
                        @endif
                    </em>
                </a>
            @empty
                @include('panel._partials.empty', [
                    'title' => 'Sin actividad reciente',
                    'message' => 'Aún no hay actuaciones, comandos, alertas ni eventos de auditoría registrados.',
                    'icon' => 'ri-history-line'
                ])
            @endforelse
        </div>
    </section>

</div>
@endsection
