(() => {
    const card = document.querySelector('[data-module-camera]');
    if (!card) return;
    const video = card.querySelector('[data-camera-video]');
    const select = card.querySelector('[data-camera-select]');
    const status = card.querySelector('[data-camera-status]');
    const placeholder = card.querySelector('[data-camera-placeholder]');
    const message = card.querySelector('[data-camera-message]');
    let hls, timer;

    function state(text, live = false) {
        status.textContent = text;
        status.classList.toggle('is-live', live);
        placeholder.hidden = live;
        if (live) clearTimeout(timer);
    }

    function stop() {
        clearTimeout(timer);
        if (hls) { hls.destroy(); hls = null; }
        video.pause();
        video.removeAttribute('src');
        video.load();
    }

    function unavailable(text) {
        state('Sin conexión');
        message.textContent = text;
    }

    function connect() {
        stop();
        const source = select?.selectedOptions[0]?.dataset.source;
        if (!source) {
            unavailable(select ? 'Esta cámara no tiene una transmisión configurada.' : 'No hay cámaras habilitadas en este módulo.');
            return;
        }
        state('Conectando…');
        message.textContent = 'Conectando con la cámara…';
        timer = setTimeout(() => {
            stop();
            unavailable('No se pudo conectar. Comprueba la cámara y reintenta.');
        }, 15000);
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source;
            video.play().catch(() => {});
        } else if (window.Hls?.isSupported()) {
            hls = new window.Hls();
            hls.on(window.Hls.Events.ERROR, (_event, data) => {
                if (data.fatal) {
                    stop();
                    unavailable('Transmisión no disponible. Puedes reintentar la conexión.');
                }
            });
            hls.loadSource(source);
            hls.attachMedia(video);
            hls.on(window.Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
        } else {
            clearTimeout(timer);
            unavailable('El reproductor HLS no está disponible en este navegador.');
        }
    }

    video.addEventListener('playing', () => state('EN VIVO', true));
    video.addEventListener('waiting', () => state('Cargando…', !video.paused));
    video.addEventListener('error', () => { clearTimeout(timer); unavailable('No se puede reproducir esta transmisión.'); });
    select?.addEventListener('change', connect);
    card.querySelector('[data-camera-retry]').addEventListener('click', connect);
    card.querySelector('[data-camera-expand]').addEventListener('click', async () => {
        const frame = card.querySelector('[data-camera-frame]');
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else if (frame.requestFullscreen) await frame.requestFullscreen();
            else unavailable('La pantalla completa no está disponible en este navegador.');
        } catch (_) { status.textContent = 'No se pudo ampliar'; }
    });
    window.addEventListener('pagehide', stop);
    connect();
})();
