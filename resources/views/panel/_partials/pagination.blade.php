@props(['paginator' => null])

@if($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->total() > 0)
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
    @endphp

    <nav class="mc-pro-data-pagination" aria-label="Paginación">
        <div class="mc-pro-page-info">
            Mostrando <strong>{{ $paginator->firstItem() }}</strong> a <strong>{{ $paginator->lastItem() }}</strong> de <strong>{{ $paginator->total() }}</strong> registros
        </div>

        @if($paginator->hasPages())
            <div class="mc-pro-page-links">
                @if($paginator->onFirstPage())
                    <span class="mc-pro-page-link is-disabled">Anterior</span>
                @else
                    <a class="mc-pro-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
                @endif

                @if($start > 1)
                    <a class="mc-pro-page-link" href="{{ $paginator->url(1) }}">1</a>
                    @if($start > 2)
                        <span class="mc-pro-page-link is-ellipsis">…</span>
                    @endif
                @endif

                @for($page = $start; $page <= $end; $page++)
                    @if($page === $current)
                        <span class="mc-pro-page-link is-active">{{ $page }}</span>
                    @else
                        <a class="mc-pro-page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endfor

                @if($end < $last)
                    @if($end < $last - 1)
                        <span class="mc-pro-page-link is-ellipsis">…</span>
                    @endif
                    <a class="mc-pro-page-link" href="{{ $paginator->url($last) }}">{{ $last }}</a>
                @endif

                @if($paginator->hasMorePages())
                    <a class="mc-pro-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
                @else
                    <span class="mc-pro-page-link is-disabled">Siguiente</span>
                @endif
            </div>
        @endif
    </nav>
@endif
