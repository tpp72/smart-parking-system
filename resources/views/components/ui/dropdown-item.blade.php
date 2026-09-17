@props(['href' => null, 'type' => 'button', 'tone' => 'default'])

@php
    $classes = [
        'flex w-full min-h-touch items-center gap-2 px-3 text-start text-body transition-colors duration-fast',
        'hover:bg-surface-2 focus:bg-surface-2 focus:outline-none focus-visible:outline-none focus-visible:shadow-[inset_2px_0_0_rgb(var(--color-primary-ink))]',
        $tone === 'danger' ? 'text-danger' : 'text-fg',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
