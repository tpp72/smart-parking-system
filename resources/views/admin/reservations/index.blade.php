<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการการจอง (Reservations)</h1>
                    <p class="text-gray-300 mt-1">ค้นหา / กรอง / แก้ไขสถานะ / ลบ</p>
                </div>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
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
                        {{-- ให้ตรงกับ controller --}}
                        @foreach (['pending', 'confirmed', 'cancelled', 'expired'] as $st)
                            <option value="{{ $st }}" @selected($status === $st)>{{ $st }}
                            </option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ $from }}" class="sp-select" />
                    <input type="date" name="to" value="{{ $to }}" class="sp-select" />

                    <div class="flex gap-2 md:col-span-6">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.reservations.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">ทะเบียน</th>
                            <th class="py-3 pr-4">ผู้ใช้</th>
                            <th class="py-3 pr-4">ลาน</th>
                            <th class="py-3 pr-4">ช่อง</th>
                            <th class="py-3 pr-4">ช่วงเวลา</th>
                            <th class="py-3 pr-4">ค่าจอง</th>
                            <th class="py-3 pr-4">สถานะ</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $r)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-extrabold">{{ $r->vehicle?->license_plate ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->user?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->parkingLot?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->parkingSlot?->slot_number ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $r->reserve_start }} → {{ $r->reserve_end }}
                                </td>
                                <td class="py-3 pr-4 font-bold text-red-200">
                                    {{ number_format((float) $r->reservation_fee, 2) }}
                                </td>
                                <td class="py-3 pr-4">
                                    @php
                                        $badge = match ($r->status) {
                                            'confirmed' => 'sp-badge sp-badge-ok',
                                            'pending' => 'sp-badge sp-badge-warn',
                                            'cancelled' => 'sp-badge sp-badge-bad',
                                            'expired' => 'sp-badge sp-badge-bad',
                                            default => 'sp-badge sp-badge-warn',
                                        };
                                    @endphp

                                    <span class="{{ $badge }}">
                                        {{ $r->status }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.reservations.edit', $r) }}"
                                            class="sp-btn sp-btn-outline">แก้ไข</a>
                                        <form method="POST" action="{{ route('admin.reservations.destroy', $r) }}"
                                            onsubmit="return confirm('ยืนยันลบ reservation นี้? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">ลบ</button>
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

                <div class="mt-4">
                    {{ $reservations->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
