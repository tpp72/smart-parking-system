@props(['messages' => [], 'id' => null])

@php
    $messages = array_values(array_filter((array) $messages, fn ($message) => filled($message)));
@endphp

@if ($messages)
    <div @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'flex items-start gap-1.5 text-caption text-danger']) }}>
        <svg class="mt-px h-4 w-4 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
            <circle cx="8" cy="8" r="6.25" />
            <path d="M8 4.75v3.75M8 11.25v.01" />
        </svg>
        <div class="min-w-0 space-y-0.5">
            @foreach ($messages as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    </div>
@endif
