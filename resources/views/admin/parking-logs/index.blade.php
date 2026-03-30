<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">ประวัติการจอด (Parking Logs)</h1>
                    <p class="text-gray-300 mt-1">ค้นหาตามทะเบียนรถ / กรองตามวันที่</p>
                </div>
            </div>

            {{-- Filter --}}
            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียนรถ..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600 px-4 py-2" />

                    <input type="date" name="from" value="{{ $from }}" class="sp-select"
                        title="วันที่เข้า (เริ่มต้น)" />

                    <input type="date" name="to" value="{{ $to }}" class="sp-select"
                        title="วันที่เข้า (สิ้นสุด)" />

                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline flex-1" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline flex-1 text-center"
                            href="{{ route('admin.parking-logs.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">รถ</th>
                            <th class="py-3 pr-4 text-left">ลาน / ช่อง</th>
                            <th class="py-3 pr-4 text-left">เวลาเข้า</th>
                            <th class="py-3 pr-4 text-left">เวลาออก</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b sp-divider hover:bg-white/5 transition">
                                <td class="py-3 pr-4 font-extrabold text-red-300">
                                    {{ $log->vehicle?->license_plate ?? '-' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $log->vehicle?->brand }}
                                    <span class="text-gray-500">{{ $log->vehicle?->color }}</span>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">ไม่พบข้อมูล</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $logs->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
