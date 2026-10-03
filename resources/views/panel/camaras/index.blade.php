@extends('layouts.panel')
@include('panel._partials.pro-assets')
@section('title', 'Cámaras')
@section('page-title', 'Cámaras')
@section('page-subtitle', 'Supervisión visual distribuida por módulo mediante MediaMTX')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $camaras instanceof \Illuminate\Pagination\AbstractPaginator ? $camaras->getCollection() : collect($camaras ?? []);
@endphp

<div class="mc-pro-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Video',
        'title' => 'Cámaras por jaula',
        'buttonRoute' => 'panel.camaras.create',
        'buttonIcon' => 'ri-camera-line',
        'buttonText' => 'Nueva cámara',
        'buttonRoles' => ['admin']
    ])

    <section class="mc-pro-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line"></i>
            <input type="search" placeholder="Buscar cámara, módulo o stream..." data-mc-search="#camarasList">
        </label>
        <div class="mc-pro-toolbar-actions">
            <button type="button" class="mc-pro-chip is-active" data-mc-filter="#camarasList" data-filter-value="all">Todas</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#camarasList" data-filter-value="activo">Habilitadas</button>
            <button type="button" class="mc-pro-chip" data-mc-filter="#camarasList" data-filter-value="inactivo">Deshabilitadas</button>
        </div>
    </section>

    <div id="camarasList" class="mc-camera-grid">
        @forelse($items as $camara)
            @php
                $search = strtolower(($camara->nombre ?? '').' '.($camara->codigo ?? '').' '.($camara->stream_key ?? '').' '.($camara->modulo->codigo ?? ''));
            @endphp
            <article class="mc-camera-card" data-search-row data-search="{{ $search }}" data-filter="{{ $camara->habilitada ? 'activo' : 'inactivo' }}">
                <a href="{{ route('panel.camaras.show', $camara) }}" class="mc-camera-preview">
                    <div class="mc-camera-preview-placeholder">
                        <i class="ri-camera-3-line"></i>
                        <span>Ver transmisión</span>
                    </div>
                    <span class="mc-camera-live {{ $camara->habilitada ? '' : 'is-off' }}">
                        {{ $camara->habilitada ? 'HABILITADA' : 'OFF' }}
                    </span>
                </a>

                <div class="mc-camera-body">
                    <div>
                        <small>{{ $camara->modulo->codigo ?? 'Sin módulo' }}</small>
                        <h3>{{ $camara->nombre }}</h3>
                        <p>{{ $camara->codigo }} · path: {{ $camara->stream_key }}</p>
                    </div>

                    <div class="mc-pro-card-actions">
                        <a class="mc-pro-btn mc-pro-btn-primary" href="{{ route('panel.camaras.show', $camara) }}">
                            <i class="ri-live-line"></i> Ver cámara
                        </a>
                        @if($canAdmin)
                            <a class="mc-pro-btn mc-pro-btn-soft" href="{{ route('panel.camaras.edit', $camara) }}"><i class="ri-pencil-line"></i> Editar</a>
                            <form method="POST" action="{{ route('panel.camaras.destroy', $camara) }}" data-mc-confirm="¿Eliminar esta cámara del módulo?">
                                @csrf
                                @method('DELETE')
                                <button class="mc-pro-icon-danger"><i class="ri-delete-bin-line"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            @include('panel._partials.empty', [
                'title' => 'No hay cámaras registradas',
                'message' => 'Registra la primera cámara y asígnala a MOD-001 para probar la integración.',
                'icon' => 'ri-camera-off-line'
            ])
        @endforelse
    </div>

    @if($camaras instanceof \Illuminate\Pagination\AbstractPaginator)
        @include('panel._partials.pagination', ['paginator' => $camaras])
    @endif
</div>

<style>
.mc-camera-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px}
.mc-camera-card{background:#fff;border:1px solid #e7ebf0;border-radius:20px;overflow:hidden;box-shadow:0 12px 30px rgba(29,44,63,.07)}
.mc-camera-preview{position:relative;display:block;aspect-ratio:16/9;background:#101820;text-decoration:none;overflow:hidden}
.mc-camera-preview-placeholder{position:absolute;inset:0;display:flex;flex-direction:column;gap:8px;align-items:center;justify-content:center;color:#d9e3ec;background:radial-gradient(circle at 50% 30%,#263747,#111820 70%)}
.mc-camera-preview-placeholder i{font-size:42px}.mc-camera-preview-placeholder span{font-weight:700}
.mc-camera-live{position:absolute;left:14px;top:14px;padding:6px 10px;border-radius:999px;background:#dc3545;color:#fff;font-size:11px;font-weight:800;letter-spacing:.08em}.mc-camera-live.is-off{background:#667085}
.mc-camera-body{padding:18px}.mc-camera-body small{color:#708090;font-weight:700}.mc-camera-body h3{margin:4px 0}.mc-camera-body p{color:#667085;word-break:break-word}
</style>
@endsection
