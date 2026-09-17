{{--
    การจอง (ใช้ร่วม Owner: ลานของตัวเอง · Admin: ลานของผู้ดูแลระบบ) — ค้นหา/กรอง · Manual Check-in (confirmed ที่ยังไม่เลยเวลา)
    · Manual Check-out พร้อมใบเสร็จประมาณ · Admin ยกเลิกการจองที่ยังไม่ Check-in ได้ และส่งออก CSV ตามตัวกรอง (ทุกลาน)
    การยืนยันการจองเกิดจาก "รับชำระแล้ว" ของเงินมัดจำในหน้าชำระเงินเท่านั้น
    ต้องการ: $reservations, $lots, $q, $status, $lotId, $from, $to, $statuses, $checkableIds, $estimates, $scope ('owner' | 'admin')
--}}
@use('App\Models\Reservation')
@use('App\Support\Format')
@use('App\Support\StatusCatalog')

@php
    $hasFilter = $q !== '' || $status || $lotId || $from || $to;
    $isAdmin = $scope === 'admin';
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10" x-data="{ checkout: null }">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">การจอง</h1>
                <p class="mt-1 text-fg-2">
                    {{ $isAdmin ? 'การจองในลานของผู้ดูแลระบบ' : 'การจองทั้งหมดในลานของคุณ' }}
                    · การจองยืนยันเมื่อกด "รับชำระแล้ว" ของมัดจำในหน้าชำระเงิน
                </p>
            </div>
            @if ($isAdmin)
                <div class="flex flex-col items-start gap-1 sm:items-end">
                    <x-ui.button variant="secondary" :href="route('admin.exports.reservations', request()->query())">
                        <x-ui.icon name="export" class="h-4 w-4" /> ส่งออก CSV
                    </x-ui.button>
                    <span class="text-caption text-fg-3">ใช้ตัวกรองนี้ ครอบคลุมทุกลานในระบบ</span>
                </div>
            @endif
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        {{-- ── ตัวกรอง ───────────────────────────────────────────────── --}}
        <form method="GET" role="search" class="rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_0.8fr_0.8fr]">
                <x-ui.field label="ค้นหา" for="q">
                    <x-ui.input id="q" name="q" type="search" :value="$q" :placeholder="$isAdmin ? 'ทะเบียน ชื่อ หรืออีเมลลูกค้า' : 'ทะเบียน หรือ ชื่อลูกค้า'" />
                </x-ui.field>
                <x-ui.field label="ลานจอด" for="lot_id">
                    <x-ui.select id="lot_id" name="lot_id" placeholder="ทุกลาน">
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string) $lotId === (string) $lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="สถานะ" for="status">
                    <x-ui.select id="status" name="status" placeholder="ทุกสถานะ">
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected($status === $st)>{{ StatusCatalog::label('reservation', $st, 'staff') }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="เริ่มจองตั้งแต่" for="from">
                    <x-ui.input id="from" name="from" data-flatpickr="date" :value="$from" placeholder="วันที่" />
                </x-ui.field>
                <x-ui.field label="ถึง" for="to">
                    <x-ui.input id="to" name="to" data-flatpickr="date" :value="$to" placeholder="วันที่" />
                </x-ui.field>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
                @if ($hasFilter)
                    <x-ui.button variant="ghost" :href="route($scope.'.reservations.index')">ล้างตัวกรอง</x-ui.button>
                @endif
                <p class="ml-auto text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ $reservations->total() }}</span> รายการ</p>
            </div>
        </form>

        {{-- ── รายการ ───────────────────────────────────────────────── --}}
        <div class="mt-4">
            @if ($reservations->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$hasFilter ? 'ไม่พบการจองที่ตรงกับตัวกรอง' : ($isAdmin ? 'ยังไม่มีการจองในลานของผู้ดูแลระบบ' : 'ยังไม่มีการจองในลานของคุณ')"
                        :description="$isAdmin ? 'การจองจากลูกค้าและรถ Walk-in ที่เข้าลานของผู้ดูแลระบบจะแสดงที่นี่' : 'การจองจากลูกค้าและรถ Walk-in ที่เข้าลานของคุณจะแสดงที่นี่'" />
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($reservations as $r)
                        @php
                            $isCheckable = in_array($r->id, $checkableIds, true);
                            $estimate = $estimates[$r->id] ?? null;
                            $graceEnd = $r->reserve_start->copy()->addMinutes(Reservation::gracePeriodMinutes());
                            $canCancel = $isAdmin && $r->canTransitionTo('cancelled');
                            $depositPaid = $r->depositPayment?->payment_status === 'paid';
                        @endphp
                        <li class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[auto_minmax(0,1.3fr)_minmax(0,1fr)_auto] lg:items-center lg:gap-5">
                            <x-ui.plate :plate="$r->license_plate ?? '—'" :province="$r->plate_province" size="sm" class="justify-self-start" />

                            <div class="min-w-0">
                                <p class="font-semibold text-fg">
                                    {{ $r->is_walk_in ? 'Walk-in' : ($r->user?->name ?? '—') }}
                                    <span class="tabular font-normal text-fg-3">#{{ $r->id }}</span>
                                </p>
                                <p class="truncate text-label text-fg-2">
                                    {{ $r->parkingLot?->name ?? '—' }}
                                    @if ($r->parkingSlot)
                                        · ช่อง <span class="tabular">{{ $r->parkingSlot->slot_number }}</span>
                                    @endif
                                    · {{ $r->brand }} {{ $r->color }}
                                </p>
                            </div>

                            <div class="text-label">
                                @if ($r->status === 'checked_in' && $r->parkingLog)
                                    <p class="text-fg">เข้า {{ Format::short($r->parkingLog->check_in_time) }}</p>
                                    <p class="text-fg-2">จอดมาแล้ว {{ Format::duration((int) $r->parkingLog->check_in_time->diffInMinutes(now())) }}
                                        @if ($estimate) · ประมาณ <span class="tabular font-semibold text-fg">{{ Format::baht($estimate['total_amount']) }}</span> @endif
                                    </p>
                                @else
                                    <p class="text-fg">เริ่มจอง {{ Format::short($r->reserve_start) }}</p>
                                    <p class="text-fg-2">
                                        @if ((float) $r->deposit_amount > 0)
                                            มัดจำ <span class="tabular">{{ Format::baht($r->deposit_amount) }}</span>
                                            ({{ StatusCatalog::label('payment', $r->depositPayment?->payment_status ?? 'unpaid', 'staff') }})
                                        @else
                                            ไม่มีมัดจำ
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.status type="reservation" :value="$r->status" audience="staff" />

                                @if ($isCheckable)
                                    <form method="POST" action="{{ route($scope.'.reservations.check-in', $r) }}"
                                        data-confirm="เช็คอินทะเบียน {{ $r->license_plate }} {{ $r->plate_province }} ด้วยตนเอง — ใช้เมื่อกล้องอ่านป้ายไม่ได้ ระบบจะใช้ช่องจอดที่ล็อกไว้ให้การจองนี้ (เข้าได้ถึง {{ Format::short($graceEnd) }})"
                                        data-confirm-title="Manual Check-in การจอง #{{ $r->id }}?"
                                        data-confirm-label="เช็คอิน" data-confirm-cancel="ยังไม่เช็คอิน">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm">เช็คอิน</x-ui.button>
                                    </form>
                                @endif

                                @if ($estimate)
                                    <x-ui.button variant="secondary" size="sm"
                                        x-on:click="checkout = {{ Js::from([
                                            'plate' => $r->license_plate,
                                            'province' => $r->plate_province,
                                            'place' => ($r->parkingLot?->name ?? '').' · ช่อง '.($r->parkingSlot?->slot_number ?? '—'),
                                            'since' => 'เข้า '.Format::short($r->parkingLog->check_in_time).' · จอดมาแล้ว '.Format::duration((int) $r->parkingLog->check_in_time->diffInMinutes(now())),
                                            'hours' => $estimate['total_hours'],
                                            'rate' => Format::baht($estimate['hourly_rate']),
                                            'fee' => Format::baht($estimate['parking_fee']),
                                            'deposit' => $estimate['deposit_deduction'] > 0 ? Format::baht($estimate['deposit_deduction']) : null,
                                            'discount' => $estimate['reservation_discount'] > 0 ? Format::baht($estimate['reservation_discount']) : null,
                                            'total' => Format::baht($estimate['total_amount']),
                                            'url' => route($scope.'.reservations.check-out', $r),
                                        ]) }}; $dispatch('open-modal', 'checkout-confirm')">
                                        เช็คเอาท์
                                    </x-ui.button>
                                @endif

                                @if ($canCancel)
                                    <form method="POST" action="{{ route('admin.reservations.cancel', $r) }}"
                                        data-confirm="ยกเลิกการจอง #{{ $r->id }} ทะเบียน {{ $r->license_plate }} {{ $r->plate_province }} — {{ $depositPaid ? 'เงินมัดจำที่ชำระแล้วไม่คืน' : 'มัดจำที่ยังไม่ชำระจะถูกยกเลิกด้วย' }} และลูกค้าจะได้รับแจ้งเตือน"
                                        data-confirm-title="ยกเลิกการจองนี้?" data-confirm-label="ยกเลิกการจอง"
                                        data-confirm-tone="danger" data-confirm-cancel="เก็บการจองไว้">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger">ยกเลิก</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$reservations" class="mt-6" />
            @endif
        </div>

        @include('staff.partials.checkout-confirm')
    </div>
</x-app-layout>
