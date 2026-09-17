@if ($paginator->hasPages())
    @php
        $item = 'inline-flex min-h-touch min-w-touch items-center justify-center rounded-control border px-2 text-label num transition-colors duration-fast';
        $link = $item.' border-line bg-surface text-fg hover:border-field hover:bg-surface-2';
        $disabled = $item.' border-transparent text-fg-3';
    @endphp

    <nav role="navigation" aria-label="เลขหน้า" class="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
        <p class="text-caption text-fg-3">
            แสดง <span class="num text-fg-2">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            @if (method_exists($paginator, 'total'))
                จาก <span class="num text-fg-2">{{ $paginator->total() }}</span>
            @endif
            รายการ
        </p>

        <div class="flex flex-wrap items-center justify-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" role="link" aria-disabled="true" aria-label="หน้าก่อนหน้า">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3.5L5.5 8l4.5 4.5" /></svg>
                </span>
            @else
                <a class="{{ $link }}" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="หน้าก่อนหน้า">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3.5L5.5 8l4.5 4.5" /></svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $disabled }}" aria-hidden="true">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $item }} border-primary-ink font-semibold text-primary-ink" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="{{ $link }}" href="{{ $url }}" aria-label="ไปหน้า {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="{{ $link }}" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="หน้าถัดไป">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3.5L10.5 8 6 12.5" /></svg>
                </a>
            @else
                <span class="{{ $disabled }}" role="link" aria-disabled="true" aria-label="หน้าถัดไป">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3.5L10.5 8 6 12.5" /></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
