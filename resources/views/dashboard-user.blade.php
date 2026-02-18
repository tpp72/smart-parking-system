<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">Dashboard</h1>
                    <p class="text-gray-300 mt-1">
                        สวัสดี, <span class="font-semibold text-white">{{ auth()->user()->name }}</span>
                    </p>
                    <p class="text-gray-400 text-sm mt-1">
                        จองง่าย • ดูสถานะชัด • จ่ายไว
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="/reservations/create" class="sp-btn sp-btn-primary sp-glow-btn">จองที่จอด</a>
                    <a href="/vehicles" class="sp-btn sp-btn-outline">รถของฉัน</a>
                    <a href="/history" class="sp-btn sp-btn-outline">ประวัติ</a>
                </div>
            </div>

            {{-- Top Status Card (สำคัญสุด) --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                @if ($activeLog)
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-300">สถานะตอนนี้</p>
                            <h2 class="text-2xl font-extrabold mt-1">กำลังจอดอยู่</h2>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ทะเบียน</p>
                                    <p class="font-extrabold">{{ $activeLog->license_plate }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ลานจอด</p>
                                    <p class="font-extrabold">{{ $activeLog->lot_name }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ช่อง</p>
                                    <p class="font-extrabold">{{ $activeLog->slot_number ?? '-' }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">เวลาเข้า</p>
                                    <p class="font-extrabold">{{ $activeLog->check_in_time }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center gap-2 text-sm">
                                <span class="sp-badge sp-badge-warn">active</span>
                                @if (($activeLog->payment_status ?? null) === 'unpaid')
                                    <span class="sp-badge sp-badge-bad">ยังไม่ชำระ</span>
                                @elseif(($activeLog->payment_status ?? null) === 'paid')
                                    <span class="sp-badge sp-badge-ok">ชำระแล้ว</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 min-w-[220px]">
                            <a href="/parking/logs/{{ $activeLog->log_id }}" class="sp-btn sp-btn-outline text-center">
                                ดูรายละเอียดการจอด
                            </a>

                            @if (($activeLog->payment_status ?? null) === 'unpaid' && !empty($activeLog->payment_id))
                                <a href="/admin/payments/{{ $activeLog->payment_id }}"
                                    class="sp-btn sp-btn-primary sp-glow-btn text-center">
                                    ชำระเงิน ({{ number_format((float) ($activeLog->total_amount ?? 0), 2) }} ฿)
                                </a>
                            @else
                                <a href="/payments" class="sp-btn sp-btn-primary sp-glow-btn text-center">
                                    ไปหน้า Payments
                                </a>
                            @endif
                        </div>
                    </div>
                @elseif($activeReservation)
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-300">สถานะตอนนี้</p>
                            <h2 class="text-2xl font-extrabold mt-1">คุณมีการจอง</h2>

                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ทะเบียน</p>
                                    <p class="font-extrabold">{{ $activeReservation->license_plate }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ลานจอด</p>
                                    <p class="font-extrabold">{{ $activeReservation->lot_name }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ช่อง</p>
                                    <p class="font-extrabold">{{ $activeReservation->slot_number ?? '-' }}</p>
                                </div>
                                <div class="rounded-xl border sp-divider p-3">
                                    <p class="text-gray-300 text-xs">ช่วงเวลา</p>
                                    <p class="font-extrabold">
                                        {{ $activeReservation->reserve_start }} →
                                        {{ $activeReservation->reserve_end }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center gap-2 text-sm">
                                <span class="sp-badge sp-badge-warn">{{ $activeReservation->status }}</span>
                                <span class="text-gray-300">ค่าจอง: <span
                                        class="text-white font-bold">{{ number_format((float) $activeReservation->reservation_fee, 2) }}
                                        ฿</span></span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 min-w-[220px]">
                            <a href="/reservations/{{ $activeReservation->reservation_id }}"
                                class="sp-btn sp-btn-primary sp-glow-btn text-center">
                                ดูรายละเอียดการจอง
                            </a>
                            <a href="/reservations/{{ $activeReservation->reservation_id }}/cancel"
                                class="sp-btn sp-btn-outline text-center">
                                ยกเลิกการจอง
                            </a>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-300">สถานะตอนนี้</p>
                            <h2 class="text-2xl font-extrabold mt-1">ยังไม่มีการจองหรือการจอด</h2>
                            <p class="text-gray-300 mt-2 text-sm">กดปุ่มด้านขวาเพื่อเริ่มจองที่จอดได้ทันที</p>
                        </div>

                        <div class="flex flex-col gap-3 min-w-[220px]">
                            <a href="/reservations/create" class="sp-btn sp-btn-primary sp-glow-btn text-center">
                                จองที่จอดตอนนี้
                            </a>
                            <a href="/parking-lots" class="sp-btn sp-btn-outline text-center">
                                ดูลานจอดทั้งหมด
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Quick stats (ลดให้เหลือเท่าที่ user เข้าใจ) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-6">
                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ช่องว่างตอนนี้</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['slots_available'] ?? 0 }}</p>
                </div>
                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">จองอยู่</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['slots_reserved'] ?? 0 }}</p>
                </div>
                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ไม่ว่าง</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['slots_occupied'] ?? 0 }}</p>
                </div>
                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">รถของฉัน</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['my_vehicles'] ?? 0 }}</p>
                </div>
            </div>

            {{-- Lots available (แนะนำให้ user เลือกง่าย) --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-extrabold">ลานที่ว่างแนะนำ</h2>
                    <a href="/parking-lots" class="text-red-300 hover:text-red-200 text-sm font-bold">ดูทั้งหมด →</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($lotsAvailable as $lot)
                        <a href="/reservations/create?lot_id={{ $lot->id }}"
                            class="rounded-xl border sp-divider p-4 hover:opacity-95">
                            <div class="flex items-center justify-between">
                                <p class="font-extrabold">{{ $lot->name }}</p>
                                <span class="sp-badge sp-badge-ok">{{ (int) $lot->available }} ว่าง</span>
                            </div>
                            <p class="text-gray-300 text-xs mt-2">แตะเพื่อจองลานนี้</p>
                        </a>
                    @empty
                        <p class="text-gray-300 text-sm">ยังไม่มีข้อมูลลานจอด</p>
                    @endforelse
                </div>
            </div>

            {{-- Recent history (สั้นๆ) --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-extrabold">ประวัติล่าสุด</h2>
                    <a href="/history" class="text-red-300 hover:text-red-200 text-sm font-bold">ดูทั้งหมด →</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4">ทะเบียน</th>
                                <th class="py-3 pr-4">ลาน</th>
                                <th class="py-3 pr-4">เข้า</th>
                                <th class="py-3 pr-4">ออก</th>
                                <th class="py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentHistory as $h)
                                <tr class="border-b sp-divider">
                                    <td class="py-3 pr-4 font-bold">{{ $h->license_plate }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $h->lot_name }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $h->check_in_time }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $h->check_out_time ?? '-' }}</td>
                                    <td class="py-3 text-right">
                                        <a href="/parking/logs/{{ $h->log_id }}"
                                            class="sp-btn sp-btn-outline px-3 py-1.5 text-sm">
                                            ดู
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-300">ยังไม่มีประวัติ</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
