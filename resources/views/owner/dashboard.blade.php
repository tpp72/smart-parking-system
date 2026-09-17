{{--
    ภาพรวมเจ้าของลาน: งานที่ต้องทำก่อน → สถานะรายลาน ณ ตอนนี้ → รถที่จอดอยู่ / การจองที่กำลังจะมาถึง → ลาออก
    (กราฟรายได้อยู่หน้า "รายได้")
--}}
@use('App\Support\Format')

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        @if ($ownerStatus !== 'approved')
            {{-- บัญชีที่ยังไม่ได้รับอนุมัติ: ดูสถานะคำขอ --}}
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state
                    :title="$ownerStatus === 'rejected' ? 'คำขอเป็นเจ้าของลานไม่ได้รับการอนุมัติ' : 'คำขอเป็นเจ้าของลานรอการพิจารณา'"
                    :description="$ownerStatus === 'rejected' ? ($application?->rejection_reason ? 'เหตุผล: '.$application->rejection_reason : 'แก้ไขข้อมูลแล้วส่งคำขอใหม่ได้') : 'ผู้ดูแลระบบจะแจ้งผลผ่านการแจ้งเตือน'">
                    <x-ui.button :href="route('owner.application.show')">ดูสถานะคำขอ</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-h1 text-fg">ภาพรวม</h1>
                    <p class="mt-1 text-fg-2">ลานของคุณ <span class="tabular font-semibold text-fg">{{ $totals['lots'] }}</span> ลาน · ข้อมูล ณ {{ Format::time(now()) }}</p>
                </div>
                <x-ui.button variant="secondary" :href="route('owner.parking-lots.create')">
                    <x-ui.icon name="plus" class="h-4 w-4" /> เพิ่มลานจอด
                </x-ui.button>
            </div>

            {{-- ── งานที่ต้องทำ ───────────────────────────────────────── --}}
            <section aria-labelledby="tasks-title" class="mt-6">
                <h2 id="tasks-title" class="sr-only">งานที่ต้องทำ</h2>
                <div class="grid overflow-hidden rounded-card border border-line bg-surface shadow-1 sm:grid-cols-3 sm:divide-x sm:divide-line">
                    @foreach ([
                        ['ยืนยันรับเงินมัดจำ', $tasks['deposits']['count'], 'รายการ · '.Format::baht($tasks['deposits']['amount']), 'จองจะยืนยันและได้ช่องจอดเมื่อยืนยันรับเงิน', route('owner.payments.index', ['status' => 'unpaid'])],
                        ['ยืนยันรับเงินค่าจอด', $tasks['checkouts']['count'], 'รายการ · '.Format::baht($tasks['checkouts']['amount']), 'ยอดหลัง Check-out ที่ยังไม่ได้รับเงิน', route('owner.payments.index', ['status' => 'unpaid'])],
                        ['ถึงเวลาเข้าลาน', $tasks['arriving'], 'การจอง', 'ถ้ากล้องอ่านป้ายไม่ได้ ใช้ Manual Check-in ในหน้าการจอง', route('owner.reservations.index', ['status' => 'confirmed'])],
                    ] as [$label, $count, $unit, $hint, $href])
                        <a href="{{ $href }}" @class([
                            'group flex flex-col gap-1 border-b border-line px-5 py-4 transition-colors duration-fast last:border-b-0 hover:bg-surface-2 sm:border-b-0',
                            'bg-warning/10' => $count > 0,
                        ])>
                            <span class="text-label font-semibold text-fg">{{ $label }}</span>
                            <span class="flex items-baseline gap-2">
                                <span @class(['num text-kpi leading-none', 'text-fg' => $count > 0, 'text-fg-3' => $count === 0])>{{ $count }}</span>
                                <span class="text-label text-fg-2">{{ $unit }}</span>
                            </span>
                            <span class="text-caption text-fg-3">{{ $count > 0 ? $hint : 'ไม่มีงานค้าง' }}</span>
                        </a>
                    @endforeach
                </div>
            </section>

            {{-- ── สถานะรายลาน ─────────────────────────────────────────── --}}
            <section aria-labelledby="lots-title" class="mt-10">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 id="lots-title" class="text-h2 text-fg">ลานของคุณตอนนี้</h2>
                    <a href="{{ route('owner.parking-lots.index') }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">จัดการลานจอด</a>
                </div>

                @if ($lots->isEmpty())
                    <div class="mt-4 rounded-card border border-line bg-surface shadow-1">
                        <x-ui.empty-state title="ยังไม่มีลานจอด" description="เพิ่มลานจอดและช่องจอดก่อน ลูกค้าจึงจะจองได้">
                            <x-ui.button :href="route('owner.parking-lots.create')">เพิ่มลานจอด</x-ui.button>
                        </x-ui.empty-state>
                    </div>
                @else
                    <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        @foreach ($lots as $lot)
                            <li class="grid gap-3 px-4 py-4 sm:px-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_8rem] md:items-center md:gap-6">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-fg">{{ $lot->name }}</p>
                                    <p class="text-label text-fg-2">
                                        <span class="tabular">{{ Format::baht($lot->hourly_rate) }}</span>/ชม. ·
                                        {{ $lot->reservations_enabled ? 'เปิดรับจอง' : 'ปิดรับจองล่วงหน้า' }}
                                    </p>
                                </div>
                                <x-ui.occupancy-bar :available="$lot->available" :reserved="$lot->reserved" :occupied="$lot->occupied" />
                                <div class="md:text-end">
                                    <p class="text-caption text-fg-3">รับเงินวันนี้</p>
                                    <p class="num font-semibold text-fg">{{ Format::baht($lot->revenue_today) }}</p>
                                </div>
                            </li>
                        @endforeach
                        @if ($lots->count() > 1)
                            <li class="grid gap-3 bg-surface-2/60 px-4 py-4 sm:px-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_8rem] md:items-center md:gap-6">
                                <p class="font-semibold text-fg">รวมทุกลาน</p>
                                <x-ui.occupancy-bar :available="$totals['available']" :reserved="$totals['reserved']" :occupied="$totals['occupied']" />
                                <div class="md:text-end">
                                    <p class="text-caption text-fg-3">รับเงินวันนี้</p>
                                    <p class="num font-semibold text-fg">{{ Format::baht($totals['revenue_today']) }}</p>
                                </div>
                            </li>
                        @endif
                    </ol>
                @endif
            </section>

            <div class="mt-10 grid gap-10 lg:grid-cols-2">
                {{-- ── รถที่จอดอยู่ ─────────────────────────────────────── --}}
                <section aria-labelledby="parked-title" class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 id="parked-title" class="text-h2 text-fg">รถที่จอดอยู่</h2>
                        <a href="{{ route('owner.reservations.index', ['status' => 'checked_in']) }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">ดูทั้งหมด</a>
                    </div>
                    @if ($parked->isEmpty())
                        <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ไม่มีรถจอดอยู่ในลานของคุณ</p>
                    @else
                        <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                            @foreach ($parked as $r)
                                <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                    <x-ui.plate :plate="$r->license_plate" :province="$r->plate_province" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-label font-semibold text-fg">{{ $r->parkingLot?->name }} · ช่อง <span class="tabular">{{ $r->parkingSlot?->slot_number ?? '—' }}</span></p>
                                        <p class="text-caption text-fg-3">
                                            เข้า {{ Format::short($r->parkingLog?->check_in_time) }}
                                            @if ($r->parkingLog)
                                                · จอดมาแล้ว {{ Format::duration((int) $r->parkingLog->check_in_time->diffInMinutes(now())) }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($r->is_walk_in)
                                        <span class="shrink-0 text-caption text-fg-3">Walk-in</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                {{-- ── การจองที่กำลังจะมาถึง ───────────────────────────── --}}
                <section aria-labelledby="upcoming-title" class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 id="upcoming-title" class="text-h2 text-fg">กำลังจะมาถึง</h2>
                        <a href="{{ route('owner.reservations.index') }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">การจองทั้งหมด</a>
                    </div>
                    <p class="mt-1 text-label text-fg-3">การจองที่ยังไม่เข้าลาน ภายใน 24 ชั่วโมง</p>
                    @if ($upcoming->isEmpty())
                        <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ไม่มีการจองที่กำลังจะมาถึง</p>
                    @else
                        <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                            @foreach ($upcoming as $r)
                                <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                    <x-ui.plate :plate="$r->license_plate" :province="$r->plate_province" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-label font-semibold text-fg">{{ Format::short($r->reserve_start) }} · {{ $r->parkingLot?->name }}</p>
                                        <p class="truncate text-caption text-fg-3">{{ $r->user?->name }} · มัดจำ <span class="tabular">{{ Format::baht($r->deposit_amount) }}</span></p>
                                    </div>
                                    <x-ui.status type="reservation" :value="$r->status" audience="staff" class="shrink-0" />
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>

            {{-- ── ลาออกจากการเป็นเจ้าของลาน (มีผลเมื่อผู้ดูแลระบบอนุมัติ — project-plan.md §16) ── --}}
            <section id="owner-resignation" aria-labelledby="resign-title" class="mt-12 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                @if ($resignation?->isPending())
                    <h2 id="resign-title" class="text-h3 text-fg">คำร้องลาออกรอการพิจารณา</h2>
                    <p class="mt-1 text-fg-2">ส่งเมื่อ {{ Format::short($resignation->created_at) }} — ยังจัดการลานได้ตามปกติจนกว่าผู้ดูแลระบบจะอนุมัติ</p>
                    <p class="mt-3 text-label text-fg-2"><span class="font-semibold text-fg">เหตุผล:</span> {{ $resignation->reason }}</p>
                @else
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="max-w-2xl">
                            <h2 id="resign-title" class="text-h3 text-fg">ลาออกจากการเป็นเจ้าของลาน</h2>
                            <p class="mt-1 text-label text-fg-2">
                                ต้องได้รับอนุมัติจากผู้ดูแลระบบ เมื่ออนุมัติ: การจองที่ยังไม่ Check-in ถูกยกเลิก · รถที่จอดอยู่ถูก Check-out ·
                                ลานจอดทั้งหมดของคุณถูกลบ · บัญชีกลับเป็นผู้ใช้
                            </p>
                        </div>
                        <x-ui.button variant="secondary" class="text-danger" x-data x-on:click="$dispatch('open-modal', 'owner-resign')">ยื่นคำร้องลาออก</x-ui.button>
                    </div>

                    @if ($resignation?->status === 'rejected')
                        <x-ui.alert tone="warning" title="คำร้องครั้งล่าสุดไม่ได้รับการอนุมัติ" class="mt-4">
                            เหตุผล: {{ $resignation->rejection_reason }}
                        </x-ui.alert>
                    @endif

                    <x-ui.modal name="owner-resign" :show="$errors->has('reason')" maxWidth="md" title="ยื่นคำร้องลาออก"
                        description="คำร้องมีผลเมื่อผู้ดูแลระบบอนุมัติ ระหว่างนี้ยังจัดการลานได้ตามปกติ">
                        <form method="POST" action="{{ route('owner.resignation.store') }}" id="owner-resign-form" class="px-5 py-4">
                            @csrf
                            <x-ui.field label="เหตุผลในการลาออก" for="reason" required>
                                <x-ui.textarea id="reason" name="reason" rows="4" required maxlength="1000">{{ old('reason') }}</x-ui.textarea>
                            </x-ui.field>
                        </form>
                        <x-slot name="footer">
                            <x-ui.button variant="secondary" x-on:click="$dispatch('close')">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" form="owner-resign-form" variant="danger">ส่งคำร้องลาออก</x-ui.button>
                        </x-slot>
                    </x-ui.modal>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
