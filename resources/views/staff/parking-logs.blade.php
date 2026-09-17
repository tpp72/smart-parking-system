{{--
    ประวัติการจอด (ใช้ร่วม Owner: ลานของตัวเอง · Admin: ลานของผู้ดูแลระบบ) — ค้นหาทะเบียน / ช่วงวันที่เข้า
    · รถที่ยังจอดอยู่ทำ Manual Check-out ได้ (ใบเสร็จประมาณ) · Admin ส่งออก CSV ตามตัวกรอง (ทุกลาน)
    ต้องการ: $logs, $q, $from, $to, $estimates, $scope ('owner' | 'admin')
--}}
@use('App\Support\Format')

@php
    $hasFilter = $q !== '' || $from || $to;
    $isAdmin = $scope === 'admin';
    $where = $isAdmin ? 'ลานของผู้ดูแลระบบ' : 'ลานของคุณ';
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10" x-data="{ checkout: null }">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">ประวัติการจอด</h1>
                <p class="mt-1 text-fg-2">รถทุกคันที่เข้า-ออก{{ $where }} · รถที่ยังจอดอยู่ Check-out ด้วยตนเองได้เมื่อกล้องสแกนขาออกไม่ได้</p>
            </div>
            @if ($isAdmin)
                <div class="flex flex-col items-start gap-1 sm:items-end">
                    <x-ui.button variant="secondary" :href="route('admin.exports.parking-logs', request()->query())">
                        <x-ui.icon name="export" class="h-4 w-4" /> ส่งออก CSV
                    </x-ui.button>
                    <span class="text-caption text-fg-3">ใช้ตัวกรองนี้ ครอบคลุมทุกลานในระบบ</span>
                </div>
            @endif
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="GET" role="search" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <x-ui.field label="ค้นหาทะเบียน" for="q" class="min-w-0 flex-1 sm:max-w-xs">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="เช่น กข 1234" />
            </x-ui.field>
            <x-ui.field label="เข้าลานตั้งแต่" for="from">
                <x-ui.input id="from" name="from" data-flatpickr="date" :value="$from" placeholder="วันที่" />
            </x-ui.field>
            <x-ui.field label="ถึง" for="to">
                <x-ui.input id="to" name="to" data-flatpickr="date" :value="$to" placeholder="วันที่" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($hasFilter)
                <x-ui.button variant="ghost" :href="route($scope.'.parking-logs.index')">ล้างตัวกรอง</x-ui.button>
            @endif
        </form>

        <div class="mt-4">
            @if ($logs->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$hasFilter ? 'ไม่พบประวัติที่ตรงกับตัวกรอง' : 'ยังไม่มีรถเข้า'.$where"
                        description="เมื่อรถ Check-in ด้วยการจองหรือ Walk-in ประวัติจะแสดงที่นี่" />
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($logs as $log)
                        @php
                            $parked = $log->check_out_time === null;
                            $estimate = $estimates[$log->id] ?? null;
                            $minutes = (int) $log->check_in_time->diffInMinutes($log->check_out_time ?? now());
                        @endphp
                        <li class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[auto_minmax(0,1.2fr)_minmax(0,1fr)_auto] lg:items-center lg:gap-5">
                            <x-ui.plate :plate="$log->license_plate ?? '—'" :province="$log->plate_province" size="sm" class="justify-self-start" />

                            <div class="min-w-0">
                                <p class="font-semibold text-fg">
                                    {{ $log->reservation?->is_walk_in ? 'Walk-in' : ($log->reservation?->user?->name ?? '—') }}
                                    <span class="tabular font-normal text-fg-3">#{{ $log->reservation_id }}</span>
                                </p>
                                <p class="truncate text-label text-fg-2">
                                    {{ $log->parkingLot?->name ?? '—' }}
                                    @if ($log->parkingSlot) · ช่อง <span class="tabular">{{ $log->parkingSlot->slot_number }}</span> @endif
                                    · {{ $log->brand }} {{ $log->color }}
                                </p>
                            </div>

                            <div class="text-label">
                                <p class="text-fg">เข้า {{ Format::short($log->check_in_time) }}</p>
                                <p class="text-fg-2">
                                    @if ($parked)
                                        ยังจอดอยู่ · {{ Format::duration($minutes) }}
                                        @if ($estimate) · ประมาณ <span class="tabular font-semibold text-fg">{{ Format::baht($estimate['total_amount']) }}</span> @endif
                                    @else
                                        ออก {{ Format::short($log->check_out_time) }} · {{ Format::duration($minutes) }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.status type="reservation" :value="$parked ? 'checked_in' : 'completed'" audience="staff" />
                                @if ($parked && $estimate)
                                    <x-ui.button variant="secondary" size="sm"
                                        x-on:click="checkout = {{ Js::from([
                                            'plate' => $log->license_plate,
                                            'province' => $log->plate_province,
                                            'place' => ($log->parkingLot?->name ?? '').' · ช่อง '.($log->parkingSlot?->slot_number ?? '—'),
                                            'since' => 'เข้า '.Format::short($log->check_in_time).' · จอดมาแล้ว '.Format::duration($minutes),
                                            'hours' => $estimate['total_hours'],
                                            'rate' => Format::baht($estimate['hourly_rate']),
                                            'fee' => Format::baht($estimate['parking_fee']),
                                            'deposit' => $estimate['deposit_deduction'] > 0 ? Format::baht($estimate['deposit_deduction']) : null,
                                            'discount' => $estimate['reservation_discount'] > 0 ? Format::baht($estimate['reservation_discount']) : null,
                                            'total' => Format::baht($estimate['total_amount']),
                                            'url' => route($scope.'.reservations.check-out', $log->reservation_id),
                                        ]) }}; $dispatch('open-modal', 'checkout-confirm')">
                                        เช็คเอาท์
                                    </x-ui.button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$logs" class="mt-6" />
            @endif
        </div>

        @include('staff.partials.checkout-confirm')
    </div>
</x-app-layout>
