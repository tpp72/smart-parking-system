{{--
    รายการชำระเงิน (มัดจำ + ค่าจอด) ใช้ร่วมกันระหว่าง Admin และ Owner — ต้องส่ง $payments, $status และ $markPaidRoute
    แต่ละแถวเป็นใบเสร็จย่อ: ประเภท · รถ · ลาน · รายการคำนวณ · ยอด · สถานะ · ปุ่มรับชำระ (ยืนยันผ่านกล่องยืนยันของระบบ)
--}}
@use('App\Support\Format')
@use('App\Models\Payment')

@if ($payments->isEmpty())
    <div class="rounded-card border border-line bg-surface shadow-1">
        <x-ui.empty-state
            :title="$status === 'unpaid' ? 'ไม่มีรายการรอยืนยันรับเงิน' : 'ไม่มีรายการในกลุ่มนี้'"
            :description="$status === 'unpaid' ? 'มัดจำและค่าจอดทุกรายการได้รับการยืนยันแล้ว' : null" />
    </div>
@else
    <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
        @foreach ($payments as $payment)
            @php
                $isDeposit = $payment->type === Payment::TYPE_DEPOSIT;
                $plate = $payment->parkingLog?->license_plate ?? $payment->reservation?->license_plate;
                $province = $payment->reservation?->plate_province;
                $lotName = $payment->parkingLog?->parkingLot?->name ?? $payment->reservation?->parkingLot?->name;
                $customer = $payment->reservation?->user?->name;
                $amount = Format::baht($payment->total_amount);
            @endphp
            <li data-payment-type="{{ $payment->type }}" class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.1fr)_auto] lg:items-center lg:gap-5">
                <x-ui.plate :plate="$plate ?? '—'" :province="$province" size="sm" class="justify-self-start" />

                <div class="min-w-0">
                    <p class="font-semibold text-fg">
                        {{ $isDeposit ? 'มัดจำ' : 'ค่าจอด' }}
                        <span class="tabular font-normal text-fg-3">#{{ $payment->id }} · การจอง #{{ $payment->reservation_id }}</span>
                    </p>
                    <p class="truncate text-label text-fg-2">{{ $lotName ?? '—' }} · {{ $customer ?? '—' }}</p>
                    <p class="text-caption text-fg-3">สร้าง {{ Format::short($payment->created_at) }}@if ($payment->paid_at) · รับเงิน {{ Format::short($payment->paid_at) }}@endif</p>
                </div>

                {{-- รายการคำนวณ --}}
                <dl class="flex flex-col gap-0.5 text-label">
                    @if ($isDeposit)
                        <div class="flex justify-between gap-3 lg:justify-start">
                            <dt class="text-fg-2">ค่าจอด 1 ชม. ของลาน</dt>
                            <dd class="tabular text-fg">{{ Format::baht($payment->hourly_rate) }}</dd>
                        </div>
                    @else
                        <div class="flex justify-between gap-3 lg:justify-start">
                            <dt class="text-fg-2"><span class="tabular">{{ (int) $payment->total_hours }}</span> ชม. × <span class="tabular">{{ Format::baht($payment->hourly_rate) }}</span></dt>
                            <dd class="tabular text-fg">{{ Format::baht($payment->parking_fee) }}</dd>
                        </div>
                        @if ((float) $payment->deposit_deduction > 0)
                            <div class="flex justify-between gap-3 lg:justify-start"><dt class="text-fg-2">หักมัดจำ</dt><dd class="tabular text-fg">−{{ Format::baht($payment->deposit_deduction) }}</dd></div>
                        @endif
                        @if ((float) $payment->reservation_discount > 0)
                            <div class="flex justify-between gap-3 lg:justify-start"><dt class="text-fg-2">ส่วนลดการจอง</dt><dd class="tabular text-fg">−{{ Format::baht($payment->reservation_discount) }}</dd></div>
                        @endif
                    @endif
                </dl>

                <div class="flex flex-wrap items-center gap-3 lg:flex-col lg:items-end lg:gap-1.5">
                    <p class="num text-h3 text-fg">{{ $amount }}</p>
                    <x-ui.status type="payment" :value="$payment->payment_status" audience="staff" />
                    @if ($payment->payment_status === Payment::STATUS_UNPAID)
                        <form method="POST" action="{{ route($markPaidRoute, $payment) }}"
                            data-confirm="{{ $isDeposit
                                ? "ยืนยันว่าได้รับเงินมัดจำ {$amount} ของทะเบียน {$plate} แล้ว — การจองจะยืนยันและระบบจัดช่องจอดให้ (ถ้าลานเต็ม การจองจะถูกยกเลิก)"
                                : "ยืนยันว่าได้รับค่าจอด {$amount} จากทะเบียน {$plate} แล้ว" }}"
                            data-confirm-title="{{ $isDeposit ? 'ยืนยันรับเงินมัดจำ?' : 'ยืนยันรับเงินค่าจอด?' }}"
                            data-confirm-label="รับชำระแล้ว" data-confirm-cancel="ยังไม่ได้รับเงิน">
                            @csrf
                            <x-ui.button type="submit" size="sm">รับชำระแล้ว</x-ui.button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <x-ui.pagination :paginator="$payments" class="mt-6" />
@endif
