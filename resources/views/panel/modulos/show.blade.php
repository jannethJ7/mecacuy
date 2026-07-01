@extends('layouts.panel')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canRoleManual = in_array($rol, ['admin', 'operador'], true);
    $canManual = $canRoleManual && $modo === 'manual';

    $sensorPorCodigo = function (array $codigos) use ($sensores) {
        $codigos = array_map('strtoupper', $codigos);

        return $sensores->first(function ($sensor) use ($codigos) {
            return in_array(strtoupper((string) $sensor->codigo), $codigos, true);
        });
    };

    $actuadorPorCodigo = function (array $codigos) use ($actuadores) {
        $codigos = array_map('strtoupper', $codigos);

        return $actuadores->first(function ($actuador) use ($codigos) {
            return in_array(strtoupper((string) $actuador->codigo), $codigos, true);
        });
    };

    $tempSensor = $sensorPorCodigo(['S_TEMP', 'TEMP', 'TEMPERATURA']);
    $humSensor = $sensorPorCodigo(['S_HR', 'S_HUM', 'HUMEDAD']);
    $nh3Sensor = $sensorPorCodigo(['S_NH3', 'S_AMONIACO', 'NH3', 'S_AIR']);
    $aguaSensor = $sensorPorCodigo(['S_NIVEL', 'S_AGUA', 'NIVEL_AGUA', 'S_WATER']);

    $nivelSensores = collect([
        25 => $sensorPorCodigo(['S_NIVEL_25', 'NIVEL_25', 'S_WATER_25']),
        50 => $sensorPorCodigo(['S_NIVEL_50', 'NIVEL_50', 'S_WATER_50']),
        75 => $sensorPorCodigo(['S_NIVEL_75', 'NIVEL_75', 'S_WATER_75']),
        100 => $sensorPorCodigo(['S_NIVEL_100', 'NIVEL_100', 'S_WATER_100']),
    ])->filter();

    $temp = optional($tempSensor?->ultimaLectura)->valor ?? null;
    $hum = optional($humSensor?->ultimaLectura)->valor ?? null;
    $nh3 = optional($nh3Sensor?->ultimaLectura)->valor ?? null;
    $agua = optional($aguaSensor?->ultimaLectura)->valor ?? null;

    if ($agua === null && $nivelSensores->isNotEmpty()) {
        $agua = 0;
        foreach ($nivelSensores as $porcentaje => $sensorNivel) {
            $valorNivel = optional($sensorNivel?->ultimaLectura)->valor ?? $sensorNivel?->valor_actual;
            if ((float) ($valorNivel ?? 0) > 0) {
                $agua = max($agua, (int) $porcentaje);
            }
        }
    }

    $opcionesNivelAgua = $nivelSensores->keys()->sort()->values()->all() ?: [25, 50, 75, 100];

    $actAlimento = $actuadorPorCodigo(['D_ALIMENTO_STEP', 'D_ALIMENTO', 'D_COMIDA', 'D_TORNILLO', 'D_CROQUETAS', 'D_FEED']);
    $actAgua = $actuadorPorCodigo(['D_AGUA', 'D_BOMBA', 'D_VALVULA', 'D_WATER']);
    $actFan = $actuadorPorCodigo(['D_FAN', 'D_VENTILADOR']);
    $actCalef = $actuadorPorCodigo(['D_CALEF', 'D_RESISTENCIA', 'D_CALEFACTOR', 'D_HEAT']);

    $formatoValor = function ($valor, int $decimales = 0, string $unidad = '') {
        if ($valor === null || $valor === '') {
            return '--' . ($unidad ? ' ' . $unidad : '');
        }

        return number_format((float) $valor, $decimales) . ($unidad ? ' ' . $unidad : '');
    };

    $momentoSensor = function ($sensor) {
        $fecha = $sensor?->valor_actual_en ?: optional($sensor?->ultimaLectura)->medido_en;
        return $fecha ? \Carbon\Carbon::parse($fecha)->diffForHumans() : 'Sin datos recientes';
    };

    $estadoActuador = function ($actuador) {
        if (!$actuador) {
            return 'No registrado';
        }

        $reportado = data_get($actuador->estado_reportado ?? [], 'on');
        $deseado = data_get($actuador->estado_deseado ?? [], 'on');
        $estado = $reportado ?? $deseado;

        if ($estado === null) {
            return $actuador->activo ? 'Registrado' : 'Inactivo';
        }

        return $estado ? 'ON' : 'OFF';
    };

    $textoBloqueo = function ($actuadorExiste) use ($canRoleManual, $modo) {
        if (!$actuadorExiste) {
            return 'No existe';
        }

        if (!$canRoleManual) {
            return 'Sin permiso';
        }

        return $modo === 'manual' ? 'Bloqueado' : 'Automático';
    };

    $estadoTexto = !$modulo->habilitado ? 'Deshabilitado' : ($estaOnline ? 'Online' : 'Offline');
    $estadoClass = $estaOnline ? 'online' : 'offline';
    $ultimoContactoTexto = $ultimoContacto ? \Carbon\Carbon::parse($ultimoContacto)->diffForHumans() : 'Sin datos';

    $textoEstadoNuevo = function ($actuacion) {
        $on = data_get($actuacion->estado_nuevo ?? [], 'on');

        if ($on === true) {
            return 'encendido';
        }

        if ($on === false) {
            return 'apagado';
        }

        return 'actualizado';
    };

    $iconoActividad = function ($actuacion) {
        $codigo = strtoupper((string) optional($actuacion->actuador)->codigo);
        $tipo = strtoupper((string) optional($actuacion->actuador)->tipo);

        if (str_contains($codigo, 'AGUA') || str_contains($codigo, 'BOMBA') || str_contains($tipo, 'VALVULA')) {
            return 'ph-drop';
        }

        if (str_contains($codigo, 'FAN') || str_contains($codigo, 'VENT')) {
            return 'ph-fan';
        }

        if (str_contains($codigo, 'CALEF') || str_contains($codigo, 'HEAT') || str_contains($codigo, 'RESIST')) {
            return 'ph-fire';
        }

        if (str_contains($codigo, 'ALIMENTO') || str_contains($codigo, 'FEED') || str_contains($codigo, 'CROQUETA')) {
            return 'ph-basket';
        }

        return 'ph-lightning';
    };
@endphp

<div class="jaula-page">

    <div class="jaula-header">
        <div>
            <h1>{{ $modulo->nombre ?: $modulo->codigo }}</h1>

            <div class="jaula-status-row">
                <span class="status-dot {{ $estadoClass }}"></span>
                <span>{{ $estadoTexto }}</span>

                <span class="mode-pill">
                    <i class="ph ph-leaf"></i>
                    Modo: {{ $modo === 'automatico' ? 'Automático' : 'Manual' }}
                </span>

                <span class="contact-pill">
                    <i class="ph ph-wifi-high"></i>
                    Último dato/contacto: {{ $ultimoContactoTexto }}
                </span>

                <span class="contact-pill">
                    <i class="ph ph-clock-countdown"></i>
                    Límite offline: {{ $staleMin }} min
                </span>
            </div>
        </div>

        <div class="jaula-header-actions">

            <a href="{{ route('panel.modulos.index') }}" class="btn-back">
                <i class="ph ph-arrow-left"></i>
                Volver
            </a>
        </div>
    </div>

    <div class="jaula-layout">

        {{-- Vista principal de jaula --}}
        <section class="jaula-card jaula-visual-card">
            <div class="card-title-row">
                <h2>Vista de la jaula</h2>
                <span class="small-info">Módulo {{ $modulo->codigo }}</span>
            </div>

            <div class="jaula-visual">
                <div class="jaula-stage">

                    {{-- Sensor ambiental --}}
                    <div class="jaula-pin pin-top pin-sensor">
                        <div class="pin-card">
                            <span class="pin-icon icon-thermo"></span>
                            <div>
                                <strong>Ambiente</strong>
                                <small>{{ $formatoValor($temp, 1, '°C') }} | {{ $formatoValor($hum, 0, '%') }}</small>
                                <small>{{ $momentoSensor($tempSensor ?: $humSensor) }}</small>
                            </div>
                        </div>
                        <span class="pin-line vertical"></span>
                        <span class="pin-dot"></span>
                    </div>

                    {{-- Tolva --}}
                    <div class="jaula-pin pin-left pin-tolva">
                        <div class="pin-card">
                            <span class="pin-icon icon-wheat"></span>
                            <div>
                                <strong>Tolva</strong>
                                <small>{{ $actAlimento ? $actAlimento->nombre : 'Sin actuador' }}</small>
                                <small>Estado: {{ $estadoActuador($actAlimento) }}</small>
                            </div>
                        </div>
                        <span class="pin-line horizontal"></span>
                        <span class="pin-dot"></span>
                    </div>

                    {{-- Bebedero --}}
                    <div class="jaula-pin pin-right pin-bebedero">
                        <span class="pin-dot"></span>
                        <span class="pin-line horizontal"></span>
                        <div class="pin-card">
                            <span class="pin-icon icon-water"></span>
                            <div>
                                <strong>Bebedero</strong>
                                <small>Nivel: {{ $formatoValor($agua, 0, $aguaSensor?->unidad ?: '%') }}</small>
                                <small>{{ $actAgua ? 'Control: ' . $estadoActuador($actAgua) : 'Sin electroválvula' }}</small>
                            </div>
                        </div>
                    </div>

                    {{-- Ventilador --}}
                    <div class="jaula-pin pin-right pin-ventilador">
                        <span class="pin-dot"></span>
                        <span class="pin-line horizontal"></span>
                        <div class="pin-card">
                            <span class="pin-icon icon-fan"></span>
                            <div>
                                <strong>Ventilador</strong>
                                <small>{{ $actFan ? $actFan->nombre : 'Sin actuador' }}</small>
                                <small>Estado: {{ $estadoActuador($actFan) }}</small>
                            </div>
                        </div>
                    </div>

                    {{-- Resistencia --}}
                    <div class="jaula-pin pin-bottom pin-resistencia">
                        <div class="pin-card">
                            <span class="pin-icon icon-heat"></span>
                            <div>
                                <strong>Resistencia</strong>
                                <small>{{ $actCalef ? $actCalef->nombre : 'Sin actuador' }}</small>
                                <small>Estado: {{ $estadoActuador($actCalef) }}</small>
                            </div>
                        </div>
                        <span class="pin-line vertical"></span>
                        <span class="pin-dot"></span>
                    </div>

                </div>
            </div>
        </section>

        {{-- Controles rápidos --}}
        <aside class="jaula-card quick-controls-card">
            <h2>
                <i class="ph ph-lightning"></i>
                Controles rápidos
            </h2>

            <div class="quick-control quick-control-column">
                <div class="quick-row-main">
                    <div class="quick-icon food">
                        <i class="ph ph-basket"></i>
                    </div>

                    <div class="quick-info">
                        <strong>Alimentación</strong>
                        @if($actAlimento && $canManual)
                    <div class="quick-segment-group quick-segment-food" role="group" aria-label="Control temporizado de alimentación">
                        @foreach([10, 20, 50] as $segundos)
                            <button class="quick-segment js-manual-control"
                                    data-url="{{ route('panel.actuadores.manual', $actAlimento) }}"
                                    data-on="1"
                                    data-accion="pulso"
                                    data-duracion="{{ $segundos }}">
                                {{ $segundos }}s
                            </button>
                        @endforeach

                        <button class="quick-segment quick-segment-stop js-manual-control"
                                data-url="{{ route('panel.actuadores.manual', $actAlimento) }}"
                                data-on="0"
                                data-accion="set_estado">
                            OFF
                        </button>
                    </div>
                @else
                    <button class="quick-btn disabled" disabled>{{ $textoBloqueo((bool) $actAlimento) }}</button>
                @endif
                    </div>
                    
                </div>
            </div>

            <div class="quick-control quick-control-column">
                <div class="quick-row-main">
                    <div class="quick-icon water">
                        <i class="ph ph-drop"></i>
                    </div>

                    <div class="quick-info">
                        <strong>Agua</strong>
                        @if($actAgua && $canManual)
                    <div class="quick-segment-group quick-segment-water" role="group" aria-label="Llenado seguro de agua por nivel">
                        @foreach($opcionesNivelAgua as $nivelObjetivo)
                            <button class="quick-segment js-manual-control"
                                    data-url="{{ route('panel.actuadores.manual', $actAgua) }}"
                                    data-on="1"
                                    data-accion="llenar_hasta"
                                    data-nivel="{{ $nivelObjetivo }}"
                                    data-timeout="90">
                                {{ $nivelObjetivo }}%
                            </button>
                        @endforeach

                        <button class="quick-segment quick-segment-stop js-manual-control"
                                data-url="{{ route('panel.actuadores.manual', $actAgua) }}"
                                data-on="0"
                                data-accion="set_estado">
                            OFF
                        </button>
                    </div>
                @else
                    <button class="quick-btn disabled" disabled>{{ $textoBloqueo((bool) $actAgua) }}</button>
                @endif
                    </div>
                </div>
            </div>

            <div class="quick-control">
                <div class="quick-icon fan">
                    <i class="ph ph-fan"></i>
                </div>

                <div class="quick-info">
                    <strong>Ventilación</strong>
                    <span>{{ $actFan ? $actFan->nombre : 'Ventilador no registrado' }}</span>
                </div>

                @if($actFan && $canManual)
                    @php
                        $fanOn = (bool) data_get($actFan?->estado_deseado ?? [], 'on', false);
                    @endphp

                    <div class="toggle-pack">
                        <input
                            class="tgl tgl-ios js-manual-toggle"
                            id="toggle-fan-{{ $actFan->id }}"
                            type="checkbox"
                            data-url="{{ route('panel.actuadores.manual', $actFan) }}"
                            {{ $fanOn ? 'checked' : '' }}
                        >

                        <label class="tgl-btn" for="toggle-fan-{{ $actFan->id }}"></label>

                        <span class="toggle-text">
                            {{ $fanOn ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                @else
                    <button class="quick-btn disabled" disabled>{{ $textoBloqueo((bool) $actFan) }}</button>
                @endif
            </div>

            <div class="quick-control">
                <div class="quick-icon heat">
                    <i class="ph ph-fire"></i>
                </div>

                <div class="quick-info">
                    <strong>Calefacción</strong>
                    <span>{{ $actCalef ? $actCalef->nombre : 'Resistencia no registrada' }}</span>
                </div>

                @if($actCalef && $canManual)
                    @php
                        $calefOn = (bool) data_get($actCalef?->estado_deseado ?? [], 'on', false);
                    @endphp

                    <div class="toggle-pack">
                        <input
                            class="tgl tgl-ios js-manual-toggle"
                            id="toggle-calef-{{ $actCalef->id }}"
                            type="checkbox"
                            data-url="{{ route('panel.actuadores.manual', $actCalef) }}"
                            {{ $calefOn ? 'checked' : '' }}
                        >

                        <label class="tgl-btn" for="toggle-calef-{{ $actCalef->id }}"></label>

                        <span class="toggle-text">
                            {{ $calefOn ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                @else
                    <button class="quick-btn disabled" disabled>{{ $textoBloqueo((bool) $actCalef) }}</button>
                @endif
            </div>

            <div class="mode-box">
                <span>Modo de funcionamiento</span>

                <div class="mode-buttons">
                    <a href="{{ route('panel.ajustes.sistema') }}" class="{{ $modo === 'automatico' ? 'active' : '' }}">Automático</a>
                    <a href="{{ route('panel.ajustes.sistema') }}" class="{{ $modo === 'manual' ? 'active' : '' }}">Manual</a>
                </div>
            </div>
        </aside>

        {{-- Cards de sensores --}}
        <section class="sensor-strip">
            <div class="sensor-mini-card">
                <i class="ph ph-thermometer"></i>
                <div>
                    <strong>{{ $formatoValor($temp, 1, '°C') }}</strong>
                    <span>Temperatura</span>
                    <small>{{ $tempSensor ? $momentoSensor($tempSensor) : 'Sensor no registrado' }}</small>
                </div>
            </div>

            <div class="sensor-mini-card">
                <i class="ph ph-drop"></i>
                <div>
                    <strong>{{ $formatoValor($hum, 0, '%') }}</strong>
                    <span>Humedad</span>
                    <small>{{ $humSensor ? $momentoSensor($humSensor) : 'Sensor no registrado' }}</small>
                </div>
            </div>

            <div class="sensor-mini-card">
                <i class="ph ph-cloud"></i>
                <div>
                    <strong>{{ $formatoValor($nh3, 0, $nh3Sensor?->unidad ?: 'ppm') }}</strong>
                    <span>Amoniaco / aire</span>
                    <small>{{ $nh3Sensor ? $momentoSensor($nh3Sensor) : 'Sensor no registrado' }}</small>
                </div>
            </div>

            <div class="sensor-mini-card">
                <i class="ph ph-waves"></i>
                <div>
                    <strong>{{ $formatoValor($agua, 0, $aguaSensor?->unidad ?: '%') }}</strong>
                    <span>Nivel de agua</span>
                    <small>{{ $aguaSensor ? $momentoSensor($aguaSensor) : 'Sensor no registrado' }}</small>
                </div>
            </div>
        </section>

        {{-- Gráficas --}}
        <section class="jaula-card charts-card">
            <div class="card-title-row">
                <h2>Últimas lecturas registradas</h2>
                <span class="small-info">Máximo 24 puntos por sensor</span>
            </div>

            <div class="charts-grid">
                @foreach($seriesGraficas as $serie)
                    <div class="chart-box">
                        <div class="chart-head">
                            <span>{{ $serie['titulo'] }}</span>
                            <strong>
                                {{ $serie['actual'] !== null ? number_format((float) $serie['actual'], $serie['decimales']) . ' ' . $serie['unidad'] : '--' }}
                            </strong>
                        </div>

                        @if(count($serie['puntos']))
                            <div class="live-chart" aria-label="{{ $serie['titulo'] }}">
                                @foreach($serie['puntos'] as $punto)
                                    <span class="live-chart-bar"
                                          style="height: {{ number_format($punto['porcentaje'], 2, '.', '') }}%"
                                          title="{{ number_format($punto['valor'], $serie['decimales']) }} {{ $serie['unidad'] }} - {{ $punto['hora'] }}"></span>
                                @endforeach
                            </div>
                        @else
                            <div class="chart-empty">
                                <i class="ph ph-chart-line-down"></i>
                                Sin lecturas para graficar
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', async function (event) {
    const btn = event.target.closest('.js-manual-control');

    if (!btn) return;

    const url = btn.dataset.url;
    const on = btn.dataset.on === '1';

    btn.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                on,
                accion: btn.dataset.accion || 'set_estado',
                duracion_seg: btn.dataset.duracion ? parseInt(btn.dataset.duracion, 10) : undefined,
                nivel_objetivo: btn.dataset.nivel ? parseInt(btn.dataset.nivel, 10) : undefined,
                timeout_seg: btn.dataset.timeout ? parseInt(btn.dataset.timeout, 10) : undefined
            })
        });

        const data = await response.json();

        if (!response.ok || data.ok === false) {
            alert(data.error || 'No se pudo ejecutar el control manual.');
        } else {
            window.location.reload();
        }
    } catch (error) {
        alert('Error de conexión con el servidor.');
    } finally {
        btn.disabled = false;
    }
});
</script>
<script>
document.addEventListener('change', async function (event) {
    const input = event.target.closest('.js-manual-toggle');

    if (!input) return;

    const url = input.dataset.url;
    const checkedAnterior = !input.checked;
    const texto = input.closest('.toggle-pack')?.querySelector('.toggle-text');

    input.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                on: input.checked
            })
        });

        const data = await response.json();

        if (!response.ok || data.ok === false) {
            input.checked = checkedAnterior;
            alert(data.error || 'No se pudo cambiar el estado.');
        }

    } catch (error) {
        input.checked = checkedAnterior;
        alert('Error de conexión con el servidor.');
    } finally {
        if (texto) {
            texto.textContent = input.checked ? 'ON' : 'OFF';
        }

        input.disabled = false;
    }
});
</script>
@endpush
