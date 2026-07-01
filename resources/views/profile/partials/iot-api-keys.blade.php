<section class="mc-form-section mc-iot-keys-section">

    <div class="mc-section-header">
        <div>
            <h2>
                <i class="ph-duotone ph-key"></i>
                API keys de módulos ESP32
            </h2>

            <p>
                Consulta el UID y la clave que debe copiarse en el firmware de cada ESP32.
                Las claves antiguas que solo fueron guardadas como hash no se pueden recuperar; en ese caso genera una nueva.
            </p>
        </div>
    </div>

    @if (session('status') === 'iot-key-regenerated')
        <div class="mc-alert mc-alert-success mc-iot-key-flash">
            <i class="ph-duotone ph-check-circle"></i>
            <div>
                <strong>Clave regenerada para {{ session('iot_key_modulo') }}</strong>
                <p>Actualiza el firmware del ESP32 con estos valores y vuelve a cargar el código.</p>
                <pre>const char* MODULO_UID = "{{ session('iot_key_uid') }}";
const char* DEVICE_KEY = "{{ session('iot_key_plain') }}";</pre>
            </div>
            <button type="button" class="mc-btn mc-btn-secondary" data-copy-text="{{ session('iot_key_plain') }}">
                <i class="ph-duotone ph-copy"></i>
                Copiar key
            </button>
        </div>
    @endif

    <div class="mc-iot-key-list">
        @forelse(($modulosIot ?? collect()) as $modulo)
            @php
                $credencial = $modulo->credencial;
                $apiKeyVisible = $modulo->api_key_visible;
                $apiKeyInputId = 'iot_key_' . $modulo->id;
            @endphp

            <article class="mc-iot-key-card">
                <div class="mc-iot-key-main">
                    <div class="mc-iot-key-icon">
                        <i class="ph-duotone ph-cpu"></i>
                    </div>

                    <div>
                        <h3>{{ $modulo->codigo }} · {{ $modulo->nombre }}</h3>
                        <p>
                            UID: <strong>{{ $modulo->uid }}</strong>
                            @if($modulo->esta_online)
                                <span class="mc-iot-pill is-online">Online</span>
                            @else
                                <span class="mc-iot-pill is-offline">Offline</span>
                            @endif
                        </p>
                        <small>
                            Último contacto:
                            {{ $modulo->ultimo_contacto?->format('d/m/Y H:i:s') ?? 'Sin contacto registrado' }}
                            · Último uso key:
                            {{ $credencial?->ultimo_uso_en?->format('d/m/Y H:i:s') ?? 'Sin uso registrado' }}
                        </small>
                    </div>
                </div>

                <div class="mc-iot-key-fields">
                    <div class="mc-form-group">
                        <label for="iot_uid_{{ $modulo->id }}" class="mc-label">MODULO_UID</label>
                        <div class="mc-copy-row">
                            <input
                                id="iot_uid_{{ $modulo->id }}"
                                type="text"
                                class="mc-input mc-code-input"
                                value="{{ $modulo->uid }}"
                                readonly
                            >
                            <button type="button" class="mc-copy-btn" data-copy-target="#iot_uid_{{ $modulo->id }}">
                                <i class="ph-duotone ph-copy"></i>
                                Copiar
                            </button>
                        </div>
                    </div>

                    <div class="mc-form-group">
                        <label for="{{ $apiKeyInputId }}" class="mc-label">DEVICE_KEY</label>
                        <div class="mc-copy-row">
                            <input
                                id="{{ $apiKeyInputId }}"
                                type="text"
                                class="mc-input mc-code-input"
                                value="{{ $apiKeyVisible ?: 'Clave antigua no recuperable: genera una nueva.' }}"
                                readonly
                            >

                            @if($apiKeyVisible)
                                <button type="button" class="mc-copy-btn" data-copy-target="#{{ $apiKeyInputId }}">
                                    <i class="ph-duotone ph-copy"></i>
                                    Copiar
                                </button>
                            @else
                                <span class="mc-copy-btn is-disabled">
                                    <i class="ph-duotone ph-lock-key"></i>
                                    No visible
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mc-iot-key-footer">
                    <div class="mc-iot-firmware-snippet">
                        <span>Firmware ESP32:</span>
                        <code>const char* MODULO_UID = "{{ $modulo->uid }}";</code>
                        @if($apiKeyVisible)
                            <code>const char* DEVICE_KEY = "{{ $apiKeyVisible }}";</code>
                        @else
                            <code>const char* DEVICE_KEY = "NUEVA_KEY";</code>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('profile.iot-keys.regenerate', $modulo) }}"
                          onsubmit="return confirm('Esto cambiará la clave del módulo {{ $modulo->codigo }}. Debes actualizar el firmware del ESP32 con la nueva clave. ¿Continuar?')">
                        @csrf
                        <button type="submit" class="mc-btn mc-btn-secondary">
                            <i class="ph-duotone ph-arrows-clockwise"></i>
                            Generar nueva key
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="mc-security-note">
                <i class="ph-duotone ph-info"></i>
                <span>No hay módulos registrados todavía.</span>
            </div>
        @endforelse
    </div>
</section>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', async function (event) {
                const button = event.target.closest('[data-copy-target], [data-copy-text]');

                if (!button) {
                    return;
                }

                let text = button.dataset.copyText || '';

                if (button.dataset.copyTarget) {
                    const input = document.querySelector(button.dataset.copyTarget);
                    text = input ? input.value : '';
                }

                if (!text || text.includes('no recuperable')) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(text);
                    const oldHtml = button.innerHTML;
                    button.innerHTML = '<i class="ph-duotone ph-check"></i> Copiado';
                    button.classList.add('is-copied');

                    setTimeout(function () {
                        button.innerHTML = oldHtml;
                        button.classList.remove('is-copied');
                    }, 1600);
                } catch (error) {
                    alert('No se pudo copiar automáticamente. Selecciona el texto y cópialo manualmente.');
                }
            });
        </script>
    @endpush
@endonce
