<div class="mc-pro-card" data-nivel-agua-conjunto @if(old('tipo', $sensor->tipo) !== 'nivel_agua') hidden @endif>
    <h3>Nivel de agua del tanque</h3>
    <p>Una sola asignación para el circuito de cuatro niveles. El dispositivo informa 0 %, 25 %, 50 %, 75 % o 100 %.</p>
    <p>Conexión del circuito probado. Los pines están definidos en el firmware y no se configuran por separado.</p>
    <input type="hidden" name="perfil_nivel_agua" value="clavel_4_niveles">
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tipo = document.getElementById('field_tipo');
    const grupo = document.querySelector('[data-nivel-agua-conjunto]');
    const actualizarAgua = () => {
        const conjunto = tipo.value === 'nivel_agua';
        grupo.hidden = !conjunto;
        grupo.style.display = conjunto ? '' : 'none';
        for (const atributo of ['data-agua-gpio', 'data-agua-unidad', 'data-agua-codigo']) {
            const campo = document.querySelector('[' + atributo + ']');
            campo.hidden = conjunto;
            campo.style.display = conjunto ? 'none' : '';
            campo.querySelectorAll('input').forEach(input => input.disabled = conjunto);
        }
    };
    tipo.addEventListener('change', actualizarAgua);
    actualizarAgua();
});
</script>
@endpush
