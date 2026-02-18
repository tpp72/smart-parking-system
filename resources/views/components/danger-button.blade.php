<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => '
                inline-flex items-center justify-center
                px-5 py-2.5
                rounded-xl
                font-bold text-xs uppercase tracking-widest
                text-white
                bg-gradient-to-r from-red-700 to-red-900
                border border-red-950
                shadow-[0_0_18px_rgba(220,38,38,0.6)]
                hover:from-red-600 hover:to-red-800
                hover:shadow-[0_0_24px_rgba(220,38,38,0.85)]
                active:scale-95
                focus:outline-none
                focus:ring-2 focus:ring-red-600/80
                focus:ring-offset-0
                transition-all duration-200 ease-in-out
            ',
    ]) }}>
    {{ $slot }}
</button>
