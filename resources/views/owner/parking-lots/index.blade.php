<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">ลานจอดของฉัน</h1>
                    <p class="text-gray-300 mt-1">จัดการลานจอดที่คุณเป็นเจ้าของ</p>
                </div>
                <a href="{{ route('owner.parking-lots.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มลานจอด</a>
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
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาชื่อ/สถานที่..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />
                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('owner.parking-lots.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ชื่อ</th>
                            <th class="py-3 pr-4 text-left">สถานที่</th>
                            <th class="py-3 pr-4 text-right">ช่อง</th>
                            <th class="py-3 pr-4 text-right">เรท/ชม.</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                            <th class="py-3 pr-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lots as $lot)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-extrabold">{{ $lot->name }}</td>
                                <td class="py-3 pr-4 text-gray-300">{{ $lot->location ? \Illuminate\Support\Str::limit($lot->location, 50) : '-' }}</td>
                                <td class="py-3 pr-4 text-right text-gray-200">{{ $lot->total_slots }}</td>
                                <td class="py-3 pr-4 text-right font-bold text-red-200">{{ number_format((float)$lot->hourly_rate, 2) }}</td>
                                <td class="py-3 pr-4 text-center">
                                    @if($lot->is_active)
                                        <span class="sp-badge sp-badge-ok">เปิด</span>
                                    @else
                                        <span class="sp-badge sp-badge-danger">ปิด</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2 justify-end flex-wrap">
                                        <a href="{{ route('owner.parking-lots.edit', $lot->id) }}" class="sp-btn sp-btn-outline">แก้ไข</a>

                                        <form method="POST" action="{{ route('owner.parking-lots.toggle', $lot->id) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="sp-btn sp-btn-outline">
                                                {{ $lot->is_active ? 'ปิด' : 'เปิด' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('owner.parking-lots.destroy', $lot->id) }}"
                                            onsubmit="return confirm('ยืนยันลบลานจอดนี้?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    ยังไม่มีลานจอด — กด "เพิ่มลานจอด" เพื่อเริ่มใช้งาน
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $lots->links() }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
