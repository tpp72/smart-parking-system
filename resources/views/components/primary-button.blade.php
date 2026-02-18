<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => '
                inline-flex items-center justify-center
                px-5 py-2.5
                rounded-xl
                font-bold text-xs uppercase tracking-widest
                text-white
                bg-gradient-to-r from-red-600 to-red-700
                border border-red-900
                shadow-[0_0_14px_rgba(220,38,38,0.45)]
                hover:from-red-500 hover:to-red-600
                hover:shadow-[0_0_18px_rgba(220,38,38,0.7)]
                active:scale-95
                focus:outline-none
                focus:ring-2 focus:ring-red-500/70
                focus:ring-offset-0
                transition-all duration-200 ease-in-out
            ',
    ]) }}>
    {{ $slot }}
</button>
