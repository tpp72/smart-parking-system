<a
    {{ $attributes->merge([
        'class' => '
                    block w-full px-4 py-2
                    text-start text-sm font-semibold
                    text-gray-200
                    rounded-xl
                    hover:text-red-300
                    hover:bg-red-900/30
                    focus:outline-none
                    focus:bg-red-900/40
                    focus:text-red-200
                    transition-all duration-150 ease-in-out
                    hover:bg-red-900/30
                    hover:shadow-[0_0_10px_rgba(220,38,38,0.4)]
                ',
    ]) }}>
    {{ $slot }}
</a>
