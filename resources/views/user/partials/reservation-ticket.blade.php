{{--
    บัตรจอดรถ 1 ใบ = การจอง 1 รายการที่ยังไม่จบ (รอยืนยันรับเงิน / ยืนยันแล้ว / กำลังจอด)
    ต้นขั้วซ้าย: ป้ายทะเบียน · ตัวบัตร: ลาน ช่อง เวลา มัดจำ · แถบช่วงเวลาเช็คอิน · สิ่งที่ต้องทำต่อ
    ต้องการ: $reservation (with parkingLot, parkingSlot, depositPayment, parkingLog) · $estimate (array|null)
--}}
@use('App\Support\Format')

@php
    $r = $reservation;
    $estimate ??= null;
    $status = $r->status;
    $graceEnd = $r->reserve_start->copy()->addMinutes(\App\Models\Reservation::gracePeriodMinutes());
    $deposit = (float) $r->deposit_amount;
    $depositState = $r->depositPayment?->payment_status;
    $log = $r->parkingLog;
    $canChange = in_array($status, ['pending', 'confirmed'], true);
    $titleId = 'ticket-'.$r->id;

    $cancelMessage = $depositState === 'paid'
        ? 'เจ้าหน้าที่ยืนยันรับมัดจำ '.Format::baht($deposit).' แล้ว ยกเลิกตอนนี้มัดจำจะไม่ได้รับคืน และช่องจอดจะถูกปล่อยให้คนอื่นจอง'
        : 'มัดจำยังไม่ได้รับการยืนยัน รายการมัดจำจะถูกยกเลิกไปพร้อมการจอง';
@endphp

<article aria-labelledby="{{ $titleId }}" class="overflow-hidden rounded-card border border-line bg-surface shadow-1">
    <div class="flex flex-col sm:flex-row">
        {{-- ต้นขั้ว: รถคันไหน --}}
        <div class="flex items-center gap-4 border-b border-dashed border-field p-4 sm:w-52 sm:shrink-0 sm:flex-col sm:items-start sm:justify-center sm:border-b-0 sm:border-r sm:p-5">
            <x-ui.plate :plate="$r->license_plate" :province="$r->plate_province" size="md" />
            <div class="min-w-0 text-label text-fg-2">
                <p class="truncate">{{ $r->brand }} · {{ $r->color }}</p>
                <p class="tabular text-caption text-fg-3">การจอง #{{ $r->id }}</p>
            </div>
        </div>

        {{-- ตัวบัตร --}}
        <div class="min-w-0 flex-1 p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 id="{{ $titleId }}" class="text-h3 text-fg">{{ $r->parkingLot?->name ?? 'ลานจอด' }}</h3>
                    <div class="mt-1"><x-ui.status type="reservation" :value="$status" audience="user" /></div>
                </div>

                {{-- ตรามัดจำ --}}
                @if ($deposit > 0)
                    @if ($depositState === 'paid')
                        <span class="-rotate-3 rounded-control border-2 border-primary-ink px-2 py-1 text-center text-caption font-bold leading-tight text-primary-ink">
                            รับมัดจำแล้ว<br><span class="num">{{ Format::baht($deposit) }}</span>
                        </span>
                    @else
                        <span class="rounded-control border border-dashed border-field px-2 py-1 text-center text-caption leading-tight text-fg-2">
                            มัดจำ <span class="num font-semibold text-fg">{{ Format::baht($deposit) }}</span><br>รอยืนยันรับเงิน
                        </span>
                    @endif
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-label sm:grid-cols-3">
                <div>
                    <dt class="text-caption text-fg-3">ช่องจอด</dt>
                    <dd class="mt-0.5 font-semibold text-fg">
                        @if ($r->parkingSlot)
                            <span class="num">{{ $r->parkingSlot->slot_number }}</span>
                        @else
                            <span class="font-normal text-fg-2">ระบบจัดให้หลังยืนยันรับมัดจำ</span>
                        @endif
                    </dd>
                </div>
                @if ($status === 'checked_in' && $log)
                    <div>
                        <dt class="text-caption text-fg-3">เข้าลาน</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($log->check_in_time) }}</dd>
                    </div>
                    <div>
                        <dt class="text-caption text-fg-3">จอดมาแล้ว</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::duration((int) $log->check_in_time->diffInMinutes(now())) }}</dd>
                    </div>
                @else
                    <div>
                        <dt class="text-caption text-fg-3">เวลาเริ่มจอง</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($r->reserve_start) }}</dd>
                    </div>
                    <div>
                        <dt class="text-caption text-fg-3">ค่าจอด</dt>
                        <dd class="mt-0.5 font-semibold text-fg"><span class="num">{{ Format::baht($r->parkingLot?->hourly_rate) }}</span> <span class="font-normal text-fg-2">/ ชม.</span></dd>
                    </div>
                @endif
            </dl>

            @if ($canChange)
                <x-ui.checkin-rail :start="$r->reserve_start" class="mt-5" />
            @elseif ($estimate)
                {{-- ใบเสร็จย่อ: ค่าจอดถึงตอนนี้ (ประมาณ) --}}
                <dl class="mt-5 rounded-control border border-line bg-surface-2 px-4 py-3 text-label">
                    <div class="flex justify-between gap-3">
                        <dt class="text-fg-2">ค่าจอด <span class="num">{{ $estimate['total_hours'] }}</span> ชม. × <span class="num">{{ Format::baht($estimate['hourly_rate']) }}</span></dt>
                        <dd class="num text-fg">{{ Format::baht($estimate['parking_fee']) }}</dd>
                    </div>
                    @if ($estimate['deposit_deduction'] > 0)
                        <div class="mt-1 flex justify-between gap-3">
                            <dt class="text-fg-2">หักมัดจำที่ชำระแล้ว</dt>
                            <dd class="num text-fg">−{{ Format::baht($estimate['deposit_deduction']) }}</dd>
                        </div>
                    @endif
                    @if ($estimate['reservation_discount'] > 0)
                        <div class="mt-1 flex justify-between gap-3">
                            <dt class="text-fg-2">ส่วนลดการจอง</dt>
                            <dd class="num text-fg">−{{ Format::baht($estimate['reservation_discount']) }}</dd>
                        </div>
                    @endif
                    <div class="mt-2 flex justify-between gap-3 border-t border-line pt-2 font-semibold">
                        <dt class="text-fg">ยอดที่ต้องชำระถ้าออกตอนนี้</dt>
                        <dd class="num text-fg">{{ Format::baht($estimate['total_amount']) }}</dd>
                    </div>
                </dl>
            @endif
        </div>
    </div>

    {{-- สิ่งที่ต้องทำต่อ --}}
    <div class="flex flex-col gap-3 border-t border-line bg-surface-2/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <p class="text-label text-fg-2">
            @switch ($status)
                @case('pending')
                    รอเจ้าหน้าที่ยืนยันรับเงินมัดจำ ต้องยืนยันและนำรถเข้าลานภายใน <span class="font-semibold text-fg">{{ Format::short($graceEnd) }}</span> ไม่เช่นนั้นการจองจะหมดอายุ
                    @break
                @case('confirmed')
                    นำรถเข้าลานได้ <span class="font-semibold text-fg">{{ Format::time($r->reserve_start) }}–{{ Format::time($graceEnd) }}</span>
                    ระบบ Check-in ให้เมื่อสแกนแล้วป้ายทะเบียน จังหวัด และยี่ห้อหรือสีตรงกับการจอง
                    @break
                @default
                    ออกจากลานโดยสแกนป้ายทะเบียนขาออก ระบบคิดค่าจอดจริงตอน Check-out แล้วเจ้าหน้าที่ยืนยันรับเงิน
            @endswitch
        </p>

        <div class="flex shrink-0 flex-wrap gap-2">
            @if ($canChange)
                <x-ui.button variant="secondary" size="sm" :href="route('user.reservations.edit', $r)">แก้ไขข้อมูลรถ</x-ui.button>
                <form method="POST" action="{{ route('user.reservations.cancel', $r) }}"
                    data-confirm="{{ $cancelMessage }}"
                    data-confirm-title="ยกเลิกการจอง #{{ $r->id }}?"
                    data-confirm-label="ยกเลิกการจอง"
                    data-confirm-cancel="เก็บการจองไว้"
                    data-confirm-tone="danger">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger">ยกเลิกการจอง</x-ui.button>
                </form>
            @else
                <x-ui.button variant="secondary" size="sm" :href="route('user.scan.create')">
                    <x-ui.icon name="scan" class="h-4 w-4" /> AI สแกนขาออก
                </x-ui.button>
            @endif
        </div>
    </div>
</article>
