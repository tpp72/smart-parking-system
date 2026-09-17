{{-- แถบล่างบนมือถือ/แท็บเล็ต (< lg) — 5 ช่อง ช่องสุดท้ายเปิด Drawer (เมนู / บัญชี) --}}
<nav aria-label="เมนูด่วน"
    class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden">
    <ul class="mx-auto grid max-w-lg grid-cols-5">
        @foreach ($nav['bottom'] as $item)
            @php
                $classes = \Illuminate\Support\Arr::toCssClasses([
                    'relative flex min-h-[3.75rem] w-full flex-col items-center justify-center gap-1 px-1 text-caption transition-colors duration-fast',
                    'font-semibold text-primary-ink' => $item['active'],
                    'text-fg-3 hover:text-fg' => ! $item['active'],
                ]);
            @endphp
            <li>
                @if ($item['drawer'])
                    <button type="button" class="{{ $classes }}" aria-haspopup="dialog"
                        x-data x-on:click="$dispatch('open-drawer', @js($item['drawer']))">
                @else
                    <a href="{{ $item['href'] }}" class="{{ $classes }}" @if ($item['active']) aria-current="page" @endif>
                @endif
                    <span @class([
                        'relative inline-flex h-7 w-12 items-center justify-center rounded-card',
                        'bg-primary/10' => $item['active'],
                    ])>
                        <x-ui.icon :name="$item['icon']" />
                        <x-ui.count-badge :count="$item['badge']" :label="$item['badgeLabel']" class="absolute -right-0.5 -top-1" />
                    </span>
                    <span class="max-w-full truncate leading-none">{{ $item['label'] }}</span>
                @if ($item['drawer'])
                    </button>
                @else
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
