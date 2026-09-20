{{--
    กล่องยืนยัน Manual Check-out (ใช้ร่วมหน้า การจอง / ประวัติการจอด)
    ปุ่มเปิด: x-on:click="checkout = {...}; $dispatch('open-modal', 'checkout-confirm')" ภายใต้ x-data ที่มี checkout
    ยอดเป็นค่าประมาณ ณ เวลาที่เปิดหน้า ด้วยสูตรเดียวกับ Check-out จริง (CheckOutService::calculate)
--}}
<x-ui.modal name="checkout-confirm" maxWidth="md" title="ยืนยัน Check-out"
    description="ใช้เมื่อกล้องสแกนขาออกไม่ได้ ยอดจริงคำนวณ ณ เวลาที่กดยืนยัน">
    <div class="px-5 py-4" x-show="checkout" x-cloak>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex min-w-[8.5rem] flex-col items-center rounded-control border-2 border-fg px-3 py-1 text-center">
                <span class="text-h3 font-bold leading-tight text-fg" x-text="checkout?.plate"></span>
                <span class="text-mini leading-tight text-fg-2" x-text="checkout?.province"></span>
            </span>
            <div class="text-label">
                <p class="font-semibold text-fg" x-text="checkout?.place"></p>
                <p class="text-fg-2" x-text="checkout?.since"></p>
            </div>
        </div>

        <dl class="mt-4 flex flex-col gap-1.5 rounded-control border border-line bg-surface-2 px-4 py-3 text-label">
            <div class="flex justify-between gap-3">
                <dt class="text-fg-2" x-text="'ค่าจอด ' + checkout?.hours + ' ชม. × ' + checkout?.rate"></dt>
                <dd class="num text-fg" x-text="checkout?.fee"></dd>
            </div>
            <div class="flex justify-between gap-3" x-show="checkout?.deposit">
                <dt class="text-fg-2">หักมัดจำที่ชำระแล้ว</dt>
                <dd class="num text-fg" x-text="'−' + checkout?.deposit"></dd>
            </div>
            <div class="flex justify-between gap-3" x-show="checkout?.discount">
                <dt class="text-fg-2">ส่วนลดการจอง</dt>
                <dd class="num text-fg" x-text="'−' + checkout?.discount"></dd>
            </div>
            <div class="mt-1 flex justify-between gap-3 border-t border-line pt-2 font-semibold">
                <dt class="text-fg">ยอดที่ต้องเก็บ (ประมาณ)</dt>
                <dd class="num text-h3 text-fg" x-text="checkout?.total"></dd>
            </div>
        </dl>
        <p class="mt-2 text-caption text-fg-3">ถ้ายอดสุทธิเป็น 0 ระบบบันทึกว่าชำระแล้วให้เอง · ยอดที่มากกว่า 0 ต้องกด "รับชำระแล้ว" ในหน้าชำระเงิน</p>
    </div>

    <x-slot name="footer">
        <x-ui.button variant="secondary" x-on:click="$dispatch('close')">ยังไม่ Check-out</x-ui.button>
        <form method="POST" x-bind:action="checkout?.url">
            @csrf
            <x-ui.button type="submit">ยืนยัน Check-out</x-ui.button>
        </form>
    </x-slot>
</x-ui.modal>
