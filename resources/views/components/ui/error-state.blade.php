@props(['title' => 'โหลดข้อมูลไม่สำเร็จ', 'description' => 'ลองใหม่อีกครั้ง หากยังไม่ได้ให้ติดต่อผู้ดูแลระบบ'])

<div role="alert" {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 px-4 py-12 text-center']) }}>
    <svg class="h-12 w-12 text-danger" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M24 7l19 34H5z" />
        <path d="M24 19v10M24 35v.01" />
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
