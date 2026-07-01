@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Alertas')
@section('page-title', 'Alertas')
@section('page-subtitle', 'Eventos críticos, advertencias y seguimiento operativo')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canOperateAlerts = in_array($rol, ['admin', 'operador'], true);
    $items = $alertas instanceof \Illuminate\Pagination\AbstractPaginator ? $alertas->getCollection() : collect($alertas ?? []);

    $severityOptions = [
        '' => 'Todas',
        'critico' => 'Críticas',
        'advertencia' => 'Advertencias',
        'info' => 'Informativas',
    ];

    $stateOptions = [
        '' => 'Todos los estados',
        'abierta' => 'Abiertas',
        'reconocida' => 'Reconocidas',
        'cerrada' => 'Cerradas',
    ];
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Seguridad operativa',
        'title' => 'Centro de alertas',
    ])

    @if($canOperateAlerts && Route::has('panel.alertas.evaluar'))
        <section class="mc-pro-card mc-pro-alert-eval">
            <div>
                <strong>Evaluación automática</strong>
                <p>Revisa temperatura, humedad, calidad de aire, sensores sin datos, módulos offline y comandos fallidos.</p>
            </div>
            <form method="POST" action="{{ route('panel.alertas.evaluar') }}">
                @csrf
                <button class="mc-pro-btn mc-pro-btn-primary" type="submit">
                    <i class="ri-pulse-line"></i> Evaluar ahora
                </button>
            </form>
        </section>
    @endif

    <section class="mc-pro-datatable-card mc-pro-alerts-datatable">
        <form method="GET" action="{{ route('panel.alertas.index') }}" class="mc-pro-datatable-top is-multiline">
            <label class="mc-pro-page-size">
                <span>Mostrar</span>
                <select name="per_page" data-mc-native-select onchange="this.form.submit()">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? 10) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <span>registros</span>
            </label>

            <div class="mc-pro-datatable-filters">
                <select name="severidad" data-mc-native-select onchange="this.form.submit()">
                    @foreach($severityOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('severidad', '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="estado" data-mc-native-select onchange="this.form.submit()">
                    @foreach($stateOptions as $value => $label)
                        <option value="{{ $value }}" @selected(request('estado', '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="modulo_id" data-mc-native-select onchange="this.form.submit()">
                    <option value="">Todos los módulos</option>
                    @foreach(($modulos ?? []) as $modulo)
                        <option value="{{ $modulo->id }}" @selected((string) request('modulo_id', '') === (string) $modulo->id)>{{ $modulo->codigo }}</option>
                    @endforeach
                </select>
            </div>

            <label class="mc-pro-datatable-search">
                <span>Buscar:</span>
                <input type="search" name="q" value="{{ $q ?? request('q') }}" placeholder="Mensaje, módulo, sensor...">
            </label>

            @if(request()->hasAny(['q', 'estado', 'severidad', 'modulo_id', 'per_page']))
                <a class="mc-pro-btn mc-pro-btn-ghost mc-pro-btn-sm" href="{{ route('panel.alertas.index') }}">
                    <i class="ri-close-line"></i> Limpiar
                </a>
            @endif
        </form>

        <div class="mc-pro-datatable-wrap">
            <table class="mc-pro-datatable mc-pro-alert-clean-table">
                <thead>
                    <tr>
                        <th>Alerta</th>
                        <th>Severidad</th>                        
                        <th>Módulo</th>
                        <th>Origen</th>
                        <th>Fecha</th>
                        <th class="is-actions">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $alerta)
                        @php
                            $severity = $alerta->severidad ?? 'advertencia';
                            $estado = $alerta->estado ?? 'abierta';
                            $moduloCodigo = $alerta->modulo->codigo ?? 'Sin módulo';
                            $sensorCodigo = $alerta->sensor->codigo ?? null;
                            $actuadorCodigo = $alerta->actuador->codigo ?? null;
                            $fecha = $alerta->created_at ? \Carbon\Carbon::parse($alerta->created_at)->format('d/m/Y H:i') : '—';

                            $severityClass = match($severity) {
                                'critico' => 'is-danger',
                                'info' => 'is-new',
                                default => 'is-pending',
                            };

                            $estadoClass = match($estado) {
                                'cerrada' => 'is-completed',
                                'reconocida' => 'is-progress',
                                default => 'is-danger',
                            };

                            $icon = match($severity) {
                                'critico' => 'ri-alarm-warning-line',
                                'info' => 'ri-information-line',
                                default => 'ri-error-warning-line',
                            };
                        @endphp

                        <tr class="mc-pro-alert-row is-{{ $severity }}">
                            <td data-label="Alerta">
                                <div class="mc-pro-table-identity">
                                    <span class="mc-pro-row-icon {{ $severity === 'critico' ? 'is-danger' : '' }}"><i class="{{ $icon }}"></i></span>
                                    <div>
                                        <strong>{{ $alerta->mensaje }}</strong>
                                        <small>{{ $sensorCodigo ? 'Sensor ' . $sensorCodigo : ($actuadorCodigo ? 'Actuador ' . $actuadorCodigo : 'Sistema') }}</small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Severidad"><span class="mc-pro-status-pill {{ $severityClass }}">{{ strtoupper($severity) }}</span></td>
                            <td data-label="Módulo"><strong>{{ $moduloCodigo }}</strong></td>
                            <td data-label="Origen">
                                <div class="mc-pro-origin-stack">
                                    @if($sensorCodigo)<span><i class="ri-radar-line"></i> {{ $sensorCodigo }}</span>@endif
                                    @if($actuadorCodigo)<span><i class="ri-toggle-line"></i> {{ $actuadorCodigo }}</span>@endif
                                    @unless($sensorCodigo || $actuadorCodigo)<span><i class="ri-cpu-line"></i> Sistema</span>@endunless
                                </div>
                            </td>
                            <td data-label="Fecha" class="is-date-ok">{{ $fecha }}</td>
                            <td data-label="Acción" class="is-actions">
                                <div class="mc-pro-square-actions">
                                    @if(Route::has('panel.alertas.show'))
                                        <a class="mc-pro-square-btn is-view" href="{{ route('panel.alertas.show', $alerta) }}" title="Ver alerta">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    @endif

                                    @if($canOperateAlerts && $estado === 'abierta' && Route::has('panel.alertas.reconocer'))
                                        <form method="POST" action="{{ route('panel.alertas.reconocer', $alerta) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="mc-pro-square-btn is-ok" type="submit" title="Reconocer alerta">
                                                <i class="ri-check-line"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if($canOperateAlerts && $estado !== 'cerrada' && Route::has('panel.alertas.cerrar'))
                                        <form method="POST" action="{{ route('panel.alertas.cerrar', $alerta) }}" data-mc-confirm="¿Cerrar esta alerta?">
                                            @csrf
                                            @method('PATCH')
                                            <button class="mc-pro-square-btn is-close" type="submit" title="Cerrar alerta">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                @include('panel._partials.empty', [
                                    'title' => 'No hay alertas',
                                    'message' => 'El sistema no registra eventos para los filtros seleccionados.',
                                    'icon' => 'ri-shield-check-line'
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('panel._partials.pagination', ['paginator' => $alertas])
    </section>
</div>
@endsection
