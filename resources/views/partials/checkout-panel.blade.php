{{--
    ชำระค่าจอดก่อนออก (project-plan.md §12.6) — ใช้ร่วมกันระหว่างหน้าเช็คสถานะรถ (Walk-in) และหน้าหลักของผู้ใช้

    กด Check-out → ล็อกยอด ชำระภายใน N นาที → กดชำระ (จำลอง) → สแกนออกได้ภายใน N นาที
    เลยเวลาไม่ว่าขั้นไหน ค่าจอดนับต่อ และต้องกด Check-out ใหม่

    ต้องการ: $exit (CheckOutService::exitState) · $checkoutAction · $payAction
    ไม่บังคับ: $hidden (ค่าที่ต้องส่งซ้ำในฟอร์ม เช่น ทะเบียน + รหัสอ้างอิงของหน้าเช็คสถานะ) · $error (ข้อความจากการกดครั้งล่าสุด)
--}}
@use('App\Support\Format')
@use('App\Services\CheckOutService')

@php
    $hidden ??= [];
    $error ??= null;
    $state = $exit['state'];
    $charge = $exit['charge'];
    $window = CheckOutService::window();
    $deadline = $exit['deadline'];
    $hasDeductions = $charge['deposit_deduction'] > 0 || $charge['reservation_discount'] > 0 || ($charge['prior_paid'] ?? 0) > 0;
@endphp

<div class="flex flex-col gap-4">
    @if ($error)
        <x-ui.alert tone="danger">{{ $error }}</x-ui.alert>
    @endif

    <div @class([
        'rounded-card border p-4',
        'border-success/40 bg-success/10' => $state === CheckOutService::STATE_EXIT_OPEN,
        'border-primary-ink/40 bg-surface-2' => $state === CheckOutService::STATE_AWAITING_PAYMENT,
        'border-line bg-surface-2' => $state === CheckOutService::STATE_PARKED,
    ])>
        <p class="flex items-baseline justify-between gap-3">
            <span class="text-label text-fg-2">
                @switch($state)
                    @case(CheckOutService::STATE_EXIT_OPEN) ชำระแล้ว @break
                    @case(CheckOutService::STATE_AWAITING_PAYMENT) ยอดที่ต้องชำระ @break
                    @default ค่าจอด ณ ตอนนี้
                @endswitch
            </span>
            <span class="num text-h2 text-fg">{{ Format::baht($charge['total_amount']) }}</span>
        </p>

        @if ($hasDeductions)
            <p class="mt-2 text-caption text-fg-3">
                ค่าจอด <span class="tabular">{{ $charge['total_hours'] }}</span> ชม. <span class="tabular">{{ Format::baht($charge['parking_fee']) }}</span>
                @if ($charge['deposit_deduction'] > 0) · หักมัดจำ <span class="tabular">{{ Format::baht($charge['deposit_deduction']) }}</span> @endif
                @if ($charge['reservation_discount'] > 0) · ส่วนลด <span class="tabular">{{ Format::baht($charge['reservation_discount']) }}</span> @endif
                @if (($charge['prior_paid'] ?? 0) > 0) · หักที่ชำระแล้ว <span class="tabular">{{ Format::baht($charge['prior_paid']) }}</span> @endif
            </p>
        @endif

        @if ($deadline)
            {{-- นับถอยหลังเพื่อบอกคนขับเท่านั้น — เซิร์ฟเวอร์ตรวจเวลาเองทุกครั้งที่กดและที่กล้องขาออก --}}
            <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-label"
                x-data="{
                    left: 0,
                    tick() { this.left = Math.max(0, Math.floor(({{ $deadline->getTimestampMs() }} - Date.now()) / 1000)); },
                }"
                x-init="tick(); setInterval(() => tick(), 1000)">
                <span class="font-semibold text-fg">
                    {{ $state === CheckOutService::STATE_EXIT_OPEN ? 'สแกนออกได้ภายใน' : 'ชำระภายใน' }}
                    {{ Format::time($deadline) }} น.
                </span>
                <span class="num text-fg-2" x-show="left > 0" x-cloak>
                    (เหลือ <span x-text="String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0')"></span>)
                </span>
                <span class="text-warning" x-show="left === 0" x-cloak>หมดเวลาแล้ว — ค่าจอดนับต่อ</span>
            </p>
        @endif

        <p class="mt-2 text-caption text-fg-3">
            @switch($state)
                @case(CheckOutService::STATE_EXIT_OPEN)
                    ขับไปที่ประตูทางออกได้เลย กล้องจะปล่อยรถให้ · ถ้าไม่ออกภายใน {{ $window }} นาที ค่าจอดนับต่อ และต้องชำระส่วนที่เกิน
                    @break
                @case(CheckOutService::STATE_AWAITING_PAYMENT)
                    ยอดถูกล็อกไว้ {{ $window }} นาที · ถ้าไม่ชำระภายในเวลา ค่าจอดนับต่อ และต้องกด Check-out ใหม่
                    @break
                @default
                    คิดรายชั่วโมง ปัดขึ้น ขั้นต่ำ 1 ชั่วโมง · <strong class="font-semibold text-fg-2">ต้องกด Check-out และชำระก่อน</strong> กล้องที่ประตูทางออกจึงจะปล่อยรถ
            @endswitch
        </p>
    </div>

    @if ($state === CheckOutService::STATE_PARKED)
        <form method="POST" action="{{ $checkoutAction }}">
            @csrf
            @foreach ($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <x-ui.button type="submit" class="w-full">Check-out</x-ui.button>
        </form>
    @elseif ($state === CheckOutService::STATE_AWAITING_PAYMENT)
        <form method="POST" action="{{ $payAction }}">
            @csrf
            @foreach ($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <x-ui.button type="submit" class="w-full">ชำระเงิน {{ Format::baht($charge['total_amount']) }}</x-ui.button>
        </form>
        <p class="-mt-2 text-center text-caption text-fg-3">การชำระเงินเป็นการจำลอง ไม่มีการตัดเงินจริง</p>
    @endif
</div>
