@php($metaCalef = $sensor->meta ?? [])
<fieldset class="mc-pro-card" data-configuracion-calefaccion @if(old('tipo', $sensor->tipo) !== 'verificacion_calefaccion') hidden @endif>
    <legend>Verificación eléctrica de calefacción con ACS712</legend>
    <p>Entrada analógica acondicionada. La página muestra estados, no amperios. Activa la confirmación únicamente después de instalar y calibrar el sensor.</p>
    <div class="mc-pro-form-grid">
        <div class="mc-pro-field">
            <label for="calefaccion_actuador">Actuador de calefacción del mismo módulo</label>
            <select id="calefaccion_actuador" name="calefaccion_actuador">
                <option value="">Seleccionar calefactor</option>
                @foreach(($actuadores ?? []) as $opcion)
                    <option value="{{ $opcion->codigo }}" data-modulo="{{ $opcion->modulo_id }}" @selected(old('calefaccion_actuador', $metaCalef['actuador_codigo'] ?? '') === $opcion->codigo && (string) old('modulo_id', $sensor->modulo_id) === (string) $opcion->modulo_id)>{{ $opcion->modulo->codigo }} · {{ $opcion->nombre }} · {{ $opcion->codigo }}</option>
                @endforeach
            </select>
            @error('calefaccion_actuador') <small>{{ $message }}</small> @enderror
        </div>
        <div class="mc-pro-field">
            <label for="calefaccion_calibrado">Sensor instalado y calibrado</label>
            <select id="calefaccion_calibrado" name="calefaccion_calibrado">
                <option value="0" @selected(!old('calefaccion_calibrado', $metaCalef['calibrado'] ?? false))>No: mostrar pendiente de calibración</option>
                <option value="1" @selected(old('calefaccion_calibrado', $metaCalef['calibrado'] ?? false))>Sí: habilitar verificación</option>
            </select>
        </div>
        @foreach([
            'umbral_encendido_mv' => ['Umbral de consumo detectado en mV RMS', 50, 1, 1000],
            'umbral_apagado_mv' => ['Umbral de ausencia de consumo en mV RMS', 25, 0, 999],
            'cero_mv' => ['Referencia medida sin consumo en mV', 1667, 100, 3000],
            'cero_min_mv' => ['Nivel medio mínimo válido en mV', 1300, 100, 2900],
            'cero_max_mv' => ['Nivel medio máximo válido en mV', 2000, 101, 3000],
            'espera_seg' => ['Espera después de la orden en segundos', 3, 2, 30],
            'vigencia_seg' => ['Vigencia máxima del dato en segundos', 30, 15, 120],
        ] as $campo => [$etiqueta, $valor, $minimo, $maximo])
            <div class="mc-pro-field">
                <label for="calefaccion_{{ $campo }}">{{ $etiqueta }}</label>
                <input id="calefaccion_{{ $campo }}" name="calefaccion_{{ $campo }}" type="number" step="any" min="{{ $minimo }}" max="{{ $maximo }}" value="{{ old('calefaccion_'.$campo, $metaCalef[$campo] ?? $valor) }}">
                @error('calefaccion_'.$campo) <small>{{ $message }}</small> @enderror
            </div>
        @endforeach
    </div>
    <p>Los valores iniciales son orientativos, no una calibración. El sensor no sustituye el limitador térmico ni el corte independiente.</p>
</fieldset>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tipo = document.getElementById('field_tipo');
    const modulo = document.getElementById('field_modulo_id');
    const grupo = document.querySelector('[data-configuracion-calefaccion]');
    const actualizar = () => {
        const activo = tipo.value === 'verificacion_calefaccion';
        grupo.hidden = !activo;
        grupo.querySelectorAll('input, select').forEach(campo => campo.disabled = !activo);
        grupo.querySelectorAll('[data-modulo]').forEach(opcion => {
            opcion.hidden = opcion.dataset.modulo !== modulo.value;
            opcion.disabled = opcion.hidden;
            if (opcion.hidden) opcion.selected = false;
        });
    };
    tipo.addEventListener('change', actualizar);
    modulo.addEventListener('change', actualizar);
    actualizar();
});
</script>
@endpush
