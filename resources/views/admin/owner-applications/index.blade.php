<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">คำขอเจ้าของลานจอด</h1>
                    <p class="text-gray-400 text-sm mt-0.5">รายการคำขอทั้งหมดจากผู้ใช้ที่ต้องการเป็นเจ้าของลานจอด</p>
                </div>
            </div>

            {{-- Flash --}}
            @if(session('success'))
            <div class="sp-card rounded-xl p-4 border border-green-500/40 text-green-300 text-sm">{{ session('success') }}</div>
            @endif

            {{-- Status counters --}}
            <div class="grid grid-cols-3 gap-4">
                <a href="{{ route('admin.owner-applications.index', ['status' => 'pending']) }}"
                   class="sp-card rounded-2xl p-4 text-center {{ $status === 'pending' ? 'ring-2 ring-yellow-500/60' : '' }}">
                    <p class="text-2xl font-extrabold text-yellow-400">{{ $counts['pending'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">รอพิจารณา</p>
                </a>
                <a href="{{ route('admin.owner-applications.index', ['status' => 'approved']) }}"
                   class="sp-card rounded-2xl p-4 text-center {{ $status === 'approved' ? 'ring-2 ring-green-500/60' : '' }}">
                    <p class="text-2xl font-extrabold text-green-400">{{ $counts['approved'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">อนุมัติแล้ว</p>
                </a>
                <a href="{{ route('admin.owner-applications.index', ['status' => 'rejected']) }}"
                   class="sp-card rounded-2xl p-4 text-center {{ $status === 'rejected' ? 'ring-2 ring-red-500/60' : '' }}">
                    <p class="text-2xl font-extrabold text-red-400">{{ $counts['rejected'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">ไม่อนุมัติ</p>
                </a>
            </div>

            {{-- Search & Filter --}}
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="text" name="q" value="{{ $q }}" placeholder="ค้นหาชื่อธุรกิจ, ผู้ติดต่อ, อีเมล..."
                    class="sp-input flex-1">
                <select name="status" class="sp-input w-full sm:w-40">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" @selected($status === 'pending')>รอพิจารณา</option>
                    <option value="approved" @selected($status === 'approved')>อนุมัติแล้ว</option>
                    <option value="rejected" @selected($status === 'rejected')>ไม่อนุมัติ</option>
                </select>
                <button type="submit" class="sp-btn sp-btn-primary whitespace-nowrap">ค้นหา</button>
                @if($q || $status)
                <a href="{{ route('admin.owner-applications.index') }}" class="sp-btn sp-btn-outline whitespace-nowrap">ล้าง</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="sp-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b sp-divider text-xs text-gray-400 uppercase tracking-wide">
                                <th class="py-3 pl-6 text-left">ผู้ยื่นคำขอ</th>
                                <th class="py-3 text-left">ธุรกิจ / ลานจอด</th>
                                <th class="py-3 text-left">เบอร์โทร</th>
                                <th class="py-3 text-left">ช่องจอด</th>
                                <th class="py-3 text-left">วันที่ส่ง</th>
                                <th class="py-3 text-left">สถานะ</th>
                                <th class="py-3 pr-6"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y sp-divider">
                            @forelse($applications as $app)
                            <tr class="hover:bg-white/5 transition">
                                <td class="py-3 pl-6">
                                    <p class="font-semibold text-gray-200">{{ $app->user?->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $app->user?->email }}</p>
                                </td>
                                <td class="py-3">
                                    <p class="font-medium">{{ $app->business_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $app->parking_lot_name }}</p>
                                </td>
                                <td class="py-3 text-gray-300">{{ $app->phone }}</td>
                                <td class="py-3 text-gray-300">{{ number_format($app->estimated_slots) }}</td>
                                <td class="py-3 text-gray-400 text-xs">{{ $app->created_at->format('d/m/Y') }}</td>
                                <td class="py-3">
                                    @if($app->status === 'pending')
                                        <span class="sp-badge sp-badge-warn">รอพิจารณา</span>
                                    @elseif($app->status === 'approved')
                                        <span class="sp-badge sp-badge-ok">อนุมัติ</span>
                                    @else
                                        <span class="sp-badge sp-badge-danger">ไม่อนุมัติ</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-6">
                                    <a href="{{ route('admin.owner-applications.show', $app) }}"
                                        class="sp-btn sp-btn-outline text-xs py-1">ดูรายละเอียด</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-gray-500">ไม่มีคำขอที่ตรงตามเงื่อนไข</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if($applications->hasPages())
            <div>{{ $applications->withQueryString()->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
