{{--
    Modal — เปิด/ปิดด้วย event: $dispatch('open-modal', 'ชื่อ') / $dispatch('close-modal', 'ชื่อ')
    ภายใน modal: $dispatch('close') เพื่อปิด · focus trap · Escape · คืนโฟกัสไปยังปุ่มที่เปิด
    งานใหม่ที่ต้องการแค่ "ยืนยัน/ยกเลิก" ให้ใช้ window.spConfirm() หรือ <form data-confirm> แทน
--}}
@props(['name', 'show' => false, 'maxWidth' => 'lg', 'title' => null, 'description' => null])

@php
    $maxWidthClass = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'][$maxWidth] ?? 'sm:max-w-lg';
    $baseId = 'modal-'.\Illuminate\Support\Str::slug($name);
@endphp

<div x-data="spModal({ name: @js($name), show: @js((bool) $show) })"
    x-show="show"
    x-on:keydown.window="onKeydown($event)"
    x-on:close.stop="close()"
    class="fixed inset-0 z-modal flex items-end justify-center overflow-y-auto p-4 sm:items-center"
    style="display: {{ $show ? 'flex' : 'none' }};">
    <div x-show="show" x-transition.opacity.duration.150ms class="fixed inset-0 bg-scrim/50" aria-hidden="true" x-on:click="close()"></div>

    <div x-ref="panel" role="dialog" aria-modal="true" tabindex="-1"
        @if ($title) aria-labelledby="{{ $baseId }}-title" @endif
        @if ($description) aria-describedby="{{ $baseId }}-description" @endif
        x-show="show"
        x-transition:enter="transition duration-base ease-out"
        x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-fast ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        {{ $attributes->except('focusable')->class(['relative w-full rounded-card border border-line bg-surface text-fg shadow-overlay focus:outline-none', $maxWidthClass]) }}>
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                <div class="min-w-0">
                    <h2 id="{{ $baseId }}-title" class="text-h3 text-fg">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $baseId }}-description" class="mt-1 text-fg-2">{{ $description }}</p>
                    @endif
                </div>
                <x-ui.button variant="ghost" icon-only label="ปิดหน้าต่าง" class="-my-2 -mr-2 text-fg-3" x-on:click="close()">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9" /></svg>
                </x-ui.button>
            </div>
        @endif

        {{ $slot }}

        @isset($footer)
            <div class="flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row sm:justify-end">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
