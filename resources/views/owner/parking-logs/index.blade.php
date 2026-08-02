<x-app-layout>
    <div class="sp-bg min-h-screen text-white"
         x-data="{
             modalOpen: false,
             plate: '',
             hours: 0,
             fee: 0,
             actionUrl: '',
             openCheckoutModal(plate, hours, fee, url) {
                 this.plate = plate;
                 this.hours = hours;
                 this.fee = fee;
                 this.actionUrl = url;
                 this.modalOpen = true;
             }
         }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">ประวัติการจอด (Parking Logs)</h1>
                    <p class="text-gray-300 mt-1">ลานจอดของคุณ — ค้นหาตามทะเบียนรถ / กรองตามวันที่ — รถ Walk-in ที่ยังไม่เช็คเอาท์ เช็คเอาท์ได้จากที่นี่</p>
                </div>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif
            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mt-6 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            {{-- Filter --}}
            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียนรถ..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600 px-4 py-2" />

                    <input type="text" name="from" data-flatpickr="date" value="{{ $from }}" class="sp-select"
                        title="วันที่เข้า (เริ่มต้น)" placeholder="วันที่เริ่ม" />

                    <input type="text" name="to" data-flatpickr="date" value="{{ $to }}" class="sp-select"
                        title="วันที่เข้า (สิ้นสุด)" placeholder="วันที่สิ้นสุด" />

                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline flex-1" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline flex-1 text-center"
                            href="{{ route('owner.parking-logs.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">ผู้ใช้</th>
                            <th class="py-3 pr-4 text-left">รถ</th>
                            <th class="py-3 pr-4 text-left">ลาน / ช่อง</th>
                            <th class="py-3 pr-4 text-left">เวลาเข้า</th>
                            <th class="py-3 pr-4 text-left">เวลาออก</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $isActiveWalkIn = $log->reservation_id === null && $log->check_out_time === null;
                                $hoursElapsed = null;
                                $estimatedFee = null;
                                if ($isActiveWalkIn) {
                                    $minutes = (int) \Carbon\Carbon::parse($log->check_in_time)->diffInMinutes(now());
                                    $hoursElapsed = max(1, (int) ceil($minutes / 60));
                                    $estimatedFee = $hoursElapsed * (float) ($log->parkingLot->hourly_rate ?? 0);
                                }
                            @endphp
                            <tr class="border-b sp-divider hover:bg-white/5 transition">
                                <td class="py-3 pr-4 font-extrabold text-red-300">
                                    {{ $log->license_plate ?? '-' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $log->reservation?->user?->name ?? 'Walk-in' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $log->brand }}
                                    <span class="text-gray-500">{{ $log->color }}</span>
                                </td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $log->parkingLot?->name ?? '-' }}
                                    @if ($log->parkingSlot)
                                        <span class="text-gray-500">/ {{ $log->parkingSlot->slot_number }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($log->check_in_time)->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">
                                    @if ($log->check_out_time)
                                        {{ \Carbon\Carbon::parse($log->check_out_time)->format('d/m/Y H:i') }}
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-center">
                                    @if ($log->check_out_time)
                                        <span class="sp-badge sp-badge-ok">completed</span>
                                    @else
                                        <span class="sp-badge sp-badge-warn">active</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    @if($isActiveWalkIn)
                                        <button type="button"
                                            title="เช็คเอาท์รถ Walk-in คันนี้"
                                            class="sp-btn sp-btn-outline border-yellow-600/50 text-yellow-300 hover:bg-yellow-900/30 text-xs px-3 py-1"
                                            @click="openCheckoutModal(
                                                '{{ $log->license_plate }}',
                                                {{ $hoursElapsed }},
                                                {{ $estimatedFee }},
                                                '{{ route('owner.parking-logs.check-out', $log) }}'
                                            )">
                                            เช็คเอาท์
                                        </button>
                                    @else
                                        <span class="text-gray-600 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-gray-400">ไม่พบข้อมูล</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $logs->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>

        {{-- ── Check-Out Confirmation Modal ──────────────────────── --}}
        <div x-show="modalOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
             @keydown.escape.window="modalOpen = false">
            <div @click.outside="modalOpen = false"
                 class="sp-card rounded-2xl p-6 max-w-sm w-full border border-yellow-600/40">
                <h3 class="text-lg font-extrabold text-yellow-300 mb-1">ยืนยัน Check-Out</h3>
                <p class="text-sm text-gray-400 mb-4">ตรวจสอบรายละเอียดก่อนยืนยันเช็คเอาท์</p>

                <div class="rounded-xl bg-black/30 border border-white/10 p-4 space-y-2 text-sm mb-5">
                    <div class="flex justify-between">
                        <span class="text-gray-500">ทะเบียน</span>
                        <span class="font-extrabold text-red-300" x-text="plate"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">จอดมาแล้ว</span>
                        <span class="font-bold text-yellow-300" x-text="hours + ' ชม.'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">ค่าจอดโดยประมาณ</span>
                        <span class="font-bold text-white" x-text="'≈ ' + fee.toFixed(2) + ' บาท'"></span>
                    </div>
                    <p class="text-xs text-gray-600 pt-1">ยอดจริงจะคำนวณ ณ เวลาที่กดยืนยัน อาจต่างเล็กน้อย</p>
                </div>

                <div class="flex flex-col gap-2">
                    <form method="POST" :action="actionUrl">
                        @csrf
                        <button type="submit" class="sp-btn sp-btn-primary sp-glow-btn w-full justify-center whitespace-nowrap">
                            ✓ ยืนยันเช็คเอาท์
                        </button>
                    </form>
                    <button type="button" @click="modalOpen = false" class="sp-btn sp-btn-outline w-full justify-center">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
