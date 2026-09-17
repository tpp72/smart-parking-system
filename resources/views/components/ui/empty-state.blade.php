@props(['title' => 'ยังไม่มีข้อมูล', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 px-4 py-12 text-center']) }}>
    {{-- ต้นขั้วบัตรว่าง --}}
    <svg class="h-12 w-12 text-fg-3" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
        <path d="M8 12h32v8a4 4 0 0 0 0 8v8H8v-8a4 4 0 0 0 0-8z" />
        <path d="M30 13.5v3M30 21.5v5M30 31.5v3" stroke-dasharray="0.01 3.5" />
        <path d="M14 21h10M14 27h7" />
    </svg>
    <div class="max-w-sm">
        <p class="text-h3 text-fg">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-fg-2">{{ $description }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
