@props(['message' => 'ไม่มีข้อมูล', 'sub' => null])

<div class="sp-empty">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
    </svg>
    <div>
        <p class="text-base font-semibold text-gray-400">{{ $message }}</p>
        @if($sub)
            <p class="text-sm text-gray-600 mt-1">{{ $sub }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div>{{ $slot }}</div>
    @endif
</div>
