@props(['active' => false])

@php
    $classes = $active
        ? 'block w-full px-4 py-2 rounded-xl
       text-start text-base font-bold tracking-wide
       text-red-200 bg-red-600/20
       ring-1 ring-red-900/70
       shadow-[0_0_8px_rgba(220,38,38,0.3)]
       transition-all duration-200 ease-in-out'
        : 'block w-full px-4 py-2 rounded-xl
       text-start text-base font-bold tracking-wide
       text-gray-200
       hover:text-red-300 hover:bg-red-900/30
       focus:outline-none focus:bg-red-900/40 focus:text-red-200
       transition-all duration-200 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
