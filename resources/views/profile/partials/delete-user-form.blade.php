<section class="mc-form-section mc-danger-section">

    <div class="mc-section-header">
        <div>
            <h2 class="mc-danger-title">
                <i class="ph-duotone ph-warning-circle"></i>
                Eliminar cuenta
            </h2>

            <p>
                Una vez eliminada tu cuenta, todos los recursos y datos asociados serán eliminados permanentemente.
                Antes de continuar, asegúrate de guardar cualquier información importante.
            </p>
        </div>
    </div>

    <div class="mc-danger-box">
        <div>
            <strong>Zona de riesgo</strong>
            <p>
                Esta acción no se puede deshacer. Para eliminar la cuenta será necesario confirmar tu contraseña.
            </p>
        </div>

        <button
            type="button"
            class="mc-btn mc-btn-danger"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >
            <i class="ph-duotone ph-trash"></i>
            Eliminar cuenta
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="mc-modal-form">
            @csrf
            @method('delete')

            <div class="mc-modal-icon-danger">
                <i class="ph-duotone ph-warning-octagon"></i>
            </div>

            <h2>
                ¿Estás seguro de eliminar tu cuenta?
            </h2>

            <p>
                Esta acción eliminará permanentemente tu cuenta y la información asociada.
                Ingresa tu contraseña para confirmar la eliminación definitiva.
            </p>

            <div class="mc-form-group">
                <label for="delete_password" class="mc-label">
                    Contraseña actual
                </label>

                <input
                    id="delete_password"
                    name="password"
                    type="password"
                    class="mc-input"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                >

                @if ($errors->userDeletion->has('password'))
                    <span class="mc-error">
                        {{ $errors->userDeletion->first('password') }}
                    </span>
                @endif
            </div>

            <div class="mc-modal-actions">
                <button
                    type="button"
                    class="mc-btn mc-btn-secondary"
                    x-on:click="$dispatch('close')"
                >
                    Cancelar
                </button>

                <button type="submit" class="mc-btn mc-btn-danger">
                    <i class="ph-duotone ph-trash"></i>
                    Eliminar definitivamente
                </button>
            </div>
        </form>
    </x-modal>

</section>