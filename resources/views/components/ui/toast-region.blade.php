{{--
    ที่แสดง Toast ทั้งระบบ (render ครั้งเดียวใน layout)
    :flash="true" → แปลง flash message ของ Laravel (success / error / warning / info) เป็น toast
    เรียกจาก JS: window.spToast({ tone: 'success', message: '...' })
--}}
@props(['flash' => false])

@php
    $flashToasts = [];
    if ($flash) {
        foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $tone) {
            if (is_string($message = session($key)) && filled($message)) {
                $flashToasts[] = ['tone' => $tone, 'message' => $message];
            }
        }
    }
@endphp

<section aria-label="การแจ้งเตือน"
    x-data
    x-init="{{ \Illuminate\Support\Js::from($flashToasts) }}.forEach((toast) => $store.toasts.push(toast))"
    class="pointer-events-none fixed inset-x-0 bottom-0 z-toast flex flex-col items-stretch gap-2 p-4 pb-[calc(1rem+var(--sp-bottom-offset,0px)+env(safe-area-inset-bottom))] sm:left-auto sm:w-[26rem]">
    <div aria-live="polite" aria-relevant="additions text" class="flex flex-col gap-2">
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-bind:role="toast.tone === 'error' || toast.tone === 'warning' ? 'alert' : 'status'"
                x-transition:enter="transition duration-base ease-out"
                x-transition:enter-start="translate-y-2 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition duration-fast ease-in"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:mouseenter="$store.toasts.pause(toast.id)"
                x-on:mouseleave="$store.toasts.resume(toast.id)"
                x-on:focusin="$store.toasts.pause(toast.id)"
                x-on:focusout="$store.toasts.resume(toast.id)"
                x-on:keydown.escape="$store.toasts.dismiss(toast.id)"
                class="pointer-events-auto flex items-start gap-3 rounded-card border bg-surface p-4 text-body shadow-overlay"
                x-bind:class="{
                    'border-success/60': toast.tone === 'success',
                    'border-primary-ink/50': toast.tone === 'info',
                    'border-warning/60': toast.tone === 'warning',
                    'border-danger/60': toast.tone === 'error',
                }">
                {{-- ไอคอนตามประเภท: <template x-if> ใช้ใน <svg> ไม่ได้ จึงแยก svg ละชนิด --}}
                <svg x-show="toast.tone === 'success'" class="mt-0.5 h-5 w-5 shrink-0 text-success" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25" /><path d="M6.75 10.25l2.25 2.25 4.25-4.5" /></svg>
                <svg x-show="toast.tone === 'info'" class="mt-0.5 h-5 w-5 shrink-0 text-primary-ink" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25" /><path d="M10 9v4.5M10 6.5v.01" /></svg>
                <svg x-show="toast.tone === 'warning'" class="mt-0.5 h-5 w-5 shrink-0 text-warning" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3.25l7.25 13H2.75z" /><path d="M10 8.25v3.5M10 14v.01" /></svg>
                <svg x-show="toast.tone === 'error'" class="mt-0.5 h-5 w-5 shrink-0 text-danger" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="7.25" /><path d="M7.5 7.5l5 5M12.5 7.5l-5 5" /></svg>

                <div class="min-w-0 flex-1">
                    <p x-show="toast.title" x-text="toast.title" class="font-semibold text-fg"></p>
                    <p x-text="toast.message" class="text-fg-2"></p>
                </div>

                <button type="button" aria-label="ปิดการแจ้งเตือน"
                    x-on:click="$store.toasts.dismiss(toast.id)"
                    class="-my-2.5 -mr-2 inline-flex h-touch w-touch shrink-0 items-center justify-center rounded-card text-fg-3 transition-colors duration-fast hover:bg-surface-2 hover:text-fg">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9" /></svg>
                </button>
            </div>
        </template>
    </div>
</section>
