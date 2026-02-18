<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => '
                inline-flex items-center justify-center
                px-5 py-2.5
                rounded-xl
                font-bold text-xs uppercase tracking-widest
                text-gray-200
                bg-black/60
                border border-red-900
                shadow-sm
                hover:bg-red-900/30
                hover:text-red-300
                hover:shadow-[0_0_10px_rgba(220,38,38,0.35)]
                focus:outline-none
                focus:ring-2 focus:ring-red-500/60
                focus:ring-offset-0
                active:bg-red-900/50
                disabled:opacity-40 disabled:cursor-not-allowed
                transition-all duration-200 ease-in-out
            ',
    ]) }}>
    {{ $slot }}
</button>
