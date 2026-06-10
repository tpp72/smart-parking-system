<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">รายได้ & สถิติ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Revenue Dashboard — ลานจอดของคุณ</p>
                </div>
            </div>

            {{-- Period Filter --}}
            <div class="sp-card rounded-2xl p-4">
                <form method="GET" class="flex flex-wrap gap-3 items-center">
                    <div class="flex gap-1">
                        @foreach(['today' => 'วันนี้', 'month' => 'เดือนนี้', 'year' => 'ปีนี้'] as $val => $label)
                            <a href="{{ request()->fullUrlWithQuery(['period' => $val]) }}"
                               class="px-4 py-2 rounded-xl text-sm font-semibold transition
                                      {{ $period === $val ? 'bg-red-600/30 text-red-200 ring-1 ring-red-700' : 'text-gray-400 hover:text-white hover:bg-white/[0.06]' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                    <select name="lot_id" class="sp-select" onchange="this.form.submit()">
                        <option value="">ทุกลาน</option>
                        @foreach($ownedLots as $lot)
                            <option value="{{ $lot->id }}" @selected((string)$lotId === (string)$lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="period" value="{{ $period }}" />
                </form>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide">รายได้รวม</p>
                    <p class="text-3xl font-extrabold text-green-400">{{ number_format($revenueTotal, 0) }}</p>
                    <p class="text-xs text-gray-500">บาท (ชำระแล้ว)</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide">ค้างชำระ</p>
                    <p class="text-3xl font-extrabold text-yellow-400">{{ number_format($unpaidTotal, 0) }}</p>
                    <p class="text-xs text-gray-500">บาท</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide">จำนวนธุรกรรม</p>
                    <p class="text-3xl font-extrabold text-white">{{ number_format($transactionCount) }}</p>
                    <p class="text-xs text-gray-500">รายการ</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 uppercase font-semibold tracking-wide">% การใช้งาน</p>
                    <p class="text-3xl font-extrabold text-red-300">{{ number_format($occupancyRate, 1) }}%</p>
                    <p class="text-xs text-gray-500">Occupancy (ปัจจุบัน)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Revenue by Lot --}}
                <div class="sp-card rounded-2xl p-6">
                    <h2 class="text-lg font-bold text-gray-200 mb-4">รายได้แยกตามลาน</h2>
                    @if($revenueByLot->isEmpty())
                        <p class="text-gray-500 text-sm">ยังไม่มีข้อมูล</p>
                    @else
                        <div class="space-y-3">
                            @foreach($revenueByLot as $item)
                            <div class="flex items-center justify-between text-sm">
                                <div>
                                    <span class="font-bold text-white">{{ $item->name }}</span>
                                    <span class="text-gray-500 ml-2 text-xs">{{ $item->transactions }} รายการ</span>
                                </div>
                                <span class="font-bold text-green-400">฿{{ number_format($item->revenue, 0) }}</span>
                            </div>
                            @php
                                $maxRev = $revenueByLot->max('revenue') ?: 1;
                                $pct = round($item->revenue / $maxRev * 100);
                            @endphp
                            <div class="h-1.5 rounded-full bg-white/5">
                                <div class="h-1.5 rounded-full bg-green-500/60 transition-all" style="width:{{ $pct }}%"></div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Revenue by Day --}}
                <div class="sp-card rounded-2xl p-6">
                    <h2 class="text-lg font-bold text-gray-200 mb-4">รายได้รายวัน</h2>
                    @if($revenueByDay->isEmpty())
                        <p class="text-gray-500 text-sm">ยังไม่มีข้อมูล</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full sp-table text-sm">
                                <thead>
                                    <tr class="border-b sp-divider text-gray-400 text-xs">
                                        <th class="py-2 pr-4 text-left">วันที่</th>
                                        <th class="py-2 pr-4 text-right">รายได้ (฿)</th>
                                        <th class="py-2 pr-4 text-right">ธุรกรรม</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($revenueByDay as $day)
                                    <tr class="border-b sp-divider">
                                        <td class="py-2 pr-4 text-gray-300">{{ \Carbon\Carbon::parse($day->day)->format('d/m/Y') }}</td>
                                        <td class="py-2 pr-4 text-right text-green-400 font-bold">{{ number_format($day->revenue, 0) }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-400">{{ $day->transactions }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Top Stats --}}
            @if($topStats)
            <div class="sp-card rounded-2xl p-6">
                <h2 class="text-lg font-bold text-gray-200 mb-4">สถิติสูงสุด</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400 mb-1">ลานยอดนิยม</p>
                        <p class="font-bold text-white">{{ $topStats->name }}</p>
                        <p class="text-gray-500 text-xs">{{ $topStats->reservations }} การจอง</p>
                    </div>
                    <div>
                        <p class="text-gray-400 mb-1">การจองช่วงนี้</p>
                        <p class="font-bold text-white">{{ number_format($reservationCount) }}</p>
                        <p class="text-gray-500 text-xs">รายการ</p>
                    </div>
                    <div>
                        <p class="text-gray-400 mb-1">ช่วงเวลา</p>
                        <p class="font-bold text-white">{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</p>
                        <p class="text-gray-500 text-xs">ถึง {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
