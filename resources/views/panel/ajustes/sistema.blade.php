@extends('layouts.panel')

@include('panel._partials.pro-assets')

@section('title', 'Ajustes del sistema')
@section('page-title', 'Ajustes del sistema')
@section('page-subtitle', 'Modo global, conexión reciente y parámetros generales')

@section('content')
@php
    $modo = old('modo_global', $config['modo_global'] ?? 'manual');
    $esManual = $modo === 'manual';
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Configuración',
        'title' => 'Modo manual / automático',
        'subtitle' => 'Define cómo se comportará el sistema y qué información recibirá el ESP32 en la sincronización.'
    ])

    <section class="mc-pro-mode {{ $esManual ? 'is-manual' : 'is-auto' }}">
        <i class="{{ $esManual ? 'ri-hand' : 'ri-robot-2-line' }}"></i>
        <div>
            <strong>Modo actual: {{ $esManual ? 'MANUAL' : 'AUTOMÁTICO' }}</strong>
            <p>
                {{ $esManual
                    ? 'Los operadores pueden enviar comandos manuales a los actuadores desde el panel.'
                    : 'El control manual queda bloqueado y el sistema queda preparado para trabajar con reglas automáticas.' }}
            </p>
        </div>

        @if(Route::has('panel.actuadores.index'))
            <a href="{{ route('panel.actuadores.index') }}">Ver actuadores</a>
        @endif
    </section>

    <form method="POST" action="{{ route('panel.ajustes.sistema.update') }}">
        @csrf
        @method('PUT')

        <section class="mc-pro-card mc-pro-form-card">
            <div class="mc-pro-card-head">
                <div>
                    <h3>Parámetros generales</h3>
                    <p>Estos valores se guardan en <strong>config_sistema</strong> y se usan por el panel y la API IoT.</p>
                </div>
            </div>

            <div class="mc-pro-form-grid">
                <div class="mc-pro-field">
                    <label for="field_modo_global">Modo global</label>
                    <select id="field_modo_global" name="modo_global" required>
                        <option value="manual" @selected($modo === 'manual')>Manual</option>
                        <option value="automatico" @selected($modo === 'automatico')>Automático</option>
                    </select>
                    <small>
                        En manual se permite controlar actuadores desde el panel. En automático se bloquea el control manual.
                    </small>
                    @error('modo_global') <small>{{ $message }}</small> @enderror
                </div>

                <div class="mc-pro-field">
                    <label for="field_stale_min">Tiempo para considerar módulo online</label>
                    <input id="field_stale_min"
                        type="number"
                        name="stale_min"
                        min="1"
                        max="1440"
                        value="{{ old('stale_min', $config['stale_min'] ?? 10) }}"
                        required
                    >
                    <small>Minutos desde el último contacto del ESP32. Recomendado: 10.</small>
                    @error('stale_min') <small>{{ $message }}</small> @enderror
                </div>

                <div class="mc-pro-field">
                    <label for="field_retention_days">Retención de datos</label>
                    <input id="field_retention_days"
                        type="number"
                        name="retention_days"
                        min="1"
                        max="3650"
                        value="{{ old('retention_days', $config['retention_days'] ?? 30) }}"
                        required
                    >
                    <small>Días de referencia para conservar o analizar datos históricos.</small>
                    @error('retention_days') <small>{{ $message }}</small> @enderror
                </div>

                <div class="mc-pro-field">
                    <label for="field_zona_horaria_default">Zona horaria por defecto</label>
                    <input id="field_zona_horaria_default"
                        name="zona_horaria_default"
                        value="{{ old('zona_horaria_default', $config['zona_horaria_default'] ?? 'America/La_Paz') }}"
                        required
                        placeholder="America/La_Paz"
                    >
                    <small>Se usa para mostrar y programar eventos con hora local.</small>
                    @error('zona_horaria_default') <small>{{ $message }}</small> @enderror
                </div>


                <div class="mc-pro-field">
                    <label for="field_iot_ack_timeout_seg">Espera máxima de ACK IoT</label>
                    <input id="field_iot_ack_timeout_seg"
                        type="number"
                        name="iot_ack_timeout_seg"
                        min="5"
                        max="3600"
                        value="{{ old('iot_ack_timeout_seg', $config['iot_ack_timeout_seg'] ?? 20) }}"
                        required
                    >
                    <small>Segundos que Laravel espera la confirmación del ESP32 antes de reenviar el mismo comando. Recomendado: 20.</small>
                    @error('iot_ack_timeout_seg') <small>{{ $message }}</small> @enderror
                </div>

                <div class="mc-pro-field">
                    <label for="field_iot_max_intentos">Intentos máximos por comando</label>
                    <input id="field_iot_max_intentos"
                        type="number"
                        name="iot_max_intentos"
                        min="1"
                        max="10"
                        value="{{ old('iot_max_intentos', $config['iot_max_intentos'] ?? 3) }}"
                        required
                    >
                    <small>Cuando se agotan los intentos sin ACK, el comando pasa a fallido y se genera alerta.</small>
                    @error('iot_max_intentos') <small>{{ $message }}</small> @enderror
                </div>
            </div>

            <div class="mc-pro-code">
                <strong>Dato enviado al ESP32 en /api/iot/v1/sync</strong>
                <pre>{
  "config": {
    "modo_global": "{{ $modo }}",
    "stale_min": {{ old('stale_min', $config['stale_min'] ?? 10) }},
    "iot_ack_timeout_seg": {{ old('iot_ack_timeout_seg', $config['iot_ack_timeout_seg'] ?? 20) }},
    "iot_max_intentos": {{ old('iot_max_intentos', $config['iot_max_intentos'] ?? 3) }}
  },
  "comando": {
    "nonce": "...",
    "intento": 1,
    "max_intentos": {{ old('iot_max_intentos', $config['iot_max_intentos'] ?? 3) }}
  }
}</pre>
            </div>

            <div class="mc-pro-form-actions">
                @if(Route::has('panel.dashboard'))
                    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.dashboard') }}">Cancelar</a>
                @endif

                <button class="mc-pro-btn mc-pro-btn-primary">
                    <i class="ri-save-3-line"></i> Guardar configuración
                </button>
            </div>
        </section>
    </form>
</div>
@endsection
