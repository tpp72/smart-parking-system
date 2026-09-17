@props(['tone' => 'info', 'title' => null, 'dismissible' => false])

@php
    $tone = in_array($tone, ['info', 'success', 'warning', 'danger'], true) ? $tone : 'info';

    $borders = ['info' => 'border-primary-ink/50', 'success' => 'border-success/60', 'warning' => 'border-warning/60', 'danger' => 'border-danger/60'];
    $inks = ['info' => 'text-primary-ink', 'success' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger'];

    $icons = [
        'info'    => '<circle cx="10" cy="10" r="7.25"/><path d="M10 9v4.5M10 6.5v.01"/>',
        'success' => '<circle cx="10" cy="10" r="7.25"/><path d="M6.75 10.25l2.25 2.25 4.25-4.5"/>',
        'warning' => '<path d="M10 3.25l7.25 13H2.75z"/><path d="M10 8.25v3.5M10 14v.01"/>',
        'danger'  => '<circle cx="10" cy="10" r="7.25"/><path d="M7.5 7.5l5 5M12.5 7.5l-5 5"/>',
    ];
@endphp

<div role="{{ in_array($tone, ['warning', 'danger'], true) ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="{ open: true }" x-show="open" @endif
    {{ $attributes->class(['flex items-start gap-3 rounded-card border bg-surface p-4 text-body', $borders[$tone]]) }}>
    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $inks[$tone] }}" viewBox="0 0 20 20" fill="none" stroke="currentColor"
        stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$tone] !!}</svg>

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold text-fg">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5 ' : '' }}text-fg-2">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <x-ui.button variant="ghost" icon-only label="ปิดข้อความนี้" class="-my-2.5 -mr-2 text-fg-3" x-on:click="open = false">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9" /></svg>
        </x-ui.button>
    @endif
</div>
