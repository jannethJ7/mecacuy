@csrf

<div class="mc-pro-form-grid">
    <div class="mc-pro-field">
        <label for="field_modulo_id">Módulo / jaula</label>
        <select id="field_modulo_id" name="modulo_id" required>
            <option value="">Seleccionar módulo</option>
            @foreach(($modulos ?? []) as $modulo)
                <option value="{{ $modulo->id }}" @selected(old('modulo_id', $camara->modulo_id ?? '') == $modulo->id)>
                    {{ $modulo->codigo }} · {{ $modulo->nombre }}
                </option>
            @endforeach
        </select>
        @error('modulo_id') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_codigo">Código</label>
        <input id="field_codigo" name="codigo" value="{{ old('codigo', $camara->codigo ?? '') }}" required placeholder="CAM-001">
        @error('codigo') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_nombre">Nombre</label>
        <input id="field_nombre" name="nombre" value="{{ old('nombre', $camara->nombre ?? '') }}" required placeholder="Cámara jaula 1">
        @error('nombre') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_stream_key">Stream key MediaMTX</label>
        <input id="field_stream_key" name="stream_key" value="{{ old('stream_key', $camara->stream_key ?? '') }}" required placeholder="mod-001">
        <small>Debe coincidir con el nombre del path configurado en MediaMTX. Solo letras, números, guion y guion bajo.</small>
        @error('stream_key') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_tipo">Tipo de cámara</label>
        <select id="field_tipo" name="tipo" required>
            @foreach(['ip' => 'Cámara IP / RTSP', 'esp32_cam' => 'ESP32-CAM / ESP32-S3 CAM', 'usb' => 'USB mediante gateway', 'otro' => 'Otro'] as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $camara->tipo ?? 'ip') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_habilitada">Habilitada</label>
        <select id="field_habilitada" name="habilitada">
            <option value="1" @selected(old('habilitada', $camara->habilitada ?? 1) == 1)>Sí</option>
            <option value="0" @selected(old('habilitada', $camara->habilitada ?? 1) == 0)>No</option>
        </select>
    </div>
</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.camaras.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar cámara
    </button>
</div>
