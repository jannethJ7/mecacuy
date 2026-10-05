@extends('layouts.panel')
@include('panel._partials.pro-assets')
@include('panel._partials.compact-assets')
@section('title', 'Cámaras')
@section('page-title', 'Cámaras')
@section('page-subtitle', 'Cámaras registradas por módulo y acceso a la transmisión')

@section('content')
@php
    $rol = auth()->user()->rol ?? 'lector';
    $canAdmin = $rol === 'admin';
    $items = $camaras instanceof \Illuminate\Pagination\AbstractPaginator ? $camaras->getCollection() : collect($camaras ?? []);
@endphp

<div class="mc-pro-page mc-compact-page">
    @include('panel._partials.flash')

    @include('panel._partials.page-header', [
        'eyebrow' => 'Video',
        'title' => 'Cámaras por jaula',
        'buttonRoute' => 'panel.camaras.create',
        'buttonIcon' => 'ri-camera-line',
        'buttonText' => 'Nueva cámara',
        'buttonRoles' => ['admin']
    ])

    <form method="GET" action="{{ route('panel.camaras.index') }}" class="mc-pro-toolbar mc-compact-toolbar">
        <label class="mc-pro-search">
            <i class="ri-search-line" aria-hidden="true"></i>
            <input type="search" name="buscar" value="{{ request('buscar') }}" aria-label="Buscar registros" placeholder="Buscar nombre, código, módulo o tipo...">
        </label>
        <label class="mc-compact-select">Módulo
            <select name="modulo_id">
                <option value="">Todos los módulos</option>
                @foreach($modulos as $modulo)
                    <option value="{{ $modulo->id }}" @selected((string)request('modulo_id') === (string)$modulo->id)>{{ $modulo->codigo }} · {{ $modulo->nombre }}</option>
                @endforeach
            </select>
        </label>
        <label class="mc-compact-select">Estado
            <select name="estado"><option value="">Todos</option><option value="activo" @selected(request("estado") === "activo")>Habilitadas</option><option value="inactivo" @selected(request("estado") === "inactivo")>Deshabilitadas</option></select>
        </label>
        <div class="mc-pro-toolbar-actions">
            <button type="submit" class="mc-pro-btn mc-pro-btn-soft"><i class="ri-filter-line" aria-hidden="true"></i> Filtrar</button>
            @if(request()->filled('buscar') || request()->filled('modulo_id') || request()->filled('estado'))
                <a href="{{ route('panel.camaras.index') }}" class="mc-pro-btn mc-pro-btn-ghost">Limpiar</a>
            @endif
        </div>
    </form>

    <div id="camarasList" class="mc-compact-list">
        @forelse($items as $camara)
            @php
                $search = strtolower(($camara->nombre ?? '').' '.($camara->codigo ?? '').' '.($camara->stream_key ?? '').' '.($camara->modulo->codigo ?? ''));
            @endphp
            <article class="mc-compact-camera" data-search-row data-search="{{ $search }}" data-filter="{{ $camara->habilitada ? 'activo' : 'inactivo' }}">
                <div class="mc-compact-camera-icon" aria-hidden="true"><i class="ri-camera-line"></i></div>
                <span class="mc-pro-badge {{ $camara->habilitada ? 'is-success' : 'is-muted' }}">{{ $camara->habilitada ? 'Habilitada' : 'Deshabilitada' }}</span>

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
                                <button type="submit" class="mc-pro-icon-danger" aria-label="Eliminar registro" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
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

@endsection
