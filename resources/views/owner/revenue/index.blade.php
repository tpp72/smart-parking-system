{{--
    รายได้ของเจ้าของลาน = เงินที่รับจริง (มัดจำ + ค่าจอด) ตามเวลาที่ยืนยันรับเงิน (project-plan.md §16.1)
    ช่วงเวลา: วันนี้ / เดือนนี้ / ปีนี้ · กรองลาน · "รอยืนยันรับเงิน" เป็นยอด ณ ตอนนี้ ไม่ขึ้นกับช่วงเวลา
--}}
@use('App\Support\Format')

@php
    $periods = ['today' => 'วันนี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้'];
    $periodLabel = $periods[$period] ?? 'เดือนนี้';
    $scopeLabel = $lotId ? ($ownedLots->firstWhere('id', (int) $lotId)?->name ?? 'ลานที่เลือก') : 'ทุกลาน';
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6">
            <h1 class="text-h1 text-fg">รายได้</h1>
            <p class="mt-1 text-fg-2">เงินที่รับจริง (มัดจำ + ค่าจอด) นับตามวันที่ยืนยันรับเงิน · ลานของคุณ</p>
        </div>

        {{-- ── ช่วงเวลา + ลาน ─────────────────────────────────────────── --}}
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <input type="hidden" name="period" value="{{ $period }}">
            <nav aria-label="ช่วงเวลา" class="flex gap-1">
                @foreach ($periods as $val => $label)
                    <a href="{{ route('owner.revenue.index', array_filter(['period' => $val, 'lot_id' => $lotId])) }}" @if ($period === $val) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-touch items-center rounded-card px-3 text-label transition-colors duration-fast',
                            'bg-primary/10 font-semibold text-primary-ink' => $period === $val,
                            'text-fg-2 hover:bg-surface-2 hover:text-fg' => $period !== $val,
                        ])>{{ $label }}</a>
                @endforeach
            </nav>
            <x-ui.field label="ลานจอด" for="lot_id" class="min-w-48">
                <x-ui.select id="lot_id" name="lot_id" placeholder="ทุกลาน" onchange="this.form.requestSubmit()">
                    @foreach ($ownedLots as $lot)
                        <option value="{{ $lot->id }}" @selected((string) $lotId === (string) $lot->id)>{{ $lot->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <noscript><x-ui.button type="submit" variant="secondary">แสดง</x-ui.button></noscript>
            <p class="ml-auto text-label text-fg-3">
                {{ $scopeLabel }} · {{ Format::parse($from)->format('d/m/Y') }}–{{ Format::parse($to)->format('d/m/Y') }}
            </p>
        </form>

        {{-- ── ยอดในช่วงเวลา ─────────────────────────────────────────── --}}
        <section aria-labelledby="period-title" class="mt-6 grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 id="period-title" class="text-label font-semibold text-fg-2">รับเงินแล้ว{{ $periodLabel }}</h2>
                <p class="num mt-1 text-kpi text-fg">{{ Format::baht($revenueTotal) }}</p>
                <dl class="mt-4 flex flex-col gap-1.5 border-t border-dashed border-field pt-3 text-label">
                    <div class="flex justify-between gap-3"><dt class="text-fg-2">มัดจำ</dt><dd class="num text-fg">{{ Format::baht($depositRevenue) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-fg-2">ค่าจอด (หลัง Check-out)</dt><dd class="num text-fg">{{ Format::baht($parkingRevenue) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-line pt-2"><dt class="text-fg-2">จำนวนรายการที่รับเงิน</dt><dd class="tabular text-fg">{{ number_format($transactionCount) }} รายการ</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-fg-2">การจองที่สร้าง{{ $periodLabel }}</dt><dd class="tabular text-fg">{{ number_format($reservationCount) }} รายการ</dd></div>
                </dl>
            </div>

            <div class="rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 class="text-label font-semibold text-fg-2">รอยืนยันรับเงิน ณ ตอนนี้</h2>
                <p class="num mt-1 text-kpi text-fg">{{ Format::baht((float) $unpaidDeposits->amount + (float) $unpaidCheckouts->amount) }}</p>
                <dl class="mt-4 flex flex-col gap-1.5 border-t border-dashed border-field pt-3 text-label">
                    <div class="flex justify-between gap-3">
                        <dt class="text-fg-2">มัดจำ <span class="tabular">{{ $unpaidDeposits->count }}</span> รายการ</dt>
                        <dd class="num text-fg">{{ Format::baht($unpaidDeposits->amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-fg-2">ค่าจอด <span class="tabular">{{ $unpaidCheckouts->count }}</span> รายการ</dt>
                        <dd class="num text-fg">{{ Format::baht($unpaidCheckouts->amount) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-caption text-fg-3">ไม่ขึ้นกับช่วงเวลาที่เลือก · ยังไม่นับเป็นรายได้จนกว่าจะยืนยันรับเงิน</p>
                @if ($unpaidDeposits->count + $unpaidCheckouts->count > 0)
                    <x-ui.button variant="secondary" size="sm" class="mt-3" :href="route('owner.payments.index', ['status' => 'unpaid'])">ไปยืนยันรับเงิน</x-ui.button>
                @endif
            </div>
        </section>

        {{-- ── 12 เดือนล่าสุด ───────────────────────────────────────── --}}
        <section aria-labelledby="trend-title" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="trend-title" class="text-h3 text-fg">รายได้ 12 เดือนล่าสุด</h2>
                <p class="text-label text-fg-3">{{ $scopeLabel }} · เดือนนี้ <span class="num font-semibold text-fg">{{ Format::baht(end($revenueTrend)['value']) }}</span></p>
            </div>
            <x-ui.bar-chart :data="$revenueTrend" caption="รายได้รายเดือน 12 เดือนล่าสุด" money class="mt-5" />
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- ── แยกตามลาน ─────────────────────────────────────────── --}}
            <section aria-labelledby="bylot-title" class="rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 id="bylot-title" class="text-h3 text-fg">แยกตามลาน · {{ $periodLabel }}</h2>
                @if ($revenueByLot->isEmpty())
                    <p class="mt-4 text-fg-2">ยังไม่มีรายการรับเงินในช่วงนี้</p>
                @else
                    @php($maxLot = max(1, (float) $revenueByLot->max('revenue')))
                    <ol class="mt-4 flex flex-col gap-4">
                        @foreach ($revenueByLot as $item)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-label">
                                    <span class="min-w-0 truncate font-semibold text-fg">{{ $item->name }}</span>
                                    <span class="shrink-0 text-fg-2"><span class="num font-semibold text-fg">{{ Format::baht($item->revenue) }}</span> · <span class="tabular">{{ $item->transactions }}</span> รายการ</span>
                                </div>
                                <div class="mt-1.5 h-2 rounded-full bg-line"><div class="h-2 rounded-full bg-primary" style="width: {{ round((float) $item->revenue / $maxLot * 100, 1) }}%"></div></div>
                            </li>
                        @endforeach
                    </ol>
                    @if ($topStats)
                        <p class="mt-5 border-t border-line pt-3 text-label text-fg-2">
                            ลานที่มีการจองมากสุด{{ $periodLabel }}: <span class="font-semibold text-fg">{{ $topStats->name }}</span> (<span class="tabular">{{ $topStats->reservations }}</span> การจอง)
                        </p>
                    @endif
                @endif
            </section>

            {{-- ── รายวัน ─────────────────────────────────────────────── --}}
            <section aria-labelledby="byday-title" class="rounded-card border border-line bg-surface p-5 shadow-1">
                <h2 id="byday-title" class="text-h3 text-fg">รายวัน · {{ $periodLabel }}</h2>
                @if ($revenueByDay->isEmpty())
                    <p class="mt-4 text-fg-2">ยังไม่มีรายการรับเงินในช่วงนี้</p>
                @else
                    {{-- กล่องเลื่อนต้องโฟกัสได้ ไม่งั้นคีย์บอร์ดเลื่อนดูรายการที่เกินความสูงไม่ได้ (WCAG 2.1.1) --}}
                    <div class="mt-3 max-h-80 overflow-y-auto rounded-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-ink"
                        tabindex="0" role="region" aria-labelledby="byday-title">
                        <table class="w-full text-label">
                            <thead class="sticky top-0 bg-surface">
                                <tr class="border-b border-line text-caption text-fg-3">
                                    <th scope="col" class="py-2 text-start font-medium">วันที่</th>
                                    <th scope="col" class="py-2 text-end font-medium">รายการ</th>
                                    <th scope="col" class="py-2 text-end font-medium">รับเงิน</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($revenueByDay->sortByDesc('day') as $day)
                                    <tr>
                                        <td class="py-2 text-fg">{{ Format::parse($day->day)->translatedFormat('D d/m/Y') }}</td>
                                        <td class="tabular py-2 text-end text-fg-2">{{ $day->transactions }}</td>
                                        <td class="num py-2 text-end text-fg">{{ Format::baht($day->revenue) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
