{{--
    Tooltip — ข้อความเสริมเมื่อ hover หรือ focus (ผูก aria-describedby ให้ตัวกระตุ้นอัตโนมัติ)
    ห้ามใช้แทน label: ปุ่มไอคอนอย่างเดียวต้องมี aria-label เสมอ
--}}
@props(['text', 'placement' => 'top'])

@php
    $id = 'tip-'.\Illuminate\Support\Str::random(8);
    $position = $placement === 'bottom' ? 'top-full mt-1.5' : 'bottom-full mb-1.5';
@endphp

<span x-data="spTooltip({ id: @js($id) })"
    x-on:mouseenter="showTip()" x-on:mouseleave="hideTip()"
    x-on:focusin="showTip()" x-on:focusout="hideTip()"
    x-on:keydown.escape="hideTip()"
    {{ $attributes->merge(['class' => 'relative inline-flex']) }}>
    <span x-ref="target" class="inline-flex">{{ $slot }}</span>
    <span id="{{ $id }}" role="tooltip" x-show="visible" x-cloak
        class="pointer-events-none absolute left-1/2 z-dropdown -translate-x-1/2 whitespace-nowrap rounded-control bg-fg px-2 py-1 text-caption text-page {{ $position }}">
        {{ $text }}
    </span>
</span>
