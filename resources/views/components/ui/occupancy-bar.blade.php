{{--
    สถานะช่องจอด ณ ตอนนี้: แถบซ้อน ใช้งาน / จอง / ว่าง + ตัวเลขกำกับเสมอ (ไม่สื่อด้วยสีอย่างเดียว)
    <x-ui.occupancy-bar :available="12" :reserved="3" :occupied="5" />
--}}
@props(['available' => 0, 'reserved' => 0, 'occupied' => 0])

@php
    $total = max(0, (int) $available + (int) $reserved + (int) $occupied);
    $pct = fn ($n) => $total > 0 ? round($n / $total * 100, 2) : 0;
@endphp

<div {{ $attributes->class('w-full') }}>
    <div class="flex h-2.5 overflow-hidden rounded-full bg-line" role="img"
        aria-label="ช่องจอด {{ $total }} ช่อง: ใช้งาน {{ $occupied }} จอง {{ $reserved }} ว่าง {{ $available }}">
        <span class="h-full bg-danger" style="width: {{ $pct($occupied) }}%"></span>
        <span class="h-full bg-warning" style="width: {{ $pct($reserved) }}%"></span>
        <span class="h-full bg-success/70" style="width: {{ $pct($available) }}%"></span>
    </div>
    <p class="mt-1.5 flex flex-wrap gap-x-3 text-caption text-fg-2" aria-hidden="true">
        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-danger align-middle"></span>ใช้งาน <span class="tabular font-semibold text-fg">{{ $occupied }}</span></span>
        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-warning align-middle"></span>จอง <span class="tabular font-semibold text-fg">{{ $reserved }}</span></span>
        <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-success/70 align-middle"></span>ว่าง <span class="tabular font-semibold text-fg">{{ $available }}</span></span>
        <span class="text-fg-3">จาก <span class="tabular">{{ $total }}</span> ช่อง</span>
    </p>
</div>
