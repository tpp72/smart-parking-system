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
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการการจอง (Reservations)</h1>
                    <p class="text-gray-300 mt-1">ค้นหา / กรอง / ยืนยัน / เช็คอิน-เช็คเอาท์ / แก้ไขสถานะ / ลบ</p>
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

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหา ทะเบียน/ชื่อ/อีเมล..."
                        class="md:col-span-2 w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="lot_id" class="sp-select">
                        <option value="">ทุกลาน</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string) $lotId === (string) $lot->id)>{{ $lot->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        @foreach (['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'] as $st)
                            <option value="{{ $st }}" @selected($status === $st)>{{ $st }}</option>
                        @endforeach
                    </select>

                    <input type="text" name="from" data-flatpickr="date" value="{{ $from }}" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" value="{{ $to }}" class="sp-select" placeholder="วันที่สิ้นสุด" />

                    <div class="flex gap-2 md:col-span-6">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.reservations.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl mt-6 overflow-hidden">
                <div class="overflow-x-auto p-6">
                <table class="w-full sp-table min-w-[640px]">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">ผู้ใช้</th>
                            <th class="py-3 pr-4 text-left">ลาน</th>
                            <th class="py-3 pr-4 text-left">ช่อง</th>
                            <th class="py-3 pr-4 text-left">เวลาเริ่ม</th>
                            <th class="py-3 pr-4 text-left">ค่าจอง</th>
                            <th class="py-3 pr-4 text-left">สถานะ</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $r)
                            @php
                                $isCheckable = in_array($r->id, $checkableIds, true);

                                $hoursElapsed = null;
                                $estimatedFee = null;
                                if ($r->status === 'checked_in' && $r->parkingLog) {
                                    $minutes = (int) \Carbon\Carbon::parse($r->parkingLog->check_in_time)->diffInMinutes(now());
                                    $hoursElapsed = max(1, (int) ceil($minutes / 60));
                                    $estimatedFee = $hoursElapsed * (float) ($r->parkingLot->hourly_rate ?? 0);
                                }
                            @endphp
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-extrabold tracking-wider">
                                    {{ $r->license_plate ?? $r->vehicle?->license_plate ?? '-' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->user?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->parkingLot?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->parkingSlot?->slot_number ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $r->reserve_start }}
                                </td>
                                <td class="py-3 pr-4 font-bold text-red-200">
                                    {{ number_format((float) $r->reservation_fee, 2) }}
                                </td>
                                <td class="py-3 pr-4">
                                    @php
                                        $badge = match ($r->status) {
                                            'confirmed'  => 'sp-badge sp-badge-ok',
                                            'checked_in' => 'sp-badge sp-badge-ok',
                                            'completed'  => 'sp-badge sp-badge-ok',
                                            'pending'    => 'sp-badge sp-badge-warn',
                                            'cancelled'  => 'sp-badge sp-badge-bad',
                                            'expired'    => 'sp-badge sp-badge-bad',
                                            default      => 'sp-badge sp-badge-warn',
                                        };
                                    @endphp

                                    <span class="{{ $badge }}">
                                        {{ $r->status }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2 flex-wrap">
                                        @if($r->status === 'pending')
                                            <form method="POST" action="{{ route('admin.reservations.confirm', $r) }}">
                                                @csrf
                                                <button type="submit"
                                                    title="ยืนยันและอนุมัติการจองนี้"
                                                    class="sp-btn sp-btn-outline border-green-600/50 text-green-300 hover:bg-green-900/30">
                                                    ✓ ยืนยัน
                                                </button>
                                            </form>
                                        @endif
                                        @if($isCheckable)
                                            <form method="POST" action="{{ route('admin.reservations.check-in', $r) }}">
                                                @csrf
                                                <button type="submit"
                                                    title="เช็คอินรถของการจองนี้"
                                                    class="sp-btn sp-btn-outline border-sky-600/50 text-sky-300 hover:bg-sky-900/30">
                                                    เช็คอิน
                                                </button>
                                            </form>
                                        @endif
                                        @if($r->status === 'checked_in' && $r->parkingLog)
                                            <button type="button"
                                                title="เช็คเอาท์รถของการจองนี้"
                                                class="sp-btn sp-btn-outline border-yellow-600/50 text-yellow-300 hover:bg-yellow-900/30"
                                                @click="openCheckoutModal(
                                                    '{{ $r->license_plate ?? $r->vehicle?->license_plate ?? '-' }}',
                                                    {{ $hoursElapsed }},
                                                    {{ $estimatedFee }},
                                                    '{{ route('admin.reservations.check-out', $r) }}'
                                                )">
                                                เช็คเอาท์
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.reservations.edit', $r) }}"
                                            class="sp-btn sp-btn-outline">แก้ไข</a>
                                        <form method="POST" action="{{ route('admin.reservations.destroy', $r) }}"
                                            onsubmit="return confirm('ยืนยันลบ reservation นี้? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="ลบ Reservation นี้ (ถาวร)" class="sp-btn sp-btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-gray-300">ยังไม่มีรายการจอง</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                </div>{{-- /overflow-x-auto --}}
                <div class="px-6 py-4">
                    {{ $reservations->links('vendor.pagination.sp') }}
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
