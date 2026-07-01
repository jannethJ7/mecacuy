<section class="mc-form-section">

    <div class="mc-section-header">
        <div>
            <h2>
                <i class="ph-duotone ph-lock-key"></i>
                Actualizar contraseña
            </h2>

            <p>
                Mantén tu cuenta segura usando una contraseña larga, única y difícil de adivinar.
            </p>
        </div>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="mc-form">
        @csrf
        @method('put')

        <div class="mc-form-grid">

            <div class="mc-form-group">
                <label for="update_password_current_password" class="mc-label">
                    Contraseña actual
                </label>

                <input
                    id="update_password_current_password"
                    name="current_password"
                    type="password"
                    class="mc-input"
                    autocomplete="current-password"
                    placeholder="Ingresa tu contraseña actual"
                >

                @if ($errors->updatePassword->has('current_password'))
                    <span class="mc-error">
                        {{ $errors->updatePassword->first('current_password') }}
                    </span>
                @endif
            </div>

            <div class="mc-form-group">
                <label for="update_password_password" class="mc-label">
                    Nueva contraseña
                </label>

                <input
                    id="update_password_password"
                    name="password"
                    type="password"
                    class="mc-input"
                    autocomplete="new-password"
                    placeholder="Ingresa una nueva contraseña"
                >

                @if ($errors->updatePassword->has('password'))
                    <span class="mc-error">
                        {{ $errors->updatePassword->first('password') }}
                    </span>
                @endif
            </div>

            <div class="mc-form-group">
                <label for="update_password_password_confirmation" class="mc-label">
                    Confirmar contraseña
                </label>

                <input
                    id="update_password_password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="mc-input"
                    autocomplete="new-password"
                    placeholder="Repite la nueva contraseña"
                >

                @if ($errors->updatePassword->has('password_confirmation'))
                    <span class="mc-error">
                        {{ $errors->updatePassword->first('password_confirmation') }}
                    </span>
                @endif
            </div>

        </div>

        <div class="mc-security-note">
            <i class="ph-duotone ph-shield-check"></i>
            <span>
                Recomendación: usa mínimo 8 caracteres, combinando letras, números y símbolos.
            </span>
        </div>

        <div class="mc-form-actions">
            <button type="submit" class="mc-btn mc-btn-primary">
                <i class="ph-duotone ph-floppy-disk"></i>
                Guardar contraseña
            </button>

            @if (session('status') === 'password-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="mc-saved-message"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                    Contraseña actualizada correctamente
                </span>
            @endif
        </div>
    </form>

</section>