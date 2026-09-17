@props([
    'variant' => 'primary',   // primary | secondary | ghost | danger
    'size' => 'md',           // md | sm (ทั้งคู่สูงอย่างน้อย 44px)
    'type' => 'button',
    'href' => null,
    'loading' => false,
    'disabled' => false,
    'iconOnly' => false,
    'label' => null,          // จำเป็นเมื่อ iconOnly — ใช้เป็น aria-label
])

@php
    if ($iconOnly && blank($label)) {
        throw new InvalidArgumentException('<x-ui.button icon-only> ต้องระบุ label สำหรับ aria-label');
    }

    $variants = [
        'primary'   => 'border-transparent bg-primary text-on-primary hover:bg-primary-hover active:bg-primary-hover',
        'secondary' => 'border-field bg-surface text-fg hover:bg-surface-2 active:bg-line/60',
        'ghost'     => 'border-transparent bg-transparent text-fg hover:bg-surface-2 active:bg-line/60',
        'danger'    => 'border-transparent bg-danger text-on-danger hover:bg-danger/90 active:bg-danger/80',
    ];

    $sizes = [
        'md' => $iconOnly ? 'w-touch' : 'px-4 text-body',
        'sm' => $iconOnly ? 'w-touch' : 'px-3 text-label',
    ];

    // สีตัวอักษรที่ส่งมาทาง class (เช่น ปุ่ม ghost สีแดง "ลบ") ต้องชนะสีของ variant —
    // ใน CSS ที่ build ออกมา .text-fg อยู่หลัง .text-danger จึงต้องตัด text-fg ของ variant ออก
    $variantClass = $variants[$variant] ?? $variants['primary'];
    if (preg_match('/(^|\s)text-(danger|warning|success|primary-ink|fg-2|fg-3)(\s|$)/', (string) $attributes->get('class'))) {
        $variantClass = trim(preg_replace('/(^|\s)text-fg(?=\s|$)/', ' ', $variantClass));
    }

    $classes = implode(' ', [
        'group relative inline-flex min-h-touch select-none items-center justify-center gap-2 whitespace-nowrap rounded-card border font-semibold',
        'transition-colors duration-fast ease-out active:translate-y-px',
        'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        'data-[loading]:cursor-progress data-[loading]:opacity-100', // กำลังบันทึก: อ่านออกเต็มที่ ไม่จางแบบ disabled
        $variantClass,
        $sizes[$size] ?? $sizes['md'],
    ]);

    $isDisabled = $disabled || $loading;
@endphp

@if ($href)
    <a href="{{ $isDisabled ? null : $href }}"
        @if ($isDisabled) aria-disabled="true" @endif
        @if ($iconOnly) aria-label="{{ $label }}" @endif
        @if ($loading) aria-busy="true" data-loading @endif
        {{ $attributes->class($classes) }}>
        <x-ui.spinner size="sm" class="{{ $loading ? '' : 'hidden' }} group-data-[loading]:inline" />
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
        @disabled($isDisabled)
        @if ($iconOnly) aria-label="{{ $label }}" @endif
        @if ($loading) aria-busy="true" data-loading @endif
        {{ $attributes->class($classes) }}>
        <x-ui.spinner size="sm" class="hidden group-data-[loading]:inline" />
        {{ $slot }}
    </button>
@endif
