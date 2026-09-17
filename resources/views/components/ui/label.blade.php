@props(['for' => null, 'value' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'block text-label text-fg-2']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-danger" aria-hidden="true">*</span>
        <span class="sr-only">(จำเป็นต้องกรอก)</span>
    @endif
</label>
