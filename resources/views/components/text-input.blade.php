@props(['disabled' => false])

<input @disabled($disabled)
    {{ $attributes->merge([
        'class' => '
                    w-full
                    rounded-xl
                    border border-red-900
                    bg-black/60
                    text-gray-200
                    placeholder-gray-500
                    shadow-sm
                    focus:border-red-500
                    focus:ring-2 focus:ring-red-500/60
                    focus:shadow-[0_0_12px_rgba(220,38,38,0.6)]
                    focus:bg-black/70
                    transition duration-200 ease-in-out
                    disabled:opacity-50 disabled:cursor-not-allowed
                ',
    ]) }}>
