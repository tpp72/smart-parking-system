{{--
    ผลที่ประตูลานจากการสแกนครั้งล่าสุด (อ่านจาก session หลัง redirect)
    ลำดับ: 1) เกิดอะไรขึ้นที่ประตู  2) บัญชีดำ  3) ค่าที่ AI อ่านได้เทียบเกณฑ์  4) การจองที่จับคู่ / ใบเสร็จ Check-out
--}}
@use('App\Support\Format')
@use('App\Models\LicensePlateScan')

@php
    $scan = session('scan_result') ? LicensePlateScan::with('parkingLot:id,name')->find(session('scan_result')) : null;
    $gate = session('scan_check_in');
    $lotFull = session('scan_lot_full');
    $reservation = session('scan_reservation_id')
        ? \App\Models\Reservation::with(['parkingLot:id,name', 'parkingSlot:id,slot_number', 'user:id,name'])->find(session('scan_reservation_id'))
        : null;
    $payment = ($gate['payment_id'] ?? null) ? \App\Models\Payment::find($gate['payment_id']) : null;

    $outcome = $gate['outcome'] ?? null;
    [$tone, $headline] = match (true) {
        $lotFull !== null => ['danger', 'ลานเต็ม'],
        $outcome === 'rejected' => ['warning', $scan?->result === LicensePlateScan::RESULT_UNREADABLE ? 'AI อ่านทะเบียนไม่ได้' : 'AI ไม่ผ่านเกณฑ์ความแม่นยำ'],
        $outcome === 'walk_in' => ['success', 'เช็คอินอัตโนมัติสำเร็จ (Walk-in)'],
        $outcome === 'checked_out' => ['success', 'เช็คเอาท์อัตโนมัติสำเร็จ'],
        ($gate['success'] ?? false) => ['success', 'เช็คอินอัตโนมัติสำเร็จ'],
        default => ['warning', 'ไม่สามารถเช็คอิน/เช็คเอาท์อัตโนมัติได้'],
    };

    // ตรงกับกฎจับคู่ใน AutoCheckInService: ยี่ห้อไม่สนตัวพิมพ์ · สีต้องตรงทุกตัวอักษร
    $same = fn ($a, $b, bool $caseless = false) => filled($a) && filled($b)
        && ($caseless ? strcasecmp(trim($a), trim($b)) === 0 : trim($a) === trim($b));
@endphp

@if ($scan || $lotFull)
    <section aria-labelledby="gate-result-title" class="overflow-hidden rounded-card border border-line bg-surface shadow-1">
        {{-- 1) ผลที่ประตูลาน --}}
        <div @class([
            'border-b border-line px-5 py-4',
            'bg-success/10' => $tone === 'success',
            'bg-warning/10' => $tone === 'warning',
            'bg-danger/10' => $tone === 'danger',
        ])>
            <p class="text-caption text-fg-3">ผลที่ประตูลาน · {{ $scan?->parkingLot?->name ?? '' }} {{ $scan ? Format::short($scan->scan_time) : '' }}</p>
            <h2 id="gate-result-title" @class([
                'mt-1 flex items-center gap-2 text-h3',
                'text-success' => $tone === 'success',
                'text-warning' => $tone === 'warning',
                'text-danger' => $tone === 'danger',
            ])>
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @if ($tone === 'success')
                        <circle cx="10" cy="10" r="7.25" /><path d="M6.75 10.25l2.25 2.25 4.25-4.5" />
                    @elseif ($tone === 'danger')
                        <circle cx="10" cy="10" r="7.25" /><path d="M7.5 7.5l5 5M12.5 7.5l-5 5" />
                    @else
                        <path d="M10 3.25l7.25 13H2.75z" /><path d="M10 8.25v3.5M10 14v.01" />
                    @endif
                </svg>
                {{ $headline }}
            </h2>

            @if ($lotFull)
                <p class="mt-1 text-fg-2">{{ $lotFull['message'] }} — ระบบไม่บันทึกผลการสแกนครั้งนี้</p>
            @else
                @if (($gate['success'] ?? false) && $gate['slot'])
                    <p class="mt-2 text-fg">
                        @if ($outcome === 'checked_out')
                            รถออกจากช่อง <span class="num font-semibold">{{ $gate['slot'] }}</span> ระบบคืนช่องจอดแล้ว
                        @else
                            ระบบจัดสรรช่อง <span class="num text-h3 font-semibold">{{ $gate['slot'] }}</span>
                        @endif
                    </p>
                @endif
                {{-- Check-out: รายละเอียดยอดอยู่ในใบเสร็จด้านล่างแล้ว ไม่ต้องแสดงสรุปซ้ำ --}}
                @unless ($payment)
                    <p class="mt-1 text-label text-fg-2">{{ $gate['message'] ?? '' }}</p>
                @endunless
            @endif
        </div>

        {{-- 2) บัญชีดำ — แจ้งเตือนแต่ยังให้เข้าลานตามกฎ --}}
        @if ($scan?->is_suspicious || ($lotFull['is_suspicious'] ?? false))
            <div class="border-b border-line px-5 py-4">
                <x-ui.alert tone="danger" title="พบรถในบัญชีดำ">
                    ระบบแจ้งเจ้าของลานและผู้ดูแลระบบ และบันทึกเหตุการณ์แล้ว
                    @unless ($lotFull)
                        · ตามกฎระบบยังให้รถเข้าลานได้
                    @endunless
                </x-ui.alert>
            </div>
        @endif

        {{-- 3) ค่าที่ AI อ่านได้ --}}
        <div class="grid gap-5 px-5 py-5 sm:grid-cols-[1fr_auto]">
            <div class="min-w-0">
                <h3 class="text-label font-semibold text-fg">ค่าที่ AI อ่านได้</h3>
                <div class="mt-3 flex flex-wrap items-center gap-4">
                    @if ($plate = $scan?->license_plate ?? $lotFull['license_plate'] ?? null)
                        <x-ui.plate :plate="$plate" :province="$scan?->plate_province ?? $lotFull['plate_province'] ?? null" size="lg" />
                    @else
                        <p class="rounded-control border border-dashed border-field px-4 py-3 text-fg-2">อ่านทะเบียนไม่ได้</p>
                    @endif

                    @if ($scan)
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-label">
                            <div>
                                <dt class="text-caption text-fg-3">ยี่ห้อ</dt>
                                <dd class="font-semibold text-fg">{{ $scan->brand ?: 'ไม่ระบุ' }}</dd>
                            </div>
                            <div>
                                <dt class="text-caption text-fg-3">สี</dt>
                                <dd class="font-semibold text-fg"><x-ui.car-color :color="$scan->color" /></dd>
                            </div>
                        </dl>
                    @endif
                </div>

                @if ($scan)
                    <x-ui.accuracy-meter :value="$scan->confidence" class="mt-5 max-w-md" />
                    <div class="mt-3"><x-ui.status type="scan" :value="$scan->result" /></div>
                @endif
            </div>

            @if ($scan?->image_path)
                <img src="{{ Storage::url($scan->image_path) }}" alt="ภาพรถที่สแกน ทะเบียน {{ $scan->license_plate ?: 'อ่านไม่ได้' }}"
                    class="h-40 w-full rounded-control border border-line object-cover sm:w-56" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.replace('hidden', 'flex')">
                <p class="hidden h-40 w-full items-center justify-center rounded-control border border-dashed border-field text-caption text-fg-3 sm:w-56">ไม่พบไฟล์ภาพ</p>
            @endif
        </div>

        {{-- 4ก) ใบเสร็จ Check-out --}}
        @if ($payment)
            <div class="border-t border-dashed border-field bg-surface-2/60 px-5 py-4">
                <h3 class="text-label font-semibold text-fg">ค่าจอด</h3>
                <dl class="mt-2 flex max-w-md flex-col gap-1.5 text-label">
                    <div class="flex justify-between gap-3">
                        <dt class="text-fg-2">จอด <span class="num">{{ (int) $payment->total_hours }}</span> ชม. × <span class="num">{{ Format::baht($payment->hourly_rate) }}</span></dt>
                        <dd class="num text-fg">{{ Format::baht($payment->parking_fee) }}</dd>
                    </div>
                    @if ((float) $payment->deposit_deduction > 0)
                        <div class="flex justify-between gap-3"><dt class="text-fg-2">หักมัดจำ</dt><dd class="num text-fg">−{{ Format::baht($payment->deposit_deduction) }}</dd></div>
                    @endif
                    @if ((float) $payment->reservation_discount > 0)
                        <div class="flex justify-between gap-3"><dt class="text-fg-2">ส่วนลดการจอง</dt><dd class="num text-fg">−{{ Format::baht($payment->reservation_discount) }}</dd></div>
                    @endif
                    <div class="mt-1 flex justify-between gap-3 border-t border-line pt-2 font-semibold">
                        <dt class="text-fg">ยอดชำระ</dt>
                        <dd class="num text-h3 text-fg">{{ Format::baht($payment->total_amount) }}</dd>
                    </div>
                </dl>
                <div class="mt-2"><x-ui.status type="payment" :value="$payment->payment_status" audience="staff" /></div>
            </div>
        @endif

        {{-- 4ข) การจองที่จับคู่: ข้อมูลที่แจ้งไว้เทียบค่าที่ AI อ่านได้ --}}
        @if ($reservation)
            <div class="border-t border-line px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-label font-semibold text-fg">
                        {{ $reservation->is_walk_in ? 'Walk-in' : 'การจอง' }} <span class="num">#{{ $reservation->id }}</span>
                        <span class="font-normal text-fg-2">· {{ $reservation->user?->name ?? '—' }}</span>
                    </h3>
                    <x-ui.status type="reservation" :value="$reservation->status" audience="staff" />
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-label sm:grid-cols-4">
                    <div>
                        <dt class="text-caption text-fg-3">ลานจอด</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ $reservation->parkingLot?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-caption text-fg-3">ช่องจอด</dt>
                        <dd class="num mt-0.5 font-semibold text-fg">{{ $reservation->parkingSlot?->slot_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-caption text-fg-3">เวลาเริ่มจอง</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($reservation->reserve_start) }}</dd>
                    </div>
                    <div>
                        <dt class="text-caption text-fg-3">ป้ายทะเบียน</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ $reservation->license_plate }} {{ $reservation->plate_province }}</dd>
                    </div>
                </dl>

                @if ($scan && ! $reservation->is_walk_in)
                    <table class="mt-4 w-full max-w-lg text-label">
                        <caption class="sr-only">ข้อมูลรถที่แจ้งไว้เทียบกับค่าที่ AI อ่านได้</caption>
                        <thead>
                            <tr class="border-b border-line text-caption text-fg-3">
                                <th scope="col" class="py-1.5 text-start font-medium">ข้อมูล</th>
                                <th scope="col" class="py-1.5 text-start font-medium">แจ้งไว้</th>
                                <th scope="col" class="py-1.5 text-start font-medium">AI อ่านได้</th>
                                <th scope="col" class="py-1.5 text-end font-medium">ผล</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ([
                                ['ยี่ห้อ', $reservation->brand, $scan->brand, $same($reservation->brand, $scan->brand, true)],
                                ['สี', $reservation->color, $scan->color, $same($reservation->color, $scan->color)],
                            ] as [$label, $declared, $read, $match])
                                <tr>
                                    <th scope="row" class="py-2 text-start font-normal text-fg-2">{{ $label }}</th>
                                    <td class="py-2 text-fg">{{ $declared ?: '—' }}</td>
                                    <td class="py-2 text-fg">{{ $read ?: '—' }}</td>
                                    <td @class(['py-2 text-end font-semibold', 'text-success' => $match, 'text-warning' => ! $match])>{{ $match ? 'ตรง' : 'ไม่ตรง' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2 text-caption text-fg-3">จับคู่เมื่อทะเบียนและจังหวัดตรง และยี่ห้อหรือสีตรงอย่างน้อย 1 อย่าง</p>
                @endif
            </div>
        @endif
    </section>
@endif
