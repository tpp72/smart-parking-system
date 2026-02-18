@props(['messages'])

@if ($messages)
    <div
        {{ $attributes->merge([
            'class' =>
                'mt-2 rounded-xl border border-red-900 bg-red-900/20 px-4 py-3 backdrop-blur shadow-[0_0_10px_rgba(220,38,38,0.25)]',
        ]) }}>
        <ul class="text-sm font-semibold text-red-300 space-y-1">
            @foreach ((array) $messages as $message)
                <li>• {{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
