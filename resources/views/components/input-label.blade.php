@props(['value'])

<label {{ $attributes->merge([
    'class' => 'block text-sm font-bold tracking-wide text-red-400 sp-glow-text',
]) }}>
    {{ $value ?? $slot }}
</label>
