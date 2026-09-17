@props(['id' => null])

<p @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'text-caption text-fg-3']) }}>{{ $slot }}</p>
