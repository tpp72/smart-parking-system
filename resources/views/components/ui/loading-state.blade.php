{{-- สถานะกำลังโหลดเฉพาะส่วน (ไม่บังทั้งหน้า) — variant: spinner | skeleton --}}
@props(['label' => 'กำลังโหลดข้อมูล', 'variant' => 'spinner', 'rows' => 3])

<div role="status" aria-live="polite" {{ $attributes->merge(['class' => 'text-fg-2']) }}>
    @if ($variant === 'skeleton')
        <span class="sr-only">{{ $label }}</span>
        <div class="space-y-3" aria-hidden="true">
            @for ($i = 0; $i < $rows; $i++)
                <div class="h-4 animate-pulse rounded-control bg-surface-2 motion-reduce:animate-none" style="width: {{ [100, 86, 64][$i % 3] }}%"></div>
            @endfor
        </div>
    @else
        <div class="flex items-center justify-center gap-3 px-4 py-10">
            <x-ui.spinner class="text-primary-ink" />
            <span>{{ $label }}</span>
        </div>
    @endif
</div>
