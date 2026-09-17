{{--
    เลือกธีม: สว่าง / มืด / ตามระบบ (ค่าเริ่มต้น) — radiogroup, ลูกศรเลื่อนตัวเลือก
    compact = ไอคอนอย่างเดียว · popover = ปุ่มไอคอนเดียว เปิดแผงตัวเลือก (ใช้ในแถบนำทางที่พื้นที่จำกัด)
--}}
@props(['compact' => false, 'popover' => false])

@php
    $options = [
        'light'  => ['label' => 'สว่าง', 'icon' => '<circle cx="10" cy="10" r="3.25"/><path d="M10 2.75v1.5M10 15.75v1.5M2.75 10h1.5M15.75 10h1.5M4.87 4.87l1.06 1.06M14.07 14.07l1.06 1.06M4.87 15.13l1.06-1.06M14.07 5.93l1.06-1.06"/>'],
        'dark'   => ['label' => 'มืด', 'icon' => '<path d="M16.25 12.1A6.75 6.75 0 0 1 7.9 3.75a6.75 6.75 0 1 0 8.35 8.35z"/>'],
        'system' => ['label' => 'ตามระบบ', 'icon' => '<rect x="2.75" y="3.75" width="14.5" height="9.5" rx="1"/><path d="M7.5 16.25h5M10 13.25v3"/>'],
    ];
    $iconOnly = $compact && ! $popover;
    $panelId = 'sp-theme-panel-'.\Illuminate\Support\Str::random(6);
@endphp

@if ($popover)
<div x-data="spThemeSwitch" class="relative" x-on:keydown.escape.stop="close(true)" x-on:click.outside="close()"
    {{ $attributes }}>
    <button type="button" x-ref="trigger" x-on:click="toggle()"
        aria-haspopup="true" aria-expanded="false" x-bind:aria-expanded="open.toString()" aria-controls="{{ $panelId }}"
        x-bind:aria-label="'ธีมการแสดงผล: ' + labels[preference]" aria-label="ธีมการแสดงผล"
        class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-card text-fg-2 transition-colors duration-fast hover:bg-surface-2 hover:text-fg active:translate-y-px aria-expanded:bg-surface-2 aria-expanded:text-fg">
        @foreach ($options as $value => $option)
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                @if ($value !== 'system') style="display:none" @endif
                x-show="preference === '{{ $value }}'">{!! $option['icon'] !!}</svg>
        @endforeach
    </button>
@endif

<div @if (! $popover) x-data="spThemeSwitch" {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-card border border-line bg-surface p-0.5']) }}
    @else id="{{ $panelId }}" x-cloak x-show="open" x-transition.opacity.duration.150ms
        class="absolute right-0 top-full z-dropdown mt-1 flex w-44 flex-col gap-0.5 rounded-card border border-line bg-surface p-1 shadow-overlay"
    @endif
    role="radiogroup" aria-label="ธีมการแสดงผล"
    x-on:keydown.arrow-right.prevent="move(1)" x-on:keydown.arrow-down.prevent="move(1)"
    x-on:keydown.arrow-left.prevent="move(-1)" x-on:keydown.arrow-up.prevent="move(-1)">
    @foreach ($options as $value => $option)
        <button type="button" role="radio" data-theme-option="{{ $value }}"
            aria-checked="{{ $value === 'system' ? 'true' : 'false' }}"
            tabindex="{{ $value === 'system' ? 0 : -1 }}"
            x-bind:aria-checked="(preference === '{{ $value }}').toString()"
            x-bind:tabindex="preference === '{{ $value }}' ? 0 : -1"
            x-on:click="choose('{{ $value }}')"
            @if ($iconOnly) aria-label="ธีม{{ $option['label'] }}" title="ธีม{{ $option['label'] }}" @endif
            @class([
                'inline-flex min-h-touch items-center gap-1.5 rounded-sm text-label text-fg-2 transition-colors duration-fast',
                'hover:bg-surface-2 hover:text-fg',
                'aria-checked:bg-surface-2 aria-checked:text-fg',
                'justify-center aria-checked:shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => ! $popover,
                'w-full justify-start gap-2.5 px-2.5 aria-checked:shadow-[inset_2px_0_0_rgb(var(--color-primary-ink))]' => $popover,
                'min-w-touch' => $iconOnly,
                'px-3' => ! $iconOnly && ! $popover,
            ])>
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"
                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $option['icon'] !!}</svg>
            @unless ($iconOnly)
                <span>{{ $option['label'] }}</span>
            @endunless
        </button>
    @endforeach
</div>

@if ($popover)
</div>
@endif
