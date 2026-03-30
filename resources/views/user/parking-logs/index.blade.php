<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">ประวัติการจอด</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Parking History</p>
                </div>
                <a href="{{ route('user.dashboard') }}" class="sp-btn sp-btn-outline text-sm">← Dashboard</a>
            </div>

            <div class="sp-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                <th class="px-5 py-4 text-left font-medium">ทะเบียน</th>
                                <th class="px-5 py-4 text-left font-medium">ลาน / ช่อง</th>
                                <th class="px-5 py-4 text-left font-medium">เข้า</th>
                                <th class="px-5 py-4 text-left font-medium">ออก</th>
                                <th class="px-5 py-4 text-left font-medium">ค่าจอด</th>
                                <th class="px-5 py-4 text-left font-medium">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($logs as $log)
                                <tr class="hover:bg-white/[0.03] transition">
                                    <td class="px-5 py-4 font-extrabold">{{ $log->license_plate }}</td>
                                    <td class="px-5 py-4 text-gray-300">
                                        {{ $log->lot_name }}
                                        @if($log->slot_number)
                                            <span class="text-gray-500">· {{ $log->slot_number }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-400 text-xs">{{ $log->check_in_time }}</td>
                                    <td class="px-5 py-4 text-xs">
                                        @if($log->check_out_time)
                                            <span class="text-green-400">{{ $log->check_out_time }}</span>
                                        @else
                                            <span class="text-yellow-400 animate-pulse">กำลังจอด…</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-gray-300">
                                        @if($log->total_amount !== null)
                                            ฿{{ number_format((float)$log->total_amount, 2) }}
                                            <span class="text-xs text-gray-500">({{ $log->total_hours }}h)</span>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($log->payment_status === 'paid')
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30">ชำระแล้ว</span>
                                        @elseif($log->payment_status === 'unpaid')
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30">ค้างชำระ</span>
                                        @elseif(!$log->check_out_time)
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30">active</span>
                                        @else
                                            <span class="text-xs text-gray-500">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                                        ยังไม่มีประวัติการจอด
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($logs->hasPages())
                    <div class="px-5 py-4 border-t border-white/10">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
