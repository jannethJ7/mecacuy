@csrf

<div class="mc-pro-form-grid">
    <div class="mc-pro-field">
        <label for="field_modulo_id">Módulo</label>
        <select id="field_modulo_id" name="modulo_id" required>
            <option value="">Seleccionar módulo</option>
            @foreach(($modulos ?? []) as $modulo)
                <option value="{{ $modulo->id }}" @selected(old('modulo_id', $actuador->modulo_id ?? '') == $modulo->id)>
                    {{ $modulo->codigo }} · {{ $modulo->nombre }}
                </option>
            @endforeach
        </select>
        @error('modulo_id') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_codigo">Código</label>
        <input id="field_codigo" name="codigo" value="{{ old('codigo', $actuador->codigo ?? '') }}" required placeholder="D_FAN">
    </div>

    <div class="mc-pro-field">
        <label for="field_nombre">Nombre</label>
        <input id="field_nombre" name="nombre" value="{{ old('nombre', $actuador->nombre ?? '') }}" required placeholder="Ventilador">
    </div>

    <div class="mc-pro-field">
        <label for="field_tipo">Tipo</label>
        <select id="field_tipo" name="tipo">
            @foreach(['rele','valvula','dosificador','motor','otro'] as $tipo)
                <option value="{{ $tipo }}" @selected(old('tipo', $actuador->tipo ?? '') == $tipo)>{{ ucfirst($tipo) }}</option>
            @endforeach
        </select>
    </div>

    <div class="mc-pro-field">
        <label for="field_gpio_pin">GPIO</label>
        <input id="field_gpio_pin" type="number" name="gpio_pin" value="{{ old('gpio_pin', $actuador->gpio_pin ?? '') }}" placeholder="26">
    </div>

    <div class="mc-pro-field">
        <label for="field_activo">Activo</label>
        <select id="field_activo" name="activo">
            <option value="1" @selected(old('activo', $actuador->activo ?? 1) == 1)>Sí</option>
            <option value="0" @selected(old('activo', $actuador->activo ?? 1) == 0)>No</option>
        </select>
    </div>

    <div class="mc-pro-field">
        <label for="field_invertido">Invertido</label>
        <select id="field_invertido" name="invertido">
            <option value="0" @selected(old('invertido', $actuador->invertido ?? 0) == 0)>No</option>
            <option value="1" @selected(old('invertido', $actuador->invertido ?? 0) == 1)>Sí</option>
        </select>
    </div>
</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.actuadores.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar actuador
    </button>
</div>
