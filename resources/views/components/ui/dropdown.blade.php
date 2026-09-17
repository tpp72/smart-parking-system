{{--
    Dropdown menu — slot "trigger" (ปุ่ม) + slot "content" (<x-ui.dropdown-item>)
    คีย์บอร์ด: Enter/Space/↓ เปิด · ↑↓ Home End เลื่อน · Escape ปิดและคืนโฟกัส · Tab ปิด
--}}
@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1'])

@php
    $alignmentClasses = match ($align) {
        'left' => 'start-0 origin-top-left',
        'top' => 'origin-top',
        default => 'end-0 origin-top-right',
    };
    $widthClass = match ((string) $width) {
        '48' => 'w-48',
        '56' => 'w-56',
        '64' => 'w-64',
        default => $width,
    };
    $menuId = 'menu-'.\Illuminate\Support\Str::random(8);
@endphp

<div x-data="spDropdown" x-on:click.outside="close(false)" {{ $attributes->merge(['class' => 'relative']) }}>
    <div x-ref="trigger" x-on:click="toggle()" x-on:keydown="onTriggerKeydown($event)">
        {{ $trigger }}
    </div>

    <div x-ref="menu" id="{{ $menuId }}" role="menu"
        x-show="open"
        x-on:keydown="onMenuKeydown($event)"
        x-on:click="close(false)"
        x-transition:enter="transition duration-fast ease-out"
        x-transition:enter-start="-translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-fast ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-dropdown mt-1 rounded-card border border-line bg-surface shadow-overlay {{ $widthClass }} {{ $alignmentClasses }} {{ $contentClasses }}"
        style="display: none;">
        {{ $content }}
    </div>
</div>
