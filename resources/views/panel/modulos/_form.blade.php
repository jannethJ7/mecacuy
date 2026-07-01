@csrf

<div class="mc-pro-form-grid">
    <div class="mc-pro-field">
        <label for="field_codigo">Código</label>
        <input id="field_codigo" name="codigo" value="{{ old('codigo', $modulo->codigo ?? '') }}" required placeholder="MOD-001">
        @error('codigo') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_nombre">Nombre</label>
        <input id="field_nombre" name="nombre" value="{{ old('nombre', $modulo->nombre ?? '') }}" placeholder="Módulo 1 - Jaula de cuyes">
        @error('nombre') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_uid">UID del ESP32</label>
        <input id="field_uid" name="uid" value="{{ old('uid', $modulo->uid ?? '') }}" required placeholder="ESP32-MOD-001">
        @error('uid') <small>{{ $message }}</small> @enderror
    </div>

    <div class="mc-pro-field">
        <label for="field_version_firmware">Versión firmware</label>
        <input id="field_version_firmware" name="version_firmware" value="{{ old('version_firmware', $modulo->version_firmware ?? '') }}" placeholder="v1.0.0">
    </div>

    <div class="mc-pro-field">
        <label for="field_ip">IP</label>
        <input id="field_ip" name="ip" value="{{ old('ip', $modulo->ip ?? '') }}" placeholder="192.168.1.50">
    </div>

    <div class="mc-pro-field">
        <label for="field_zona_horaria">Zona horaria</label>
        <input id="field_zona_horaria" name="zona_horaria" value="{{ old('zona_horaria', $modulo->zona_horaria ?? 'America/La_Paz') }}">
    </div>

    <div class="mc-pro-field">
        <label for="field_habilitado">Habilitado</label>
        <select id="field_habilitado" name="habilitado">
            <option value="1" @selected(old('habilitado', $modulo->habilitado ?? 1) == 1)>Sí</option>
            <option value="0" @selected(old('habilitado', $modulo->habilitado ?? 1) == 0)>No</option>
        </select>
    </div>
</div>

<div class="mc-pro-form-actions">
    <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.modulos.index') }}">Cancelar</a>
    <button class="mc-pro-btn mc-pro-btn-primary">
        <i class="ri-save-3-line"></i> Guardar módulo
    </button>
</div>
