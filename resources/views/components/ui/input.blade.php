@aware(['for' => null, 'hint' => null])

@props(['name' => null, 'id' => null, 'type' => 'text', 'invalid' => null, 'numeric' => false])

@php
    $id ??= $name ?? $for;
    $invalid ??= $name && ($errors ?? new \Illuminate\Support\ViewErrorBag)->has($name);
    $describedBy = collect([
        $hint && $id ? $id.'-hint' : null,
        $invalid && $id ? $id.'-error' : null,
        $attributes->get('aria-describedby'),
    ])->filter()->implode(' ');
@endphp

<input type="{{ $type }}"
    @if ($name) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->except('aria-describedby')->class([
        'block w-full min-h-touch rounded-control border border-field bg-surface px-3 text-body text-fg shadow-none',
        'placeholder:text-fg-3 hover:border-fg-2',
        'focus:border-primary-ink focus:outline-none focus:ring-1 focus:ring-primary-ink focus-visible:outline-none',
        'disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-fg-3 read-only:bg-surface-2',
        'aria-[invalid=true]:border-danger aria-[invalid=true]:focus:ring-danger',
        'num' => $numeric,
    ]) }}>
