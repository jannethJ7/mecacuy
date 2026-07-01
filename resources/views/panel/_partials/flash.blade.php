@if(session('success') || session('ok') || session('status') || session('error'))
    <div class="mc-pro-flash {{ session('error') ? 'is-danger' : 'is-success' }}" data-mc-autohide>
        <span class="mc-pro-flash-icon">
            <i class="{{ session('error') ? 'ri-error-warning-line' : 'ri-check-double-line' }}"></i>
        </span>
        <div>
            <strong>{{ session('error') ? 'Revisa la operación' : 'Operación realizada' }}</strong>
            <p>{{ session('success') ?? session('ok') ?? session('status') ?? session('error') }}</p>
        </div>
        <button type="button" data-mc-dismiss aria-label="Cerrar">×</button>
    </div>
@endif

@if($errors->any())
    <div class="mc-pro-flash is-danger">
        <span class="mc-pro-flash-icon">
            <i class="ri-error-warning-line"></i>
        </span>
        <div>
            <strong>Hay datos por corregir</strong>
            <p>{{ $errors->first() }}</p>
        </div>
        <button type="button" data-mc-dismiss aria-label="Cerrar">×</button>
    </div>
@endif
