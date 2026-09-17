{{--
    Drawer (แผงเลื่อนจากขอบจอ) — เปิด/ปิดด้วย $dispatch('open-drawer', 'ชื่อ') / $dispatch('close-drawer', 'ชื่อ')
    ใช้เป็นเมนูมือถือหรือรายละเอียดเสริม · focus trap · Escape · คืนโฟกัส
--}}
@props(['name', 'title' => null, 'side' => 'right'])

@php
    $side = $side === 'left' ? 'left' : 'right';
    $baseId = 'drawer-'.\Illuminate\Support\Str::slug($name);
    $position = $side === 'left' ? 'left-0 border-r' : 'right-0 border-l';
    $from = $side === 'left' ? '-translate-x-full' : 'translate-x-full';
@endphp

<div x-data="spDrawer({ name: @js($name) })"
    x-show="show"
    x-on:keydown.window="onKeydown($event)"
    x-on:close.stop="close()"
    class="fixed inset-0 z-drawer"
    style="display: none;">
    <div x-show="show" x-transition.opacity.duration.150ms class="fixed inset-0 bg-scrim/50" aria-hidden="true" x-on:click="close()"></div>

    <div x-ref="panel" role="dialog" aria-modal="true" tabindex="-1"
        @if ($title) aria-labelledby="{{ $baseId }}-title" @endif
        x-show="show"
        x-transition:enter="transition duration-base ease-out"
        x-transition:enter-start="{{ $from }}"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-fast ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="{{ $from }}"
        {{ $attributes->class(['fixed inset-y-0 flex w-full max-w-sm flex-col border-line bg-surface text-fg shadow-overlay focus:outline-none', $position]) }}>
        <div class="flex min-h-14 items-center justify-between gap-4 border-b border-line px-4">
            @if ($title)
                <h2 id="{{ $baseId }}-title" class="text-h3 text-fg">{{ $title }}</h2>
            @endif
            <x-ui.button variant="ghost" icon-only label="ปิดแผง" class="-mr-2 ml-auto text-fg-3" x-on:click="close()">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9" /></svg>
            </x-ui.button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto">
            {{ $slot }}
        </div>
    </div>
</div>
