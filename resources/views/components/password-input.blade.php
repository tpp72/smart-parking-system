@props(['id', 'name', 'autocomplete' => 'current-password', 'required' => false])

<div x-data="{ show: false }" class="relative mt-1">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        :type="show ? 'text' : 'password'"
        @if($required) required @endif
        autocomplete="{{ $autocomplete }}"
        {{ $attributes->merge(['class' => '
            w-full pr-10
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
        ']) }}
    />
    <button
        type="button"
        @click="show = !show"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none"
        tabindex="-1"
        :aria-label="show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'"
    >
        {{-- Eye (แสดงตอน show = false) --}}
        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        {{-- Eye-slash (แสดงตอน show = true) --}}
        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
        </svg>
    </button>
</div>
