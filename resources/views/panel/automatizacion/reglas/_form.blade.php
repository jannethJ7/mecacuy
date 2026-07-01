@csrf

@php
    $payloadActual = old('payload_json', isset($regla->payload) && $regla->payload ? json_encode($regla->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '');
    $tipoSugerido = 'rango';
    if (is_array($regla->payload ?? null) && (($regla->payload['accion'] ?? null) === 'llenar_hasta')) {
        $tipoSugerido = 'agua_nivel';
    } elseif (($regla->objetivo_max ?? null) !== null && ($regla->objetivo_min ?? null) === null) {
        $tipoSugerido = 'maximo';
    } elseif (($regla->objetivo_min ?? null) !== null && ($regla->objetivo_max ?? null) === null) {
        $tipoSugerido = 'minimo';
    }
@endphp

<div class="mc-auto-wizard" data-auto-wizard="regla">
    <div class="mc-auto-explain-card">
        <div class="mc-auto-explain-icon"><i class="ri-robot-2-line"></i></div>
        <div>
            <span class="mc-auto-kicker">Asistente de reglas automáticas</span>
            <h3>Una regla responde a una condición de sensor</h3>
            <p>
                Usa esta pantalla cuando quieras que una lectura active o apague un actuador. Para comida por horarios, usa <strong>Programaciones</strong>, no reglas.
            </p>
        </div>
        @if(Route::has('panel.automatizacion.programaciones.create'))
            <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.automatizacion.programaciones.create') }}?preset=alimentacion">
                Ir a comida por horario
            </a>
        @endif
    </div>

    <input type="hidden" id="field_tipo_regla_visual" data-regla-tipo value="{{ $tipoSugerido }}">

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>1</span>
            <div>
                <h3>Elige el caso de uso</h3>
                <p>Selecciona una situación real del módulo. El formulario se acomoda según tu elección.</p>
            </div>
        </div>

        <div class="mc-auto-choice-grid" role="radiogroup" aria-label="Tipo de regla automática">
            <button type="button" class="mc-auto-choice" data-regla-card="maximo" data-preset="temperatura_alta">
                <i class="ri-temp-hot-line"></i>
                <strong>Ventilar por calor</strong>
                <span>Si la temperatura supera un máximo, encender ventilador.</span>
            </button>
            <button type="button" class="mc-auto-choice" data-regla-card="minimo" data-preset="temperatura_baja">
                <i class="ri-temp-cold-line"></i>
                <strong>Calentar por frío</strong>
                <span>Si la temperatura baja demasiado, encender calefacción.</span>
            </button>
            <button type="button" class="mc-auto-choice" data-regla-card="rango" data-preset="rango_humedad">
                <i class="ri-contrast-drop-2-line"></i>
                <strong>Mantener en rango</strong>
                <span>Si humedad o aire salen del rango, activar corrección.</span>
            </button>
            <button type="button" class="mc-auto-choice" data-regla-card="agua_nivel" data-preset="agua_segura">
                <i class="ri-water-percent-line"></i>
                <strong>Agua segura</strong>
                <span>Si falta nivel, abrir válvula hasta el porcentaje elegido.</span>
            </button>
        </div>
    </section>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>2</span>
            <div>
                <h3>Indica qué se mide y qué se controla</h3>
                <p>Ambos deben pertenecer al mismo módulo para que el ESP32 reciba el comando correcto.</p>
            </div>
        </div>

        <div class="mc-pro-form-grid">
            <div class="mc-pro-field">
                <label for="field_nombre">Nombre de la regla</label>
                <input id="field_nombre" name="nombre" value="{{ old('nombre', $regla->nombre ?? '') }}" required placeholder="Ej.: Ventilar por temperatura alta" data-auto-name>
                <small>Usa un nombre que explique la acción.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_modulo_id">Módulo</label>
                <select id="field_modulo_id" name="modulo_id" required data-auto-modulo>
                    <option value="">Seleccionar módulo</option>
                    @foreach(($modulos ?? []) as $modulo)
                        <option value="{{ $modulo->id }}" @selected(old('modulo_id', $regla->modulo_id ?? '') == $modulo->id)>
                            {{ $modulo->codigo }} · {{ $modulo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mc-pro-field">
                <label for="field_sensor_id">Sensor que se evalúa</label>
                <select id="field_sensor_id" name="sensor_id" required data-regla-sensor>
                    <option value="">Seleccionar sensor</option>
                    @foreach(($sensores ?? []) as $sensor)
                        @php
                            $codigoSensor = strtoupper((string) $sensor->codigo);
                            $grupoSensor = str_contains($codigoSensor, 'NIVEL') ? 'agua_nivel' : 'general';
                        @endphp
                        <option value="{{ $sensor->id }}" data-modulo="{{ $sensor->modulo_id }}" data-codigo="{{ $sensor->codigo }}" data-grupo="{{ $grupoSensor }}" @selected(old('sensor_id', $regla->sensor_id ?? '') == $sensor->id)>
                            {{ $sensor->modulo->codigo ?? 'Módulo' }} · {{ $sensor->codigo }} · {{ $sensor->nombre }}
                        </option>
                    @endforeach
                </select>
                <small data-regla-sensor-help>Ej.: S_TEMP para temperatura, S_NIVEL_50 para agua al 50 %.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_actuador_id">Actuador que se controla</label>
                <select id="field_actuador_id" name="actuador_id" required data-regla-actuador>
                    <option value="">Seleccionar actuador</option>
                    @foreach(($actuadores ?? []) as $actuador)
                        @php
                            $codigoAct = strtoupper((string) $actuador->codigo);
                            $tipoAct = strtolower((string) $actuador->tipo);
                            $grupoAct = str_contains($codigoAct, 'AGUA') || str_contains($codigoAct, 'WATER') || str_contains($codigoAct, 'VALVULA') || str_contains($tipoAct, 'valvula') ? 'agua_nivel' : 'general';
                        @endphp
                        <option value="{{ $actuador->id }}" data-modulo="{{ $actuador->modulo_id }}" data-codigo="{{ $actuador->codigo }}" data-grupo="{{ $grupoAct }}" @selected(old('actuador_id', $regla->actuador_id ?? '') == $actuador->id)>
                            {{ $actuador->modulo->codigo ?? 'Módulo' }} · {{ $actuador->codigo }} · {{ $actuador->nombre }}
                        </option>
                    @endforeach
                </select>
                <small data-regla-actuador-help>Ej.: D_FAN para ventilador, D_CALEF para calefacción, D_AGUA para válvula.</small>
            </div>
        </div>
    </section>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>3</span>
            <div>
                <h3>Define la condición de trabajo</h3>
                <p data-regla-ayuda>Ejemplo: si la temperatura supera 26 °C, se enciende el ventilador.</p>
            </div>
        </div>

        <div class="mc-auto-rule-preview" data-rule-preview>
            <i class="ri-flashlight-line"></i>
            <span>Cuando se cumpla la condición, se enviará un comando al ESP32.</span>
        </div>

        <div class="mc-pro-form-grid">
            <div class="mc-pro-field" data-regla-field="min">
                <label for="field_objetivo_min">Valor mínimo permitido</label>
                <input id="field_objetivo_min" type="number" step="0.001" name="objetivo_min" value="{{ old('objetivo_min', $regla->objetivo_min ?? '') }}" placeholder="Ej.: 18">
                <small>Se usa para calefacción o límite inferior del rango.</small>
            </div>

            <div class="mc-pro-field" data-regla-field="max">
                <label for="field_objetivo_max">Valor máximo permitido</label>
                <input id="field_objetivo_max" type="number" step="0.001" name="objetivo_max" value="{{ old('objetivo_max', $regla->objetivo_max ?? '') }}" placeholder="Ej.: 26">
                <small>Se usa para ventilación o límite superior del rango.</small>
            </div>

            <div class="mc-pro-field" data-regla-field="nivel">
                <label for="field_nivel_objetivo_auto">Nivel objetivo de agua</label>
                <select id="field_nivel_objetivo_auto" data-regla-nivel>
                    @foreach([25, 50, 75, 100] as $nivel)
                        <option value="{{ $nivel }}">{{ $nivel }} %</option>
                    @endforeach
                </select>
                <small>Para 50 %, selecciona el sensor S_NIVEL_50 y el actuador D_AGUA.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_histeresis">Margen de seguridad / histéresis</label>
                <input id="field_histeresis" type="number" step="0.001" name="histeresis" value="{{ old('histeresis', $regla->histeresis ?? 0) }}" placeholder="Ej.: 1">
                <small>Evita que el actuador encienda y apague muchas veces cerca del límite.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_retardo_seg">Confirmar condición durante (s)</label>
                <input id="field_retardo_seg" type="number" min="0" name="retardo_seg" value="{{ old('retardo_seg', $regla->retardo_seg ?? 0) }}" placeholder="Ej.: 10">
                <small>La condición debe mantenerse este tiempo antes de accionar.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_prioridad">Prioridad</label>
                <input id="field_prioridad" type="number" name="prioridad" value="{{ old('prioridad', $regla->prioridad ?? 100) }}">
                <small>Menor número = se evalúa antes.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_activo">Estado de la regla</label>
                <select id="field_activo" name="activo">
                    <option value="1" @selected(old('activo', $regla->activo ?? 1) == 1)>Activa</option>
                    <option value="0" @selected(old('activo', $regla->activo ?? 1) == 0)>Inactiva</option>
                </select>
            </div>
        </div>
    </section>

    <details class="mc-auto-advanced">
        <summary><i class="ri-code-s-slash-line"></i> Configuración avanzada del comando</summary>
        <div class="mc-pro-field mc-pro-field-full">
            <label for="field_payload_json">Payload JSON</label>
            <textarea id="field_payload_json" name="payload_json" rows="6" placeholder='{"estado_activo":{"on":true},"estado_inactivo":{"on":false}}'>{{ $payloadActual }}</textarea>
            <small>Déjalo vacío en reglas normales. Para agua, el asistente genera automáticamente <code>llenar_hasta</code> con timeout.</small>
        </div>
    </details>

</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.reglas.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar regla automática
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wizard = document.querySelector('[data-auto-wizard="regla"]');
    if (!wizard) return;

    const tipo = wizard.querySelector('[data-regla-tipo]');
    const cards = [...wizard.querySelectorAll('[data-regla-card]')];
    const minField = wizard.querySelector('[data-regla-field="min"]');
    const maxField = wizard.querySelector('[data-regla-field="max"]');
    const nivelField = wizard.querySelector('[data-regla-field="nivel"]');
    const minInput = wizard.querySelector('#field_objetivo_min');
    const maxInput = wizard.querySelector('#field_objetivo_max');
    const nivelInput = wizard.querySelector('[data-regla-nivel]');
    const payload = wizard.querySelector('#field_payload_json');
    const ayuda = wizard.querySelector('[data-regla-ayuda]');
    const preview = wizard.querySelector('[data-rule-preview] span');
    const nombre = wizard.querySelector('[data-auto-name]');
    const modulo = wizard.querySelector('[data-auto-modulo]');
    const sensor = wizard.querySelector('[data-regla-sensor]');
    const actuador = wizard.querySelector('[data-regla-actuador]');

    function selectOptionByCode(select, codes) {
        if (!select) return;
        const wanted = codes.map((c) => String(c).toUpperCase());
        const opt = [...select.options].find((option) => wanted.includes(String(option.dataset.codigo || '').toUpperCase()));
        if (opt && !opt.hidden) select.value = opt.value;
    }

    function filtrarPorModulo() {
        const moduloId = modulo?.value || '';
        [sensor, actuador].forEach((select) => {
            if (!select) return;
            [...select.options].forEach((opt) => {
                if (!opt.value) return;
                opt.hidden = moduloId && opt.dataset.modulo !== moduloId;
            });
            if (select.selectedOptions[0]?.hidden) select.value = '';
        });
    }

    function filtrarPorTipo() {
        const value = tipo?.value || 'rango';
        if (sensor) {
            [...sensor.options].forEach((opt) => {
                if (!opt.value) return;
                const grupo = opt.dataset.grupo || 'general';
                opt.hidden = (modulo?.value && opt.dataset.modulo !== modulo.value) || (value === 'agua_nivel' ? grupo !== 'agua_nivel' : grupo === 'agua_nivel');
            });
        }
        if (actuador) {
            [...actuador.options].forEach((opt) => {
                if (!opt.value) return;
                const grupo = opt.dataset.grupo || 'general';
                opt.hidden = (modulo?.value && opt.dataset.modulo !== modulo.value) || (value === 'agua_nivel' ? grupo !== 'agua_nivel' : grupo === 'agua_nivel');
            });
        }
    }

    function aplicarPreset(preset) {
        if (!preset) return;
        if (preset === 'temperatura_alta') {
            tipo.value = 'maximo';
            if (!nombre.value) nombre.value = 'Ventilar por temperatura alta';
            if (!maxInput.value) maxInput.value = '26';
            if (!wizard.querySelector('#field_histeresis').value) wizard.querySelector('#field_histeresis').value = '1';
            selectOptionByCode(sensor, ['S_TEMP']);
            selectOptionByCode(actuador, ['D_FAN']);
        }
        if (preset === 'temperatura_baja') {
            tipo.value = 'minimo';
            if (!nombre.value) nombre.value = 'Calefacción por temperatura baja';
            if (!minInput.value) minInput.value = '18';
            if (!wizard.querySelector('#field_histeresis').value) wizard.querySelector('#field_histeresis').value = '1';
            selectOptionByCode(sensor, ['S_TEMP']);
            selectOptionByCode(actuador, ['D_CALEF', 'D_HEAT']);
        }
        if (preset === 'rango_humedad') {
            tipo.value = 'rango';
            if (!nombre.value) nombre.value = 'Control por humedad fuera de rango';
            if (!minInput.value) minInput.value = '45';
            if (!maxInput.value) maxInput.value = '75';
            selectOptionByCode(sensor, ['S_HR']);
            selectOptionByCode(actuador, ['D_FAN']);
        }
        if (preset === 'agua_segura') {
            tipo.value = 'agua_nivel';
            if (!nombre.value) nombre.value = 'Llenar agua hasta nivel seguro';
            if (nivelInput) nivelInput.value = '50';
            selectOptionByCode(sensor, ['S_NIVEL_50']);
            selectOptionByCode(actuador, ['D_AGUA', 'D_WATER', 'D_VALVULA']);
        }
    }

    function actualizarRegla() {
        const value = tipo?.value || 'rango';
        cards.forEach((card) => card.classList.toggle('is-selected', card.dataset.reglaCard === value));
        if (minField) minField.style.display = ['minimo', 'rango'].includes(value) ? '' : 'none';
        if (maxField) maxField.style.display = ['maximo', 'rango'].includes(value) ? '' : 'none';
        if (nivelField) nivelField.style.display = value === 'agua_nivel' ? '' : 'none';

        if (value === 'maximo') {
            if (minInput) minInput.value = '';
            if (ayuda) ayuda.textContent = 'Cuando el valor supere el máximo, se enciende el actuador. Al bajar con histéresis, se apaga.';
            if (preview) preview.textContent = `Si el sensor supera ${maxInput?.value || 'el máximo'}, se activa el actuador.`;
        }
        if (value === 'minimo') {
            if (maxInput) maxInput.value = '';
            if (ayuda) ayuda.textContent = 'Cuando el valor baje del mínimo, se enciende el actuador. Al subir con histéresis, se apaga.';
            if (preview) preview.textContent = `Si el sensor baja de ${minInput?.value || 'el mínimo'}, se activa el actuador.`;
        }
        if (value === 'rango') {
            if (ayuda) ayuda.textContent = 'Cuando el valor salga del rango permitido, se enciende el actuador. Al volver al rango, se apaga.';
            if (preview) preview.textContent = `Mantener el sensor entre ${minInput?.value || 'mínimo'} y ${maxInput?.value || 'máximo'}.`;
        }
        if (value === 'agua_nivel') {
            const nivel = nivelInput?.value || '50';
            if (minInput) minInput.value = nivel;
            if (maxInput) maxInput.value = '';
            if (ayuda) ayuda.textContent = 'El sistema abre la válvula si no se detecta el nivel objetivo, y cierra cuando el sensor confirma el nivel.';
            if (preview) preview.textContent = `Abrir agua hasta ${nivel} %. Si el sensor no responde, se corta por timeout.`;
            if (payload) {
                payload.value = JSON.stringify({accion: 'llenar_hasta', nivel_objetivo: parseInt(nivel, 10), timeout_seg: 90}, null, 2);
            }
        } else if (payload && payload.value.includes('llenar_hasta')) {
            payload.value = '';
        }

        filtrarPorTipo();
    }

    modulo?.addEventListener('change', () => { filtrarPorModulo(); filtrarPorTipo(); });
    cards.forEach((card) => {
        card.addEventListener('click', () => {
            tipo.value = card.dataset.reglaCard;
            aplicarPreset(card.dataset.preset);
            actualizarRegla();
        });
    });
    [minInput, maxInput, nivelInput].forEach((el) => el?.addEventListener('input', actualizarRegla));
    nivelInput?.addEventListener('change', actualizarRegla);

    const preset = new URLSearchParams(window.location.search).get('preset');
    aplicarPreset(preset);
    filtrarPorModulo();
    actualizarRegla();
});
</script>
@endpush
