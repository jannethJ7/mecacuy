(() => {
    const elementos = [...document.querySelectorAll('[data-verificacion-calefaccion]')];
    if (!elementos.length) return;
    const sinDatos = elemento => {
        elemento.dataset.estado = 'sin_verificar';
        elemento.querySelector('[data-verificacion-etiqueta]').textContent = 'Sin verificación reciente';
        elemento.querySelector('[data-verificacion-fecha]').textContent = 'No se pudo actualizar la medición';
    };
    const actualizar = async elemento => {
        try {
            const respuesta = await fetch(elemento.dataset.url, {
                headers: {Accept: 'application/json'}, cache: 'no-store',
                signal: AbortSignal.timeout(5000)
            });
            if (!respuesta.ok) throw new Error('No disponible');
            const estado = await respuesta.json();
            elemento.dataset.estado = estado.codigo;
            elemento.querySelector('[data-verificacion-etiqueta]').textContent = estado.etiqueta;
            elemento.querySelector('[data-verificacion-fecha]').textContent = estado.medido_en
                ? 'Medido: ' + new Date(estado.medido_en).toLocaleString('es-BO')
                : 'Sin medición válida reciente';
        } catch (_) { sinDatos(elemento); }
    };
    const ciclo = async () => {
        await Promise.allSettled(elementos.map(actualizar));
        setTimeout(ciclo, 5000);
    };
    // Al volver a una pestaña suspendida no conservar una confirmación antigua.
    document.addEventListener('visibilitychange', () => {
        elementos.forEach(sinDatos);
        if (!document.hidden) elementos.forEach(actualizar);
    });
    ciclo();
})();
