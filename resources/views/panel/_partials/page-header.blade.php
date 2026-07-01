@php
    $buttonRoles = $buttonRoles ?? null;
    $currentRole = auth()->user()->rol ?? 'lector';
    $canSeeButton = is_null($buttonRoles) || in_array($currentRole, (array) $buttonRoles, true);
@endphp

<div class="mc-pro-header mc-hero-modulos">
    <div>
        <span class="mc-pro-eyebrow">{{ $eyebrow ?? 'MECACUY PRO' }}</span>
        <h2>{{ $title ?? 'Panel' }}</h2>
        <p>{{ $subtitle ?? '' }}</p>
    </div>

    @isset($buttonRoute)
        @if($canSeeButton && Route::has($buttonRoute))
            <a href="{{ route($buttonRoute) }}" class="mc-pro-btn mc-pro-btn-primary">
                <i class="{{ $buttonIcon ?? 'ri-add-line' }}"></i>
                {{ $buttonText ?? 'Nuevo registro' }}
            </a>
        @endif
    @endisset
</div>
