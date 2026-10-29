@php
    $verificacion = app(\App\Services\SupervisionCalefaccion::class)->paraActuador($actuadorVerificado);
    $esCalefactor = in_array(strtoupper($actuadorVerificado->codigo), ['D_CALEF', 'D_HEAT', 'D_RESISTENCIA', 'D_CALEFACTOR'], true);
@endphp
@if($esCalefactor || $verificacion['codigo'] !== 'no_configurada')
    <div class="mc-verificacion-calefaccion" data-verificacion-calefaccion
         data-url="{{ route('panel.actuadores.verificacion', $actuadorVerificado) }}"
         data-estado="{{ $verificacion['codigo'] }}" role="status" aria-live="polite">
        <small>Verificación eléctrica</small>
        <strong data-verificacion-etiqueta>{{ $verificacion['etiqueta'] }}</strong>
        <small data-verificacion-fecha>{{ isset($verificacion['medido_en']) ? 'Medido: '.\Carbon\Carbon::parse($verificacion['medido_en'])->locale('es')->diffForHumans() : 'Sin medición válida reciente' }}</small>
    </div>
    @once
        @push('styles')
            <link rel="stylesheet" href="{{ asset('dashboard/assets/css/mecacuy/verificacion-calefaccion.css') }}">
        @endpush
        @push('scripts')
            <script src="{{ asset('dashboard/assets/js/mecacuy/verificacion-calefaccion.js') }}" defer></script>
        @endpush
    @endonce
@endif
