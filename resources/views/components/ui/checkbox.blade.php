@props(['name' => null, 'id' => null, 'value' => '1', 'checked' => false, 'label' => null, 'description' => null])

@php
    $id ??= $name ? $name.'-'.$value : 'cb-'.\Illuminate\Support\Str::random(6);
@endphp

<label for="{{ $id }}" class="group inline-flex min-h-touch cursor-pointer items-start gap-3 py-2.5 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
    <input type="checkbox" id="{{ $id }}" value="{{ $value }}"
        @if ($name) name="{{ $name }}" @endif
        @checked($checked)
        @if ($description) aria-describedby="{{ $id }}-description" @endif
        {{ $attributes->class([
            'mt-0.5 h-5 w-5 shrink-0 rounded-control border-field bg-surface text-primary shadow-none',
            'focus:ring-2 focus:ring-primary-ink focus:ring-offset-2 focus:ring-offset-surface focus-visible:outline-none',
            'group-hover:border-fg-2 disabled:cursor-not-allowed',
        ]) }}>
    <span class="min-w-0">
        <span class="block text-body text-fg">{{ $label ?? $slot }}</span>
        @if ($description)
            <span id="{{ $id }}-description" class="block text-caption text-fg-3">{{ $description }}</span>
        @endif
    </span>
</label>
