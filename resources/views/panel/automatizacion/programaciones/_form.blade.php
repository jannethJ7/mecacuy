@csrf

@php
    $diasRaw = old('dias', isset($programacion) ? (is_array($programacion->dias) ? $programacion->dias : json_decode($programacion->dias ?? '[]', true)) : []);
    $mapaDias = ['lun' => 'lu', 'mar' => 'ma', 'mie' => 'mi', 'jue' => 'ju', 'vie' => 'vi', 'sab' => 'sa', 'dom' => 'do'];
    $diasActuales = collect($diasRaw ?? [])->map(fn ($d) => $mapaDias[$d] ?? $d)->all();

    $estado = old('estado_deseado_json');
    if ($estado === null) {
        $estadoData = $programacion->estado_deseado ?? ['on' => true];
        $estadoData = is_array($estadoData) ? $estadoData : json_decode($estadoData ?: '{}', true);
        $estado = json_encode($estadoData ?: ['on' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        $estadoData = json_decode($estado, true) ?: [];
    }

    $estadoData = is_array($programacion->estado_deseado ?? null)
        ? $programacion->estado_deseado
        : json_decode($programacion->estado_deseado ?? '{}', true);

    $accionActual = old('tipo_programacion');
    if (!$accionActual) {
        $accionGuardada = $estadoData['accion'] ?? null;
        $accionActual = $accionGuardada === 'pulso'
            ? 'alimentacion'
            : ($accionGuardada === 'llenar_hasta' ? 'agua_nivel' : 'generica');
    }

    $nivelActual = old('nivel_objetivo', $estadoData['nivel_objetivo'] ?? 50);
    $timeoutActual = old('timeout_seg', $estadoData['timeout_seg'] ?? ($programacion->duracion_seg ?? 90));
@endphp

<div class="mc-auto-wizard" data-auto-wizard="programacion">
    <div class="mc-auto-explain-card">
        <div class="mc-auto-explain-icon"><i class="ri-calendar-schedule-line"></i></div>
        <div>
            <span class="mc-auto-kicker">Asistente de programaciones</span>
            <h3>Una programación se ejecuta por hora y día</h3>
            <p>
                Usa esta pantalla para alimentar a horas definidas o llenar agua hasta un nivel. El sistema siempre crea protección de apagado.
            </p>
        </div>
        @if(Route::has('panel.automatizacion.reglas.create'))
            <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.reglas.create') }}">
                Automatizar por sensor
            </a>
        @endif
    </div>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>1</span>
            <div>
                <h3>Elige qué acción se repetirá</h3>
                <p>La comida se controla por segundos. El agua se controla por nivel y por tiempo máximo de seguridad.</p>
            </div>
        </div>

        <input type="hidden" id="field_tipo_programacion" name="tipo_programacion" value="{{ $accionActual }}" data-programacion-tipo>

        <div class="mc-auto-choice-grid" role="radiogroup" aria-label="Tipo de programación">
            <button type="button" class="mc-auto-choice" data-prog-card="alimentacion">
                <i class="ri-basket-line"></i>
                <strong>Alimentación temporizada</strong>
                <span>Encender alimentador por 10, 20, 50 s o el tiempo que definas.</span>
            </button>
            <button type="button" class="mc-auto-choice" data-prog-card="agua_nivel">
                <i class="ri-drop-line"></i>
                <strong>Llenado por nivel</strong>
                <span>Abrir electroválvula y cerrar al llegar a 25, 50, 75 o 100 %.</span>
            </button>
            <button type="button" class="mc-auto-choice" data-prog-card="generica">
                <i class="ri-tools-line"></i>
                <strong>Acción avanzada</strong>
                <span>Enviar un estado JSON manual para pruebas o casos especiales.</span>
            </button>
        </div>
    </section>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>2</span>
            <div>
                <h3>Configura el actuador y el horario</h3>
                <p>Selecciona el módulo, el actuador y la hora en la que debe iniciar.</p>
            </div>
        </div>

        <div class="mc-pro-form-grid">
            <div class="mc-pro-field">
                <label for="field_nombre">Nombre</label>
                <input id="field_nombre" name="nombre" value="{{ old('nombre', $programacion->nombre ?? '') }}" required placeholder="Ej.: Alimentación de la mañana" data-prog-name>
            </div>

            <div class="mc-pro-field">
                <label for="field_modulo_id">Módulo</label>
                <select id="field_modulo_id" name="modulo_id" required data-prog-modulo>
                    <option value="">Seleccionar módulo</option>
                    @foreach(($modulos ?? []) as $modulo)
                        <option value="{{ $modulo->id }}" @selected(old('modulo_id', $programacion->modulo_id ?? '') == $modulo->id)>
                            {{ $modulo->codigo }} · {{ $modulo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mc-pro-field">
                <label for="field_actuador_id">Actuador</label>
                <select id="field_actuador_id" name="actuador_id" required data-programacion-actuador>
                    <option value="">Seleccionar actuador</option>
                    @foreach(($actuadores ?? []) as $actuador)
                        @php
                            $cod = strtoupper((string) $actuador->codigo);
                            $tipo = strtolower((string) $actuador->tipo);
                            $grupo = str_contains($cod, 'ALIMENTO') || str_contains($cod, 'FEED') || str_contains($tipo, 'stepper') || str_contains($tipo, 'dosificador')
                                ? 'alimentacion'
                                : ((str_contains($cod, 'AGUA') || str_contains($cod, 'WATER') || str_contains($cod, 'VALVULA') || str_contains($tipo, 'valvula')) ? 'agua_nivel' : 'generica');
                        @endphp
                        <option value="{{ $actuador->id }}" data-modulo="{{ $actuador->modulo_id }}" data-codigo="{{ $actuador->codigo }}" data-grupo="{{ $grupo }}" @selected(old('actuador_id', $programacion->actuador_id ?? '') == $actuador->id)>
                            {{ $actuador->codigo }} · {{ $actuador->nombre }}
                        </option>
                    @endforeach
                </select>
                <small data-programacion-actuador-help>Para alimentación selecciona D_ALIMENTO_STEP. Para agua selecciona D_AGUA.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_hora_inicio">Hora de inicio</label>
                <input id="field_hora_inicio" type="time" name="hora_inicio" value="{{ old('hora_inicio', isset($programacion->hora_inicio) ? substr($programacion->hora_inicio, 0, 5) : '') }}" required>
            </div>
        </div>
    </section>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>3</span>
            <div>
                <h3>Define cuánto debe durar o hasta dónde debe llegar</h3>
                <p data-prog-help>El sistema apagará automáticamente al cumplir la condición o vencer el tiempo máximo.</p>
            </div>
        </div>

        <div class="mc-auto-rule-preview" data-prog-preview>
            <i class="ri-shield-check-line"></i>
            <span>La acción tendrá apagado seguro.</span>
        </div>

        <div class="mc-pro-form-grid">
            <div class="mc-pro-field" data-prog-field="duracion">
                <label for="field_duracion_seg">Duración de alimentación</label>
                <div class="mc-auto-segmented" data-duration-segments>
                    @foreach([10, 20, 50] as $seg)
                        <button type="button" data-duration="{{ $seg }}">{{ $seg }} s</button>
                    @endforeach
                </div>
                <input id="field_duracion_seg" type="number" name="duracion_seg" min="1" max="3600" value="{{ old('duracion_seg', $programacion->duracion_seg ?? 20) }}">
                <small>Ejemplo: 20 s para una porción corta de croquetas.</small>
            </div>

            <div class="mc-pro-field" data-prog-field="nivel">
                <label for="field_nivel_objetivo">Nivel objetivo de agua</label>
                <div class="mc-auto-segmented" data-level-segments>
                    @foreach([25, 50, 75, 100] as $nivel)
                        <button type="button" data-level="{{ $nivel }}">{{ $nivel }} %</button>
                    @endforeach
                </div>
                <select id="field_nivel_objetivo" name="nivel_objetivo">
                    @foreach([25, 50, 75, 100] as $nivel)
                        <option value="{{ $nivel }}" @selected((int) $nivelActual === $nivel)>{{ $nivel }} %</option>
                    @endforeach
                </select>
                <small>La electroválvula se cierra cuando el sensor de nivel confirma este porcentaje.</small>
            </div>

            <div class="mc-pro-field" data-prog-field="timeout">
                <label for="field_timeout_seg">Tiempo máximo de llenado</label>
                <input id="field_timeout_seg" type="number" name="timeout_seg" min="5" max="300" value="{{ $timeoutActual }}">
                <small>Si el sensor falla, este tiempo evita rebalse.</small>
            </div>

            <div class="mc-pro-field">
                <label for="field_prioridad">Prioridad</label>
                <input id="field_prioridad" type="number" name="prioridad" value="{{ old('prioridad', $programacion->prioridad ?? 50) }}">
            </div>

            <div class="mc-pro-field">
                <label for="field_activo">Estado</label>
                <select id="field_activo" name="activo">
                    <option value="1" @selected(old('activo', $programacion->activo ?? 1) == 1)>Activa</option>
                    <option value="0" @selected(old('activo', $programacion->activo ?? 1) == 0)>Inactiva</option>
                </select>
            </div>
        </div>
    </section>

    <section class="mc-auto-step">
        <div class="mc-auto-step-head">
            <span>4</span>
            <div>
                <h3>Elige los días</h3>
                <p>Marca los días en los que se repetirá esta programación.</p>
            </div>
        </div>

        <div class="mc-pro-field" role="group" aria-labelledby="field_dias_label">
            <span id="field_dias_label" class="mc-pro-field-label">Días de ejecución</span>
            <div class="mc-pro-check-grid mc-auto-days-grid">
                @foreach(['lu' => 'Lunes', 'ma' => 'Martes', 'mi' => 'Miércoles', 'ju' => 'Jueves', 'vi' => 'Viernes', 'sa' => 'Sábado', 'do' => 'Domingo'] as $key => $label)
                    <label>
                        <input type="checkbox" name="dias[]" value="{{ $key }}" @checked(in_array($key, $diasActuales ?? []))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    <details class="mc-auto-advanced" data-prog-field="json">
        <summary><i class="ri-code-s-slash-line"></i> Estado deseado JSON avanzado</summary>
        <div class="mc-pro-field">
            <label for="field_estado_deseado_json">Estado deseado JSON</label>
            <textarea id="field_estado_deseado_json" name="estado_deseado_json" rows="4" placeholder='{"on": true}'>{{ $estado }}</textarea>
            <small>Solo se usa en modo avanzado. Para alimentación y agua se genera automáticamente.</small>
        </div>
    </details>
</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.automatizacion.programaciones.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar programación
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const wizard = document.querySelector('[data-auto-wizard="programacion"]');
    if (!wizard) return;

    const tipo = wizard.querySelector('[data-programacion-tipo]');
    const cards = [...wizard.querySelectorAll('[data-prog-card]')];
    const modulo = wizard.querySelector('[data-prog-modulo]');
    const actuador = wizard.querySelector('[data-programacion-actuador]');
    const nombre = wizard.querySelector('[data-prog-name]');
    const duracion = wizard.querySelector('[data-prog-field="duracion"]');
    const nivel = wizard.querySelector('[data-prog-field="nivel"]');
    const timeout = wizard.querySelector('[data-prog-field="timeout"]');
    const json = wizard.querySelector('[data-prog-field="json"]');
    const duracionInput = wizard.querySelector('#field_duracion_seg');
    const nivelSelect = wizard.querySelector('#field_nivel_objetivo');
    const preview = wizard.querySelector('[data-prog-preview] span');
    const ayuda = wizard.querySelector('[data-prog-help]');

    function selectOptionByCode(select, codes) {
        if (!select) return;
        const wanted = codes.map((c) => String(c).toUpperCase());
        const opt = [...select.options].find((option) => wanted.includes(String(option.dataset.codigo || '').toUpperCase()) && !option.hidden);
        if (opt) select.value = opt.value;
    }

    function filtrarActuadores() {
        const value = tipo?.value || 'generica';
        const moduloId = modulo?.value || '';
        if (!actuador) return;
        [...actuador.options].forEach((opt) => {
            if (!opt.value) return;
            const grupo = opt.dataset.grupo || 'generica';
            opt.hidden = (moduloId && opt.dataset.modulo !== moduloId) || (value !== 'generica' && grupo !== value);
        });
        if (actuador.selectedOptions[0]?.hidden) actuador.value = '';
    }

    function aplicarPreset(preset) {
        if (preset === 'alimentacion') {
            tipo.value = 'alimentacion';
            if (!nombre.value) nombre.value = 'Alimentación programada';
            if (!duracionInput.value) duracionInput.value = '20';
            selectOptionByCode(actuador, ['D_ALIMENTO_STEP', 'D_FEED', 'D_ALIMENTO']);
        }
        if (preset === 'agua_nivel') {
            tipo.value = 'agua_nivel';
            if (!nombre.value) nombre.value = 'Llenado de agua programado';
            if (nivelSelect) nivelSelect.value = '50';
            selectOptionByCode(actuador, ['D_AGUA', 'D_WATER', 'D_VALVULA']);
        }
    }

    function actualizar() {
        const value = tipo?.value || 'generica';
        cards.forEach((card) => card.classList.toggle('is-selected', card.dataset.progCard === value));
        if (duracion) duracion.style.display = value === 'agua_nivel' ? 'none' : '';
        if (nivel) nivel.style.display = value === 'agua_nivel' ? '' : 'none';
        if (timeout) timeout.style.display = value === 'agua_nivel' ? '' : 'none';
        if (json) json.style.display = value === 'generica' ? '' : 'none';

        if (value === 'alimentacion') {
            if (preview) preview.textContent = `A la hora indicada, alimentador encendido ${duracionInput?.value || 20} s y apagado automático.`;
            if (ayuda) ayuda.textContent = 'Ideal para croquetas. Nunca queda encendido indefinidamente porque se programa apagado seguro.';
        }
        if (value === 'agua_nivel') {
            if (preview) preview.textContent = `A la hora indicada, abrir válvula hasta ${nivelSelect?.value || 50} % o cortar por timeout.`;
            if (ayuda) ayuda.textContent = 'Ideal para evitar presión constante en el chupón. El nivel y el timeout evitan rebalse.';
        }
        if (value === 'generica') {
            if (preview) preview.textContent = 'Se enviará el JSON avanzado al actuador seleccionado.';
            if (ayuda) ayuda.textContent = 'Úsalo solo para pruebas o actuadores especiales.';
        }
        filtrarActuadores();
    }

    cards.forEach((card) => card.addEventListener('click', () => {
        tipo.value = card.dataset.progCard;
        if (tipo.value === 'alimentacion') aplicarPreset('alimentacion');
        if (tipo.value === 'agua_nivel') aplicarPreset('agua_nivel');
        actualizar();
    }));
    modulo?.addEventListener('change', actualizar);
    duracionInput?.addEventListener('input', actualizar);
    nivelSelect?.addEventListener('change', actualizar);

    wizard.querySelectorAll('[data-duration]').forEach((btn) => {
        btn.addEventListener('click', () => {
            duracionInput.value = btn.dataset.duration;
            actualizar();
        });
    });
    wizard.querySelectorAll('[data-level]').forEach((btn) => {
        btn.addEventListener('click', () => {
            nivelSelect.value = btn.dataset.level;
            actualizar();
        });
    });

    const preset = new URLSearchParams(window.location.search).get('preset');
    aplicarPreset(preset);
    actualizar();
});
</script>
@endpush
