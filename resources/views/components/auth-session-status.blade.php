@props(['status'])

@if ($status)
    <div
        {{ $attributes->merge([
            'class' =>
                'mb-4 rounded-xl border border-red-900 bg-red-900/20 px-4 py-3 text-sm font-semibold text-red-300 backdrop-blur',
        ]) }}>
        {{ $status }}
    </div>
@endif
