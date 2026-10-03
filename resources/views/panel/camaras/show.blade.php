@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', $camara->nombre)
@section('page-title', $camara->nombre)
@section('page-subtitle', ($camara->modulo->codigo ?? 'Sin módulo').' · '.$camara->codigo.' · MediaMTX')

@section('content')
<div class="mc-pro-page">
    @include('panel._partials.flash')

    <div class="mc-camera-detail-grid">
        <section class="mc-pro-card mc-video-card">
            <div class="mc-video-head">
                <div>
                    <small>SUPERVISIÓN VISUAL</small>
                    <h3>{{ $camara->nombre }}</h3>
                </div>
                <span id="camera-status" class="mc-pro-badge is-muted">Conectando…</span>
            </div>

            <div class="mc-video-shell">
                <video id="camera-player" autoplay muted playsinline controls></video>
                <div class="mc-video-watermark">{{ $camara->modulo->codigo ?? 'MÓDULO' }} · {{ $camara->codigo }}</div>
            </div>

            @if(!$camara->hls_url)
                <div class="mc-video-warning">Configura <code>MEDIAMTX_HLS_PUBLIC_BASE</code> para habilitar HLS.</div>
            @endif
        </section>

        <aside class="mc-pro-card mc-camera-info">
            <h3>Información</h3>
            <dl>
                <div><dt>Módulo</dt><dd>{{ $camara->modulo->codigo ?? '—' }}</dd></div>
                <div><dt>Stream key</dt><dd>{{ $camara->stream_key }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ strtoupper(str_replace('_', ' ', $camara->tipo)) }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $camara->habilitada ? 'Habilitada' : 'Deshabilitada' }}</dd></div>
            </dl>

            <div class="mc-pro-form-actions">
                <button type="button" id="camera-reload" class="mc-pro-btn mc-pro-btn-primary"><i class="ri-refresh-line"></i> Reconectar</button>
                @if($camara->webrtc_url)
                    <a class="mc-pro-btn mc-pro-btn-soft" href="{{ $camara->webrtc_url }}" target="_blank" rel="noopener"><i class="ri-live-line"></i> WebRTC</a>
                @endif
                <a class="mc-pro-btn mc-pro-btn-ghost" href="{{ route('panel.camaras.index') }}">Volver</a>
            </div>
        </aside>
    </div>
</div>

<style>
.mc-camera-detail-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,.8fr);gap:22px}.mc-video-card,.mc-camera-info{padding:20px}.mc-video-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:14px}.mc-video-head small{color:#7a8898;font-weight:800;letter-spacing:.08em}.mc-video-head h3{margin:4px 0 0}.mc-video-shell{position:relative;aspect-ratio:16/9;background:#05080b;border-radius:16px;overflow:hidden}.mc-video-shell video{width:100%;height:100%;object-fit:contain;background:#000}.mc-video-watermark{position:absolute;left:14px;bottom:14px;padding:7px 10px;border-radius:8px;background:rgba(0,0,0,.58);color:#fff;font-size:12px;font-weight:700}.mc-camera-info dl{display:grid;gap:12px}.mc-camera-info dl div{display:flex;justify-content:space-between;gap:20px;padding-bottom:10px;border-bottom:1px solid #edf0f3}.mc-camera-info dt{color:#7a8898}.mc-camera-info dd{margin:0;font-weight:700;text-align:right;word-break:break-all}.mc-video-warning{margin-top:12px;padding:12px;border-radius:10px;background:#fff5d8;color:#745500}@media(max-width:900px){.mc-camera-detail-grid{grid-template-columns:1fr}}
</style>

<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.18"></script>
<script>
(() => {
    const source = @json($camara->hls_url);
    const video = document.getElementById('camera-player');
    const status = document.getElementById('camera-status');
    let hls = null;

    const setStatus = (text, ok = false) => {
        status.textContent = text;
        status.className = 'mc-pro-badge ' + (ok ? 'is-success' : 'is-muted');
    };

    const connect = () => {
        if (hls) {
            hls.destroy();
            hls = null;
        }
        video.removeAttribute('src');
        video.load();

        if (!source) {
            setStatus('Sin URL HLS');
            return;
        }

        setStatus('Conectando…');

        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = source;
            video.play().catch(() => {});
            return;
        }

        if (window.Hls && Hls.isSupported()) {
            hls = new Hls({
                liveSyncDurationCount: 2,
                liveMaxLatencyDurationCount: 5,
                maxLiveSyncPlaybackRate: 1.5,
                enableWorker: true,
                backBufferLength: 20,
            });

            hls.loadSource(source);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
            hls.on(Hls.Events.ERROR, (_event, data) => {
                if (!data.fatal) return;
                if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                    setStatus('Reconectando…');
                    hls.startLoad();
                } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                    setStatus('Recuperando…');
                    hls.recoverMediaError();
                } else {
                    setStatus('Error de stream');
                    hls.destroy();
                }
            });
            return;
        }

        setStatus('HLS no soportado');
    };

    video.addEventListener('playing', () => setStatus('EN VIVO', true));
    video.addEventListener('waiting', () => setStatus('Buffer…'));
    video.addEventListener('stalled', () => setStatus('Reconectando…'));
    video.addEventListener('error', () => setStatus('Error de reproducción'));
    document.getElementById('camera-reload').addEventListener('click', connect);

    connect();
})();
</script>
@endsection
