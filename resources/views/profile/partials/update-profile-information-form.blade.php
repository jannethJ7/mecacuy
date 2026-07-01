<section class="mc-form-section">

    <div class="mc-section-header">
        <div>
            <h2>
                <i class="ph-duotone ph-user-circle"></i>
                Información del perfil
            </h2>

            <p>
                Actualiza tu nombre de usuario y correo electrónico asociado a tu cuenta.
            </p>
        </div>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mc-form">
        @csrf
        @method('patch')

        <div class="mc-form-grid">

            <div class="mc-form-group">
                <label for="name" class="mc-label">
                    Nombre completo
                </label>

                <input
                    id="name"
                    name="name"
                    type="text"
                    class="mc-input"
                    value="{{ old('name', $user->name) }}"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Ej. Andrés Rodríguez"
                >

                @error('name')
                    <span class="mc-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="mc-form-group">
                <label for="email" class="mc-label">
                    Correo electrónico
                </label>

                <input
                    id="email"
                    name="email"
                    type="email"
                    class="mc-input"
                    value="{{ old('email', $user->email) }}"
                    required
                    autocomplete="username"
                    placeholder="usuario@correo.com"
                >

                @error('email')
                    <span class="mc-error">{{ $message }}</span>
                @enderror
            </div>

        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="mc-alert mc-alert-warning">
                <div>
                    <strong>Correo no verificado</strong>
                    <p>
                        Tu dirección de correo electrónico aún no fue verificada.
                    </p>
                </div>

                <button
                    form="send-verification"
                    class="mc-link-button"
                    type="submit"
                >
                    Reenviar verificación
                </button>
            </div>

            @if (session('status') === 'verification-link-sent')
                <div class="mc-alert mc-alert-success">
                    <i class="ph-duotone ph-check-circle"></i>
                    <span>
                        Se envió un nuevo enlace de verificación a tu correo electrónico.
                    </span>
                </div>
            @endif
        @endif

        <div class="mc-form-actions">
            <button type="submit" class="mc-btn mc-btn-primary">
                <i class="ph-duotone ph-floppy-disk"></i>
                Guardar cambios
            </button>

            @if (session('status') === 'profile-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="mc-saved-message"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                    Cambios guardados correctamente
                </span>
            @endif
        </div>

    </form>

</section>