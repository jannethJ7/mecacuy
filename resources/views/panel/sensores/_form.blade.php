@csrf

<div class="mc-pro-form-grid">
    <div class="mc-pro-field">
        <label for="field_modulo_id">Módulo</label>
        <select id="field_modulo_id" name="modulo_id" required>
            <option value="">Seleccionar módulo</option>
            @foreach(($modulos ?? []) as $modulo)
                <option value="{{ $modulo->id }}" @selected(old('modulo_id', $sensor->modulo_id ?? '') == $modulo->id)>
                    {{ $modulo->codigo }} · {{ $modulo->nombre }}
                </option>
            @endforeach
        </select>
        @error('modulo_id') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_codigo">Código</label>
        <input id="field_codigo" name="codigo" value="{{ old('codigo', $sensor->codigo ?? '') }}" required placeholder="S_TEMP">
        @error('codigo') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_nombre">Nombre</label>
        <input id="field_nombre" name="nombre" value="{{ old('nombre', $sensor->nombre ?? '') }}" required placeholder="Temperatura interior">
        @error('nombre') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_tipo">Tipo</label>
        <select id="field_tipo" name="tipo">
            @foreach(['temperatura','humedad','calidad_aire','nivel','peso','otro'] as $tipo)
                <option value="{{ $tipo }}" @selected(old('tipo', $sensor->tipo ?? '') == $tipo)>{{ ucfirst(str_replace('_',' ', $tipo)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="mc-pro-field">
        <label for="field_unidad">Unidad</label>
        <input id="field_unidad" name="unidad" value="{{ old('unidad', $sensor->unidad ?? '') }}" placeholder="°C, %, ppm">
    </div>

    <div class="mc-pro-field">
        <label for="field_gpio_pin">GPIO</label>
        <input id="field_gpio_pin" type="number" name="gpio_pin" value="{{ old('gpio_pin', $sensor->gpio_pin ?? '') }}" min="0" max="39" placeholder="Ej: 4">
        <small>Pin físico usado por el ESP32 para este sensor. Déjalo vacío si es un sensor lógico.</small>
        @error('gpio_pin') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_activo">Activo</label>
        <select id="field_activo" name="activo">
            <option value="1" @selected(old('activo', $sensor->activo ?? 1) == 1)>Sí</option>
            <option value="0" @selected(old('activo', $sensor->activo ?? 1) == 0)>No</option>
        </select>
    </div>

    <div class="mc-pro-field mc-pro-field-wide">
        <label for="field_meta_json">Meta JSON opcional</label>
        <textarea id="field_meta_json" name="meta_json" rows="3" placeholder='{"driver":"dht","dht_type":"DHT11"}'>{{ old('meta_json', isset($sensor) && $sensor->meta ? json_encode($sensor->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        <small>Opcional. Sirve para calibración, tipo de sensor o comportamiento especial del firmware.</small>
        @error('meta_json') <small>{{ $message }}</small> @enderror
    </div>
</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.sensores.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar sensor
    </button>
</div>
