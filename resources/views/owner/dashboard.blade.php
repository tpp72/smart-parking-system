<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            @if($ownerStatus === 'pending')
            {{-- ============================================================ --}}
            {{--  PENDING STATE                                                --}}
            {{-- ============================================================ --}}
            <div class="flex flex-col items-center justify-center min-h-[60vh] gap-6 text-center">
                <div class="w-20 h-20 rounded-full bg-yellow-500/20 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text mb-2">รอการอนุมัติ</h1>
                    <p class="text-gray-400 max-w-md">คำขอของคุณอยู่ระหว่างการพิจารณาจาก Admin<br>กรุณารอการแจ้งผล เราจะส่งการแจ้งเตือนให้คุณทราบ</p>
                </div>
                @if($application)
                <div class="sp-card rounded-2xl p-6 max-w-md w-full text-left space-y-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide font-semibold">รายละเอียดคำขอ</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <span class="text-gray-400">ชื่อธุรกิจ</span><span class="font-medium">{{ $application->business_name }}</span>
                        <span class="text-gray-400">ลานจอด</span><span class="font-medium">{{ $application->parking_lot_name }}</span>
                        <span class="text-gray-400">ส่งเมื่อ</span><span class="font-medium">{{ $application->created_at->diffForHumans() }}</span>
                        <span class="text-gray-400">สถานะ</span><span class="font-medium text-yellow-400">รอพิจารณา</span>
                    </div>
                </div>
                @endif
                <a href="{{ route('owner.application.show') }}" class="sp-btn sp-btn-outline">ดูรายละเอียดคำขอ</a>
            </div>

            @elseif($ownerStatus === 'rejected')
            {{-- ============================================================ --}}
            {{--  REJECTED STATE                                               --}}
            {{-- ============================================================ --}}
            <div class="flex flex-col items-center justify-center min-h-[60vh] gap-6 text-center">
                <div class="w-20 h-20 rounded-full bg-red-500/20 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-red-300 mb-2">คำขอไม่ได้รับการอนุมัติ</h1>
                    <p class="text-gray-400 max-w-md">คุณสามารถแก้ไขข้อมูลและส่งคำขอใหม่ได้</p>
                </div>
                @if($application && $application->rejection_reason)
                <div class="sp-card rounded-2xl p-6 max-w-md w-full text-left border border-red-500/30">
                    <p class="text-xs text-red-300 uppercase tracking-wide font-semibold mb-2">เหตุผลที่ไม่อนุมัติ</p>
                    <p class="text-sm text-gray-300">{{ $application->rejection_reason }}</p>
                </div>
                @endif
                <div class="flex gap-3">
                    <a href="{{ route('owner.application.edit') }}" class="sp-btn sp-btn-primary">แก้ไขและส่งใหม่</a>
                    <a href="{{ route('owner.application.show') }}" class="sp-btn sp-btn-outline">ดูรายละเอียด</a>
                </div>
            </div>

            @else
            {{-- ============================================================ --}}
            {{--  APPROVED — Full Dashboard                                    --}}
            {{-- ============================================================ --}}

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">Owner Dashboard</h1>
                    <p class="text-gray-400 text-sm mt-0.5">ภาพรวมลานจอดของคุณ — {{ now()->format('d M Y, H:i') }}</p>
                </div>
                <a href="{{ route('owner.parking-lots.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มลานจอด</a>
            </div>

            {{-- Quick Actions --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <a href="{{ route('owner.parking-lots.index') }}" class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                    <span class="text-sm font-semibold">ลานจอด</span>
                </a>
                <a href="{{ route('owner.parking-slots.index') }}" class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
                    <span class="text-sm font-semibold">ช่องจอด</span>
                </a>
                <a href="{{ route('owner.reservations.index') }}" class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="text-sm font-semibold">การจอง</span>
                    @if($stats['pending_reservations'] > 0)
                        <span class="text-xs bg-red-500/30 text-red-300 rounded-full px-1.5">{{ $stats['pending_reservations'] }}</span>
                    @endif
                </a>
                <a href="{{ route('owner.revenue.index') }}" class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm font-semibold">รายได้</span>
                </a>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">ลานจอดทั้งหมด</p>
                    <p class="text-3xl font-extrabold text-white">{{ $stats['lots_total'] }}</p>
                    <p class="text-xs text-gray-500">Parking Lots</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">จอดอยู่ตอนนี้</p>
                    <p class="text-3xl font-extrabold text-red-400">{{ $stats['active_now'] }}</p>
                    <p class="text-xs text-gray-500">Active / {{ $stats['slots_total'] }} ช่อง</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">รายได้วันนี้</p>
                    <p class="text-3xl font-extrabold text-green-400">{{ number_format($stats['revenue_today'], 0) }}</p>
                    <p class="text-xs text-gray-500">บาท</p>
                </div>
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-1">
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide">รายได้เดือนนี้</p>
                    <p class="text-3xl font-extrabold text-green-300">{{ number_format($stats['revenue_month'], 0) }}</p>
                    <p class="text-xs text-gray-500">บาท / {{ $stats['reservations_today'] }} จองวันนี้</p>
                </div>
            </div>

            {{-- Slot Status Bar --}}
            @if($stats['slots_total'] > 0)
            <div class="sp-card rounded-2xl p-5">
                <h2 class="text-sm font-bold text-gray-300 mb-3">สถานะช่องจอดรวม</h2>
                <div class="flex gap-4 text-sm mb-3">
                    <span class="text-green-400 font-bold">{{ $stats['slots_available'] }} ว่าง</span>
                    <span class="text-yellow-400 font-bold">{{ $stats['slots_reserved'] }} จอง</span>
                    <span class="text-red-400 font-bold">{{ $stats['slots_occupied'] }} ใช้งาน</span>
                    <span class="text-gray-400">{{ $stats['slots_total'] }} ทั้งหมด</span>
                </div>
                <div class="flex h-3 rounded-full overflow-hidden bg-white/5">
                    @php
                        $total = max($stats['slots_total'], 1);
                        $availPct = round($stats['slots_available'] / $total * 100);
                        $resPct   = round($stats['slots_reserved']  / $total * 100);
                        $occPct   = round($stats['slots_occupied']  / $total * 100);
                    @endphp
                    @if($availPct > 0)<div class="bg-green-500/70 transition-all" style="width:{{ $availPct }}%"></div>@endif
                    @if($resPct > 0)<div class="bg-yellow-500/70 transition-all" style="width:{{ $resPct }}%"></div>@endif
                    @if($occPct > 0)<div class="bg-red-500/70 transition-all" style="width:{{ $occPct }}%"></div>@endif
                </div>
            </div>
            @endif

            {{-- Lots Overview --}}
            @if($lotsOverview->isNotEmpty())
            <div class="sp-card rounded-2xl p-6">
                <h2 class="text-lg font-bold text-gray-200 mb-4">ภาพรวมรายลาน</h2>
                <div class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider text-xs text-gray-400 uppercase">
                                <th class="py-2 pr-4 text-left">ลาน</th>
                                <th class="py-2 pr-4 text-right">ว่าง</th>
                                <th class="py-2 pr-4 text-right">จอง</th>
                                <th class="py-2 pr-4 text-right">ใช้งาน</th>
                                <th class="py-2 pr-4 text-right">ทั้งหมด</th>
                                <th class="py-2 pr-4 text-right">เรท/ชม.</th>
                                <th class="py-2 pr-4 text-right">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lotsOverview as $lot)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-bold">
                                    <a href="{{ route('owner.parking-lots.edit', $lot->id) }}" class="hover:text-red-300 transition">{{ $lot->name }}</a>
                                </td>
                                <td class="py-3 pr-4 text-right text-green-400 font-bold">{{ $lot->available }}</td>
                                <td class="py-3 pr-4 text-right text-yellow-400">{{ $lot->reserved }}</td>
                                <td class="py-3 pr-4 text-right text-red-400">{{ $lot->occupied }}</td>
                                <td class="py-3 pr-4 text-right text-gray-300">{{ $lot->total_slots }}</td>
                                <td class="py-3 pr-4 text-right text-gray-300">{{ number_format((float)$lot->hourly_rate, 0) }}</td>
                                <td class="py-3 pr-4 text-right">
                                    @if($lot->is_active)
                                        <span class="sp-badge sp-badge-ok">เปิด</span>
                                    @else
                                        <span class="sp-badge sp-badge-danger">ปิด</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Active Now --}}
                <div class="sp-card rounded-2xl p-6">
                    <h2 class="text-lg font-bold text-gray-200 mb-4">รถที่จอดอยู่ตอนนี้</h2>
                    @if($activeNow->isEmpty())
                        <p class="text-gray-500 text-sm">ไม่มีรถจอดอยู่ในขณะนี้</p>
                    @else
                        <div class="space-y-2">
                            @foreach($activeNow as $log)
                            <div class="flex items-center justify-between text-sm py-2 border-b sp-divider last:border-0">
                                <span class="font-bold text-red-300">{{ $log->license_plate }}</span>
                                <span class="text-gray-400">{{ $log->lot_name }} {{ $log->slot_number ? '· '.$log->slot_number : '' }}</span>
                                <span class="text-gray-500 text-xs">{{ \Carbon\Carbon::parse($log->check_in_time)->format('H:i') }}</span>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Recent Reservations --}}
                <div class="sp-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-200">การจองล่าสุด</h2>
                        <a href="{{ route('owner.reservations.index') }}" class="text-xs text-red-400 hover:text-red-300">ดูทั้งหมด →</a>
                    </div>
                    @if($recentReservations->isEmpty())
                        <p class="text-gray-500 text-sm">ยังไม่มีการจอง</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentReservations as $r)
                            <div class="flex items-center justify-between text-sm py-2 border-b sp-divider last:border-0">
                                <div>
                                    <span class="font-bold text-red-300">{{ $r->license_plate }}</span>
                                    <span class="text-gray-400 ml-2 text-xs">{{ $r->lot_name }}</span>
                                </div>
                                <div class="text-right">
                                    @php
                                        $badgeClass = match($r->status) {
                                            'confirmed'  => 'sp-badge-ok',
                                            'checked_in' => 'sp-badge-ok',
                                            'completed'  => 'sp-badge-ok',
                                            'pending'    => 'sp-badge-warn',
                                            default      => 'sp-badge-danger',
                                        };
                                    @endphp
                                    <span class="sp-badge {{ $badgeClass }}">{{ $r->status }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @endif {{-- end approved state --}}

        </div>
    </div>
</x-app-layout>
