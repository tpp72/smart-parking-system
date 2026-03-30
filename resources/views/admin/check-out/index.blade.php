<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">รถออก (Manual Check-Out)</h1>
                <p class="text-gray-300 mt-1">รายการรถที่กำลังจอดอยู่ — กด Check-Out เพื่อคำนวณเงินและคืนช่องจอด</p>
            </div>

            {{-- Flash success --}}
            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Error --}}
            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-6 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6 overflow-x-auto">
                @if ($logs->isEmpty())
                    <p class="text-gray-400 text-center py-8">ไม่มีรถที่กำลังจอดอยู่ในขณะนี้</p>
                @else
                    <table class="w-full sp-table">
                        <thead>
                            <tr class="border-b sp-divider">
                                <th class="py-3 pr-4 text-left">ทะเบียน</th>
                                <th class="py-3 pr-4 text-left">รถ</th>
                                <th class="py-3 pr-4 text-left">ลาน / ช่อง</th>
                                <th class="py-3 pr-4 text-left">Check-In</th>
                                <th class="py-3 pr-4 text-right">จอดมาแล้ว</th>
                                <th class="py-3 pr-4 text-right">อัตรา/ชม.</th>
                                <th class="py-3 pr-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php
                                    $minutes     = (int) $log->check_in_time->diffInMinutes(now());
                                    $hoursElapsed = max(1, (int) ceil($minutes / 60));
                                    $estimatedFee = number_format($hoursElapsed * $log->parkingLot->hourly_rate, 2);
                                @endphp
                                <tr class="border-b sp-divider hover:bg-white/5 transition">
                                    <td class="py-3 pr-4 font-bold text-red-300">
                                        {{ $log->vehicle->license_plate }}
                                    </td>
                                    <td class="py-3 pr-4 text-gray-300">
                                        {{ $log->vehicle->brand }}, {{ $log->vehicle->color }}
                                    </td>
                                    <td class="py-3 pr-4 text-gray-300">
                                        {{ $log->parkingLot->name }}
                                        @if ($log->parkingSlot)
                                            <span class="text-gray-400">/ {{ $log->parkingSlot->slot_number }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">
                                        {{ $log->check_in_time->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-3 pr-4 text-right text-yellow-300 whitespace-nowrap">
                                        {{ $hoursElapsed }} ชม.
                                        <span class="block text-xs text-gray-400">≈ {{ $estimatedFee }} บาท</span>
                                    </td>
                                    <td class="py-3 pr-4 text-right text-gray-300">
                                        {{ number_format($log->parkingLot->hourly_rate, 2) }} บาท
                                    </td>
                                    <td class="py-3 pr-4 text-center">
                                        <form method="POST"
                                            action="{{ route('admin.check-out.store', $log) }}"
                                            onsubmit="return confirm('ยืนยัน Check-Out ทะเบียน {{ $log->vehicle->license_plate }}?')">
                                            @csrf
                                            <button type="submit"
                                                class="sp-btn sp-btn-outline text-sm px-4 py-1">
                                                Check-Out
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $logs->links('vendor.pagination.sp') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
