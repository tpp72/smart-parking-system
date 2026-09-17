{{--
    ภาพรวมระบบของผู้ดูแลระบบ (ทุกลานในระบบ)
    งานที่รอผู้ดูแลระบบ (ไม่ขึ้นกับตัวกรอง) → ตัวกรองลาน + ช่วงเวลา → KPI "ตอนนี้" / "ในช่วงเวลา" (ทุกตัวบอกขอบเขตและหน่วย)
    → ภาพรวมลานจอด (กดลาน = กรอง KPI) → การจองใหม่ตามสถานะ / ลานที่มีการจองมากที่สุด → รถที่จอดอยู่ / สแกนล่าสุด
    ต้องการ: $range, $lots, $lot (null = ทุกลาน), $stats, $tasks, $reservationStatus, $topLots, $lotsOverview, $parked, $latestScans
--}}
@use('App\Support\Format')
@use('App\Support\StatusCatalog')

@php
    $rangeLabel = ['today' => 'วันนี้', '7d' => '7 วันล่าสุด', 'month' => 'เดือนนี้'][$range];
    $rangeDates = match ($range) {
        '7d' => now()->subDays(6)->format('d/m').' – '.now()->format('d/m'),
        'month' => now()->startOfMonth()->format('d/m').' – '.now()->format('d/m'),
        default => now()->format('d/m/Y'),
    };
    $scopeLabel = $lot ? $lot->name : 'รวมทุกลาน ('.$stats['lots_total'].' ลาน)';

    // ลิงก์ของหน้านี้ที่คงตัวกรองเดิมไว้ แล้วเลื่อนมาที่ตัวเลข
    $dashboardUrl = fn (array $params = []) => route('admin.dashboard', array_filter(
        array_merge(['lot_id' => $lot?->id, 'range' => $range], $params),
        fn ($v, $k) => filled($v) && ! ($k === 'range' && $v === 'today'),
        ARRAY_FILTER_USE_BOTH,
    )).'#dashboard-numbers';

    $unpaidCount = $stats['unpaid_deposits']['count'] + $stats['unpaid_checkouts']['count'];
    $newReservations = $reservationStatus->sum();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-3xl">
                <h1 class="text-h1 text-fg">ภาพรวมระบบ</h1>
                <p class="mt-1 text-fg-2">
                    ทุกลานในระบบ <span class="tabular font-semibold text-fg">{{ $stats['lots_total'] }}</span> ลาน
                    (ลานของผู้ดูแลระบบ <span class="tabular">{{ $stats['admin_lots_total'] }}</span> ลาน) · ข้อมูล ณ {{ Format::time(now()) }}
                </p>
                <p class="mt-1 text-caption text-fg-3">ตัวเลขในหน้านี้ครอบคลุมทุกลาน ส่วนหน้าจัดการการจอง ชำระเงิน และลานจอด ใช้ได้เฉพาะลานของผู้ดูแลระบบ</p>
            </div>
            <x-ui.button variant="secondary" :href="route('admin.exports.index')">
                <x-ui.icon name="export" class="h-4 w-4" /> ส่งออก CSV
            </x-ui.button>
        </div>

        {{-- ── งานที่รอผู้ดูแลระบบ (ไม่ขึ้นกับตัวกรอง) ─────────────────────── --}}
        <section aria-labelledby="tasks-title" class="mt-6">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                <h2 id="tasks-title" class="text-label font-semibold text-fg">งานที่รอผู้ดูแลระบบ</h2>
                <p class="text-caption text-fg-3">ไม่ขึ้นกับตัวกรองด้านล่าง</p>
            </div>
            <div class="mt-2 grid overflow-hidden rounded-card border border-line bg-surface shadow-1 sm:grid-cols-3 sm:divide-x sm:divide-line">
                @foreach ([
                    ['คำขอเป็นเจ้าของลาน', $tasks['applications'], 'รอพิจารณา', 'ตรวจข้อมูลและเอกสาร แล้วอนุมัติหรือไม่อนุมัติ', route('admin.owner-applications.index', ['status' => 'pending'])],
                    ['คำร้องลาออกของเจ้าของลาน', $tasks['resignations'], 'รอพิจารณา', 'อนุมัติแล้วลานของเจ้าของจะถูกปิดและลบ', route('admin.owner-resignations.index')],
                    ['เงินรอยืนยันในลานของผู้ดูแลระบบ', $tasks['payments']['count'], 'รายการ · '.Format::baht($tasks['payments']['amount']), 'มัดจำและค่าจอดที่ยังไม่กด "รับชำระแล้ว"', route('admin.payments.index', ['status' => 'unpaid'])],
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

        {{-- ── ตัวกรอง + KPI ──────────────────────────────────────────── --}}
        <section id="dashboard-numbers" aria-labelledby="numbers-title" class="mt-10 scroll-mt-20">
            <h2 id="numbers-title" class="text-h2 text-fg">ตัวเลขสำคัญ</h2>

            <div class="mt-3 flex flex-wrap items-end justify-between gap-4 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
                <form method="GET" action="{{ route('admin.dashboard') }}#dashboard-numbers" class="flex min-w-0 flex-1 basis-full flex-wrap items-end gap-3 md:basis-auto">
                    @if ($range !== 'today')
                        <input type="hidden" name="range" value="{{ $range }}">
                    @endif
                    <x-ui.field label="ลานจอด" for="lot_id" class="min-w-[12rem] flex-1 md:max-w-sm">
                        <x-ui.select id="lot_id" name="lot_id" :placeholder="'ทุกลาน ('.$stats['lots_total'].' ลาน)'">
                            @foreach ($lots as $option)
                                <option value="{{ $option->id }}" @selected($lot?->id === $option->id)>
                                    {{ $option->name }} — {{ $option->owner ? 'เจ้าของ '.$option->owner->name : 'ลานของผู้ดูแลระบบ' }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary">แสดง</x-ui.button>
                </form>

                <nav aria-label="ช่วงเวลา">
                    <p class="text-label font-semibold text-fg" aria-hidden="true">ช่วงเวลา</p>
                    <div class="mt-1.5 inline-flex rounded-control border border-line bg-surface-2 p-0.5">
                        @foreach (['today' => 'วันนี้', '7d' => '7 วัน', 'month' => 'เดือนนี้'] as $key => $label)
                            <a href="{{ $dashboardUrl(['range' => $key]) }}" @if ($range === $key) aria-current="page" @endif
                                @class([
                                    'inline-flex min-h-touch items-center rounded-control px-4 text-label transition-colors duration-fast',
                                    'bg-surface font-semibold text-fg shadow-1' => $range === $key,
                                    'text-fg-2 hover:text-fg' => $range !== $key,
                                ])>{{ $label }}</a>
                        @endforeach
                    </div>
                </nav>
            </div>

            <p class="mt-3 text-label text-fg-2" aria-live="polite">
                กำลังดู <span class="font-semibold text-fg">{{ $scopeLabel }}</span>
                · ช่วงเวลา <span class="font-semibold text-fg">{{ $rangeLabel }}</span> <span class="tabular">({{ $rangeDates }})</span>
                @if ($lot)
                    · <a href="{{ $dashboardUrl(['lot_id' => null]) }}" class="font-semibold text-primary-ink underline-offset-4 hover:underline">ดูรวมทุกลาน</a>
                @endif
            </p>

            {{-- ตอนนี้ --}}
            <h3 class="mt-5 text-label font-semibold text-fg">ตอนนี้ <span class="font-normal text-fg-3">· ไม่ขึ้นกับช่วงเวลา</span></h3>
            <dl class="mt-2 grid gap-px overflow-hidden rounded-card border border-line bg-line shadow-1 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">รถที่จอดอยู่ตอนนี้</dt>
                    <dd class="flex items-baseline gap-1.5"><span class="num text-kpi leading-none text-fg">{{ $stats['active_now'] }}</span><span class="text-label text-fg-2">คัน</span></dd>
                    <dd class="text-caption text-fg-3">{{ $scopeLabel }}</dd>
                </div>
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">ช่องจอดว่าง</dt>
                    <dd class="flex items-baseline gap-1.5">
                        <span class="num text-kpi leading-none text-fg">{{ $stats['slots_available'] }}</span>
                        <span class="text-label text-fg-2">จาก <span class="tabular">{{ $stats['slots_total'] }}</span> ช่อง</span>
                    </dd>
                    <dd class="text-caption text-fg-3">จอง <span class="tabular">{{ $stats['slots_reserved'] }}</span> · ใช้งาน <span class="tabular">{{ $stats['slots_occupied'] }}</span> · {{ $scopeLabel }}</dd>
                </div>
                {{-- พื้นหลังของ grid เป็นสีเส้น จึงซ้อนสีเตือนบน bg-surface แทนการใช้สีโปร่งใสตรง ๆ --}}
                <div @class(['flex flex-col gap-1 bg-surface px-5 py-4', '[background-image:linear-gradient(rgb(var(--color-warning)/0.1),rgb(var(--color-warning)/0.1))]' => $unpaidCount > 0])>
                    <dt class="text-label font-semibold text-fg-2">รอยืนยันรับเงิน</dt>
                    <dd class="flex items-baseline gap-1.5"><span class="num text-kpi leading-none text-fg">{{ $unpaidCount }}</span><span class="text-label text-fg-2">รายการ</span></dd>
                    <dd class="text-caption text-fg-3">
                        มัดจำ <span class="tabular">{{ $stats['unpaid_deposits']['count'] }}</span> · <span class="tabular">{{ Format::baht($stats['unpaid_deposits']['amount']) }}</span><br>
                        ค่าจอด <span class="tabular">{{ $stats['unpaid_checkouts']['count'] }}</span> · <span class="tabular">{{ Format::baht($stats['unpaid_checkouts']['amount']) }}</span>
                    </dd>
                </div>
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">บัญชีดำที่ใช้งาน</dt>
                    <dd class="flex items-baseline gap-1.5"><span class="num text-kpi leading-none text-fg">{{ $stats['blacklist_active'] }}</span><span class="text-label text-fg-2">ทะเบียน</span></dd>
                    <dd class="text-caption text-fg-3">
                        ทั้งระบบ ไม่ขึ้นกับลานที่เลือก ·
                        <a href="{{ route('admin.suspicious-vehicles.index') }}" class="font-semibold text-primary-ink underline-offset-4 hover:underline">จัดการบัญชีดำ</a>
                    </dd>
                </div>
            </dl>

            {{-- ในช่วงเวลา --}}
            <h3 class="mt-6 text-label font-semibold text-fg">{{ $rangeLabel }} <span class="font-normal text-fg-3">· <span class="tabular">{{ $rangeDates }}</span></span></h3>
            <dl class="mt-2 grid gap-px overflow-hidden rounded-card border border-line bg-line shadow-1 lg:grid-cols-3">
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">รับเงินแล้ว</dt>
                    <dd class="num text-kpi leading-none text-fg">{{ Format::baht($stats['revenue_paid']) }}</dd>
                    <dd class="text-caption text-fg-3">
                        มัดจำ <span class="tabular">{{ Format::baht($stats['revenue_deposit']) }}</span> · ค่าจอด <span class="tabular">{{ Format::baht($stats['revenue_parking']) }}</span> · {{ $scopeLabel }}
                    </dd>
                </div>
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">รถเข้าลาน</dt>
                    <dd class="flex items-baseline gap-1.5"><span class="num text-kpi leading-none text-fg">{{ $stats['check_ins'] }}</span><span class="text-label text-fg-2">คัน</span></dd>
                    <dd class="text-caption text-fg-3">
                        ในนี้ Walk-in <span class="tabular">{{ $stats['walk_ins'] }}</span> คัน · รถออก <span class="tabular">{{ $stats['check_outs'] }}</span> คัน · {{ $scopeLabel }}
                    </dd>
                </div>
                <div class="flex flex-col gap-1 bg-surface px-5 py-4">
                    <dt class="text-label font-semibold text-fg-2">AI สแกนป้ายทะเบียน</dt>
                    <dd class="flex items-baseline gap-1.5"><span class="num text-kpi leading-none text-fg">{{ $stats['scans_total'] }}</span><span class="text-label text-fg-2">ครั้ง</span></dd>
                    <dd class="text-caption text-fg-3">
                        ผ่านเกณฑ์ <span class="tabular">{{ $stats['scans_passed'] }}</span> · ไม่ผ่าน <span class="tabular">{{ $stats['scans_failed'] }}</span>
                        · <span @class(['text-danger font-semibold' => $stats['scans_suspicious'] > 0])>พบบัญชีดำ <span class="tabular">{{ $stats['scans_suspicious'] }}</span></span> · {{ $scopeLabel }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- ── ภาพรวมลานจอด (กดเพื่อกรองตัวเลขด้านบน) ───────────────────── --}}
        <section aria-labelledby="lots-title" class="mt-10">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="lots-title" class="text-h2 text-fg">ภาพรวมลานจอด</h2>
                    <p class="mt-1 text-label text-fg-2">ทุกลาน {{ $lotsOverview->count() }} ลาน · กดลานเพื่อดูตัวเลขด้านบนเฉพาะลานนั้น · ช่องจอดเป็นสถานะตอนนี้ ส่วนรับเงินเป็นของ{{ $rangeLabel }}</p>
                </div>
                <a href="{{ route('admin.parking-lots.index') }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">จัดการลานของผู้ดูแลระบบ</a>
            </div>

            @if ($lotsOverview->isEmpty())
                <div class="mt-4 rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state title="ยังไม่มีลานจอดในระบบ" description="เพิ่มลานของผู้ดูแลระบบ หรืออนุมัติคำขอเป็นเจ้าของลาน">
                        <x-ui.button :href="route('admin.parking-lots.create')">เพิ่มลานจอด</x-ui.button>
                    </x-ui.empty-state>
                </div>
            @else
                <ul class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($lotsOverview as $overview)
                        @php $current = $lot?->id === $overview->id; @endphp
                        <li class="min-w-0">
                            <a href="{{ $dashboardUrl(['lot_id' => $overview->id]) }}" @if ($current) aria-current="true" @endif
                                @class([
                                    'flex h-full flex-col gap-3 rounded-card border bg-surface p-4 shadow-1 transition-colors duration-fast hover:bg-surface-2',
                                    'border-primary-ink ring-1 ring-primary-ink' => $current,
                                    'border-line' => ! $current,
                                ])>
                                <span class="flex items-start justify-between gap-3">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-fg">{{ $overview->name }}</span>
                                        <span class="block truncate text-caption text-fg-2">
                                            {{ $overview->owner_name ? 'เจ้าของ '.$overview->owner_name : 'ลานของผู้ดูแลระบบ' }}
                                            · <span class="tabular">{{ Format::baht($overview->hourly_rate) }}</span>/ชม.
                                        </span>
                                    </span>
                                    @if ($current)
                                        <span class="shrink-0 rounded-control bg-primary px-2 py-0.5 text-caption font-semibold text-on-primary">กำลังดู</span>
                                    @endif
                                </span>
                                @if ($overview->available + $overview->reserved + $overview->occupied > 0)
                                    <x-ui.occupancy-bar :available="$overview->available" :reserved="$overview->reserved" :occupied="$overview->occupied" />
                                @else
                                    <span class="text-caption text-warning">ยังไม่มีช่องจอด</span>
                                @endif
                                <span class="mt-auto grid grid-cols-2 gap-3 border-t border-line pt-3 text-caption">
                                    <span>
                                        <span class="block text-fg-3">รถจอดอยู่</span>
                                        <span class="num font-semibold text-fg">{{ $overview->parked }}</span> <span class="text-fg-2">คัน</span>
                                    </span>
                                    <span>
                                        <span class="block text-fg-3">รับเงิน{{ $rangeLabel }}</span>
                                        <span class="num font-semibold text-fg">{{ Format::baht($overview->revenue) }}</span>
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- ── การจองใหม่ ─────────────────────────────────────────────── --}}
        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="status-title" class="min-w-0 rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 id="status-title" class="text-h3 text-fg">การจองใหม่{{ $rangeLabel }}</h2>
                <p class="mt-0.5 text-caption text-fg-3">{{ $scopeLabel }} · รวม Walk-in · นับตามสถานะปัจจุบัน · ทั้งหมด <span class="tabular">{{ $newReservations }}</span> รายการ</p>
                @if ($newReservations === 0)
                    <p class="mt-4 rounded-control bg-surface-2 px-4 py-6 text-center text-label text-fg-2">ยังไม่มีการจองใหม่{{ $rangeLabel }}</p>
                @else
                    <x-ui.bar-list class="mt-3" :caption="'การจองใหม่'.$rangeLabel.' แยกตามสถานะ'" unit="รายการ" :items="$reservationStatus->map(fn ($value, $status) => [
                        'label' => StatusCatalog::label('reservation', $status, 'staff'),
                        'value' => $value,
                        'tone' => StatusCatalog::resolve('reservation', $status)['tone'],
                    ])->values()->all()" />
                @endif
            </section>

            <section aria-labelledby="top-lots-title" class="min-w-0 rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 id="top-lots-title" class="text-h3 text-fg">ลานที่มีการจองใหม่มากที่สุด</h2>
                <p class="mt-0.5 text-caption text-fg-3">{{ $rangeLabel }} · เทียบทุกลาน ไม่ขึ้นกับลานที่เลือก · กดเพื่อดูลานนั้น</p>
                @if ($topLots->isEmpty())
                    <p class="mt-4 rounded-control bg-surface-2 px-4 py-6 text-center text-label text-fg-2">ยังไม่มีการจองใหม่{{ $rangeLabel }}</p>
                @else
                    <x-ui.bar-list class="mt-3" :caption="'5 ลานที่มีการจองใหม่มากที่สุด '.$rangeLabel" unit="รายการ" :items="$topLots->map(fn ($row) => [
                        'label' => $row->name,
                        'value' => $row->total,
                        'href' => $dashboardUrl(['lot_id' => $row->id]),
                        'current' => $lot?->id === (int) $row->id,
                    ])->all()" />
                    @if ($lot && ! $topLots->contains('id', $lot->id))
                        <p class="mt-2 px-2 text-caption text-fg-3">{{ $lot->name }} ไม่อยู่ใน 5 อันดับแรก</p>
                    @endif
                @endif
            </section>
        </div>

        {{-- ── รถที่จอดอยู่ + สแกนล่าสุด ───────────────────────────────── --}}
        <div class="mt-10 grid gap-10 lg:grid-cols-2">
            <section aria-labelledby="parked-title" class="min-w-0">
                <h2 id="parked-title" class="text-h2 text-fg">รถที่จอดอยู่ตอนนี้</h2>
                <p class="mt-1 text-label text-fg-3">
                    {{ $scopeLabel }} ·
                    @if ($stats['active_now'] > $parked->count())
                        แสดง <span class="tabular">{{ $parked->count() }}</span> คันล่าสุดจาก <span class="tabular">{{ $stats['active_now'] }}</span> คัน
                    @else
                        ทั้งหมด <span class="tabular">{{ $stats['active_now'] }}</span> คัน
                    @endif
                </p>
                @if ($parked->isEmpty())
                    <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ไม่มีรถจอดอยู่ใน{{ $lot ? 'ลานนี้' : 'ระบบ' }}ตอนนี้</p>
                @else
                    <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        @foreach ($parked as $log)
                            <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                <x-ui.plate :plate="$log->license_plate ?? '—'" :province="$log->plate_province" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-label font-semibold text-fg">{{ $log->parkingLot?->name }}</p>
                                    <p class="truncate text-caption text-fg-2">
                                        ช่อง <span class="tabular">{{ $log->parkingSlot?->slot_number ?? '—' }}</span>
                                        · {{ $log->reservation?->is_walk_in ? 'Walk-in' : ($log->reservation?->user?->name ?? '—') }}
                                    </p>
                                    <p class="truncate text-caption text-fg-3">
                                        เข้า {{ Format::short($log->check_in_time) }} · จอดมาแล้ว {{ Format::duration((int) $log->check_in_time->diffInMinutes(now())) }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section aria-labelledby="scans-title" class="min-w-0">
                <h2 id="scans-title" class="text-h2 text-fg">สแกนล่าสุด</h2>
                <p class="mt-1 text-label text-fg-3">{{ $scopeLabel }} · <span class="tabular">{{ $latestScans->count() }}</span> ครั้งล่าสุด ไม่ขึ้นกับช่วงเวลา</p>
                @if ($latestScans->isEmpty())
                    <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ยังไม่มีการสแกน</p>
                @else
                    <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        @foreach ($latestScans as $scan)
                            <li @class(['flex items-start gap-3 px-4 py-3 sm:px-5', 'bg-danger/5' => $scan->is_suspicious])>
                                @if ($scan->license_plate)
                                    <x-ui.plate :plate="$scan->license_plate" :province="$scan->plate_province" size="sm" />
                                @else
                                    <span class="inline-flex min-h-10 min-w-[6.5rem] items-center justify-center rounded-control border border-dashed border-field px-2 text-caption text-fg-2">อ่านไม่ได้</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-label font-semibold text-fg">{{ $scan->parkingLot?->name ?? '—' }}</p>
                                    <p class="truncate text-caption text-fg-3">
                                        {{ Format::short($scan->scan_time) }}
                                        @if ($scan->confidence !== null) · ความแม่นยำ <span class="tabular">{{ number_format((float) $scan->confidence, 0) }}%</span> @endif
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-2">
                                        <x-ui.status type="scan" :value="$scan->result" size="sm" />
                                        @if ($scan->is_suspicious)
                                            <span class="text-caption font-semibold text-danger">พบในบัญชีดำ</span>
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
