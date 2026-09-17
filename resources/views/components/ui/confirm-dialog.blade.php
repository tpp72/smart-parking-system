{{-- กล่องยืนยันกลางของระบบ (render ครั้งเดียวใน layout) — ควบคุมด้วย window.spConfirm() และ <form data-confirm> --}}
<div x-data="spConfirmDialog"
    x-show="store.open"
    x-on:keydown.window="onKeydown($event)"
    class="fixed inset-0 z-modal flex items-end justify-center p-4 sm:items-center"
    style="display: none;">
    <div x-show="store.open" x-transition.opacity.duration.150ms class="fixed inset-0 bg-scrim/50" aria-hidden="true" x-on:click="store.settle(false)"></div>

    <div x-ref="panel" role="alertdialog" aria-modal="true" aria-labelledby="sp-confirm-title" aria-describedby="sp-confirm-message"
        x-show="store.open"
        x-transition:enter="transition duration-base ease-out"
        x-transition:enter-start="translate-y-2 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        class="relative w-full rounded-card border border-line bg-surface p-5 text-fg shadow-overlay sm:max-w-md sm:p-6">
        <h2 id="sp-confirm-title" class="text-h3 text-fg" x-text="store.title"></h2>
        <p id="sp-confirm-message" class="mt-2 text-fg-2" x-text="store.message"></p>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" x-ref="cancel" x-on:click="store.settle(false)">
                <span x-text="store.cancelLabel">ยกเลิก</span>
            </x-ui.button>
            <x-ui.button variant="danger" x-show="store.tone === 'danger'" x-on:click="store.settle(true)">
                <span x-text="store.confirmLabel">ยืนยัน</span>
            </x-ui.button>
            <x-ui.button variant="primary" x-show="store.tone !== 'danger'" x-on:click="store.settle(true)">
                <span x-text="store.confirmLabel">ยืนยัน</span>
            </x-ui.button>
        </div>
    </div>
</div>
