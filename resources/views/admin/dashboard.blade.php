<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">

                {{-- Quick actions (เฉพาะที่มี route จริง) --}}
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.users.index') }}" class="sp-btn sp-btn-outline">ผู้ใช้งาน</a>
                    <a href="{{ route('admin.parking-lots.index') }}" class="sp-btn sp-btn-outline">จัดการลานจอด</a>
                    <a href="{{ route('admin.parking-slots.index') }}" class="sp-btn sp-btn-outline">จัดการช่องจอด</a>
                    <a href="{{ route('admin.devices.index') }}" class="sp-btn sp-btn-outline">จัดการอุปกรณ์</a>
                    <a href="{{ route('admin.reservations.index') }}" class="sp-btn sp-btn-outline">จัดการการจอง</a>

                </div>
            </div>

            {{-- KPI --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-5 mt-8">
                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ลานจอดทั้งหมด</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['lots_total'] ?? 0 }}</p>
                </div>

                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ช่องทั้งหมด</p>
                    <p class="text-3xl font-extrabold mt-2">{{ $stats['slots_total'] ?? 0 }}</p>
                </div>

                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ว่าง</p>
                    <div class="flex items-end justify-between mt-2">
                        <p class="text-3xl font-extrabold">{{ $stats['slots_available'] ?? 0 }}</p>
                        <span class="sp-badge sp-badge-ok">available</span>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">จอง</p>
                    <div class="flex items-end justify-between mt-2">
                        <p class="text-3xl font-extrabold">{{ $stats['slots_reserved'] ?? 0 }}</p>
                        <span class="sp-badge sp-badge-warn">reserved</span>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">ไม่ว่าง</p>
                    <div class="flex items-end justify-between mt-2">
                        <p class="text-3xl font-extrabold">{{ $stats['slots_occupied'] ?? 0 }}</p>
                        <span class="sp-badge sp-badge-bad">occupied</span>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-5">
                    <p class="text-gray-300 text-sm">กำลังจอดอยู่ (Active)</p>
                    <div class="flex items-end justify-between mt-2">
                        <p class="text-3xl font-extrabold">{{ $stats['active_now'] ?? 0 }}</p>
                        <span class="sp-badge sp-badge-warn">active</span>
                    </div>
                </div>
            </div>

            {{-- Action Center --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">

                {{-- Unpaid payments --}}
                <div class="sp-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-extrabold">ค้างชำระล่าสุด</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full sp-table">
                            <thead>
                                <tr class="border-b sp-divider">
                                    <th class="py-3 pr-4">ทะเบียน</th>
                                    <th class="py-3 pr-4">ชั่วโมง</th>
                                    <th class="py-3 pr-4">รวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($unpaidPayments as $p)
                                    <tr class="border-b sp-divider">
                                        <td class="py-3 pr-4 font-bold">{{ $p->license_plate }}</td>
                                        <td class="py-3 pr-4 text-gray-300">{{ $p->total_hours }}</td>
                                        <td class="py-3 pr-4 font-bold text-red-200">
                                            {{ number_format((float) $p->total_amount, 2) }} ฿
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-gray-300">ไม่มีรายการค้างชำระ
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Recent penalties --}}
                <div class="sp-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-extrabold">ค่าปรับล่าสุด</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full sp-table">
                            <thead>
                                <tr class="border-b sp-divider">
                                    <th class="py-3 pr-4">ทะเบียน</th>
                                    <th class="py-3 pr-4">เหตุผล</th>
                                    <th class="py-3 pr-4">จำนวน</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPenalties as $x)
                                    <tr class="border-b sp-divider">
                                        <td class="py-3 pr-4 font-bold">{{ $x->license_plate }}</td>
                                        <td class="py-3 pr-4 text-gray-300">{{ $x->reason }}</td>
                                        <td class="py-3 pr-4 font-bold text-red-200">
                                            {{ number_format((float) $x->amount, 2) }} ฿</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-gray-300">ไม่มีค่าปรับล่าสุด
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Latest scans --}}
                <div class="sp-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-extrabold">สแกนป้ายทะเบียนล่าสุด</h2>
                    </div>

                    <div class="space-y-3">
                        @forelse($latestScans as $s)
                            <div class="rounded-xl border sp-divider p-3">
                                <div class="flex items-center justify-between">
                                    <p class="font-extrabold">{{ $s->license_plate }}</p>
                                    <span class="text-xs px-2 py-1 rounded-full border sp-divider text-gray-200">
                                        {{ $s->device_type }}
                                    </span>
                                </div>
                                <p class="text-gray-300 text-xs mt-2">{{ $s->location }} • {{ $s->scan_time }}</p>
                            </div>
                        @empty
                            <p class="text-gray-300 text-sm">ยังไม่มีข้อมูลการสแกน</p>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- Workspace (Tabs) --}}
            <div x-data="{ tab: 'active' }" class="sp-card rounded-2xl p-6 mt-8">
                <div class="flex flex-wrap gap-2 mb-4">
                    <button @click="tab='active'"
                        :class="tab === 'active' ? 'sp-btn sp-btn-primary' : 'sp-btn sp-btn-outline'">Live
                        Parking</button>
                    <button @click="tab='reserve'"
                        :class="tab === 'reserve' ? 'sp-btn sp-btn-primary' : 'sp-btn sp-btn-outline'">Reservations</button>
                    <button @click="tab='history'"
                        :class="tab === 'history' ? 'sp-btn sp-btn-primary' : 'sp-btn sp-btn-outline'">Recent
                        History</button>
                    <button @click="tab='slots'"
                        :class="tab === 'slots' ? 'sp-btn sp-btn-primary' : 'sp-btn sp-btn-outline'">Slots
                        Preview</button>
                </div>

                {{-- Active Now --}}
                <div x-show="tab==='active'" class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4">ทะเบียน</th>
                                <th class="py-3 pr-4">ผู้ใช้</th>
                                <th class="py-3 pr-4">ลาน</th>
                                <th class="py-3 pr-4">ช่อง</th>
                                <th class="py-3 pr-4">เวลาเข้า</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeNow as $row)
                                <tr class="border-b sp-divider">
                                    <td class="py-3 pr-4 font-bold">{{ $row->license_plate }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $row->user_name ?? '-' }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $row->lot_name }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $row->slot_number ?? '-' }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $row->check_in_time }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-300">
                                        ยังไม่มีรายการที่กำลังจอด</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Reservations --}}
                <div x-show="tab==='reserve'" class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4">ทะเบียน</th>
                                <th class="py-3 pr-4">ผู้ใช้</th>
                                <th class="py-3 pr-4">ลาน</th>
                                <th class="py-3 pr-4">ช่วงเวลา</th>
                                <th class="py-3 pr-4">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reservations as $r)
                                <tr class="border-b sp-divider">
                                    <td class="py-3 pr-4 font-bold">{{ $r->license_plate }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $r->user_name ?? '-' }}</td>
                                    <td class="py-3 pr-4">{{ $r->lot_name }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $r->reserve_start }} →
                                        {{ $r->reserve_end }}</td>
                                    <td class="py-3 pr-4"><span
                                            class="sp-badge sp-badge-warn">{{ $r->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-300">ยังไม่มีการจอง</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- History --}}
                <div x-show="tab==='history'" class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4">ทะเบียน</th>
                                <th class="py-3 pr-4">ผู้ใช้</th>
                                <th class="py-3 pr-4">ลาน</th>
                                <th class="py-3 pr-4">เข้า</th>
                                <th class="py-3 pr-4">ออก</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentHistory as $h)
                                <tr class="border-b sp-divider">
                                    <td class="py-3 pr-4 font-bold">{{ $h->license_plate }}</td>
                                    <td class="py-3 pr-4 text-gray-200">{{ $h->user_name ?? '-' }}</td>
                                    <td class="py-3 pr-4">{{ $h->lot_name }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $h->check_in_time }}</td>
                                    <td class="py-3 pr-4 text-gray-300">{{ $h->check_out_time ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-300">ยังไม่มีประวัติ</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Slots preview --}}
                <div x-show="tab==='slots'" class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4">ลาน</th>
                                <th class="py-3 pr-4">ช่อง</th>
                                <th class="py-3 pr-4">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($slotsPreview as $s)
                                <tr class="border-b sp-divider">
                                    <td class="py-3 pr-4 text-gray-200">{{ $s->lot_name }}</td>
                                    <td class="py-3 pr-4 font-bold">{{ $s->slot_number }}</td>
                                    <td class="py-3 pr-4">
                                        @if ($s->status === 'available')
                                            <span class="sp-badge sp-badge-ok">available</span>
                                        @elseif($s->status === 'reserved')
                                            <span class="sp-badge sp-badge-warn">reserved</span>
                                        @else
                                            <span class="sp-badge sp-badge-bad">occupied</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-gray-300">ยังไม่มีข้อมูล</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>

            {{-- Lots Overview --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-extrabold">ภาพรวมลานจอด</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($lotsOverview as $lot)
                        <div class="rounded-xl border sp-divider p-4">
                            <div class="flex items-center justify-between">
                                <p class="font-extrabold">{{ $lot->name }}</p>
                                <span class="text-xs px-2 py-1 rounded-full border sp-divider text-gray-200">
                                    Rate: {{ number_format((float) $lot->hourly_rate, 2) }} ฿/hr
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-300">
                                <div>ทั้งหมด: <span class="text-white font-bold">{{ $lot->total_slots }}</span></div>
                                <div>ว่าง: <span class="text-green-200 font-bold">{{ $lot->available }}</span></div>
                                <div>ไม่ว่าง: <span class="text-red-200 font-bold">{{ $lot->occupied }}</span></div>
                            </div>

                            <div class="mt-2 text-xs text-gray-400">
                                จองแล้ว: <span class="text-white font-bold">{{ $lot->reserved }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-300 text-sm">ยังไม่มีข้อมูลลานจอด</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
