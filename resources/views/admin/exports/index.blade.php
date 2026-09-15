<x-app-layout>
    @php
        $inputClass = 'w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600';
    @endphp

    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <div>
                <h1 class="text-3xl font-extrabold sp-glow-text">Export CSV</h1>
                <p class="text-gray-300 mt-1">
                    เลือกประเภทข้อมูลและตัวกรอง แล้วดาวน์โหลดเป็นไฟล์ CSV — ข้อมูลครอบคลุมทั้งระบบ ทุกลาน (เลือกกรองลานได้)
                </p>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            @php
                $lotSelect = function () use ($lots) {
                    $options = '<option value="">ทุกลาน</option>';
                    foreach ($lots as $lot) {
                        $owner = $lot->owner ? 'Owner: ' . $lot->owner->name : 'ลานของ Admin';
                        $options .= '<option value="' . $lot->id . '">' . e($lot->name) . ' (' . e($owner) . ')</option>';
                    }
                    return $options;
                };
            @endphp

            {{-- ประวัติการจอง --}}
            <form method="GET" action="{{ route('admin.exports.reservations') }}" class="sp-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-extrabold">ประวัติการจอง (Reservation)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">การจองทุกรายการรวม Walk-in — ช่วงวันที่นับจากเวลาเริ่มจอง</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <input name="q" placeholder="ทะเบียน / ชื่อ / อีเมล" class="{{ $inputClass }}" />
                    <select name="lot_id" class="sp-select">{!! $lotSelect() !!}</select>
                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}">{{ $st }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="from" data-flatpickr="date" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" class="sp-select" placeholder="วันที่สิ้นสุด" />
                </div>
                <div class="flex justify-end"><button type="submit" class="sp-btn sp-btn-primary">ดาวน์โหลด CSV</button></div>
            </form>

            {{-- ประวัติการจอด --}}
            <form method="GET" action="{{ route('admin.exports.parking-logs') }}" class="sp-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-extrabold">ประวัติการจอด (Parking Log)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">เวลาเข้า-ออก อัตราค่าจอด และยอดชำระหลัง Check-out — ช่วงวันที่นับจากเวลาเข้า</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <input name="q" placeholder="ทะเบียนรถ" class="{{ $inputClass }}" />
                    <select name="lot_id" class="sp-select">{!! $lotSelect() !!}</select>
                    <select name="state" class="sp-select">
                        <option value="">ทั้งหมด</option>
                        <option value="parked">กำลังจอด</option>
                        <option value="completed">เช็คเอาท์แล้ว</option>
                    </select>
                    <input type="text" name="from" data-flatpickr="date" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" class="sp-select" placeholder="วันที่สิ้นสุด" />
                </div>
                <div class="flex justify-end"><button type="submit" class="sp-btn sp-btn-primary">ดาวน์โหลด CSV</button></div>
            </form>

            {{-- รายงานรายได้ --}}
            <form method="GET" action="{{ route('admin.exports.revenue') }}" class="sp-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-extrabold">รายงานรายได้รายวัน (Revenue)</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        1 แถว = 1 วัน × 1 ลาน · เงินที่รับจริง แยกมัดจำ / ค่าจอด นับตามวันที่ยืนยันรับเงิน — ไม่ระบุช่วงวัน = เดือนนี้
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <select name="lot_id" class="sp-select">{!! $lotSelect() !!}</select>
                    <input type="text" name="from" data-flatpickr="date" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" class="sp-select" placeholder="วันที่สิ้นสุด" />
                </div>
                <div class="flex justify-end"><button type="submit" class="sp-btn sp-btn-primary">ดาวน์โหลด CSV</button></div>
            </form>

            {{-- Reservation Log --}}
            <form method="GET" action="{{ route('admin.reservation-logs.export') }}" class="sp-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-extrabold">Reservation Log</h2>
                    <p class="text-xs text-gray-400 mt-0.5">ประวัติการเปลี่ยนสถานะการจอง รวมรายการที่ระบบดำเนินการ</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <select name="lot_id" class="sp-select">{!! $lotSelect() !!}</select>
                    <input type="text" name="from" data-flatpickr="date" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" class="sp-select" placeholder="วันที่สิ้นสุด" />
                </div>
                <div class="flex justify-end"><button type="submit" class="sp-btn sp-btn-primary">ดาวน์โหลด CSV</button></div>
            </form>

            {{-- Audit Log --}}
            <form method="GET" action="{{ route('admin.admin-actions.export') }}" class="sp-card rounded-2xl p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-extrabold">Audit Log</h2>
                    <p class="text-xs text-gray-400 mt-0.5">การกระทำของทุก Role และเหตุการณ์ที่ระบบดำเนินการ</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <select name="actor_role" class="sp-select">
                        <option value="">ทุก Role</option>
                        @foreach (['user', 'owner', 'admin', 'system'] as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="from" data-flatpickr="date" class="sp-select" placeholder="วันที่เริ่ม" />
                    <input type="text" name="to" data-flatpickr="date" class="sp-select" placeholder="วันที่สิ้นสุด" />
                </div>
                <div class="flex justify-end"><button type="submit" class="sp-btn sp-btn-primary">ดาวน์โหลด CSV</button></div>
            </form>

        </div>
    </div>
</x-app-layout>
