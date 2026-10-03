<section class="jaula-card module-camera-card" data-module-camera>
    <div class="card-title-row">
        <h2><i class="ri-camera-line" aria-hidden="true"></i> Cámara de la jaula</h2>
        @if($modulo->camaras->isNotEmpty())
            <select class="module-camera-select" aria-label="Seleccionar cámara" data-camera-select>
                @foreach($modulo->camaras as $camara)
                    <option value="{{ $camara->id }}" data-source="{{ $camara->hls_url }}">{{ $camara->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
    <div class="module-camera-frame" data-camera-frame>
        <video autoplay muted playsinline controls aria-label="Transmisión de la cámara de la jaula" data-camera-video></video>
        <span class="module-camera-status" role="status" aria-live="polite" data-camera-status>Sin transmisión</span>
        <div class="module-camera-placeholder" data-camera-placeholder>
            <i class="ri-camera-off-line" aria-hidden="true"></i>
            <strong data-camera-message>{{ $modulo->camaras->isEmpty() ? 'No hay cámaras habilitadas en este módulo' : 'Conectando con la cámara…' }}</strong>
            <small>La imagen en vivo aparecerá cuando la transmisión esté disponible.</small>
        </div>
    </div>
    <div class="module-camera-actions">
        <button type="button" data-camera-retry @disabled($modulo->camaras->isEmpty())><i class="ri-refresh-line"></i> Reintentar conexión</button>
        <a href="{{ route('panel.camaras.index', ['modulo_id' => $modulo->id]) }}">Ver cámaras</a>
        <button type="button" data-camera-expand><i class="ri-fullscreen-line"></i> Ampliar</button>
    </div>
</section>
