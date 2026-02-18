@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-center">
        <div class="inline-flex items-center gap-2">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="sp-page sp-page-disabled" aria-disabled="true">‹</span>
            @else
                <a class="sp-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                {{-- Three Dots --}}
                @if (is_string($element))
                    <span class="sp-page sp-page-disabled">{{ $element }}</span>
                @endif

                {{-- Page Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sp-page sp-page-active" aria-current="page">
                                {{ $page }}
                            </span>
                        @else
                            <a class="sp-page" href="{{ $url }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a class="sp-page" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
            @else
                <span class="sp-page sp-page-disabled" aria-disabled="true">›</span>
            @endif

        </div>
    </nav>
@endif
