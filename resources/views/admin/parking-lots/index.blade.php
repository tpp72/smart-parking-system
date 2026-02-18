<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการลานจอด</h1>
                    <p class="text-gray-300 mt-1">เพิ่ม/แก้ไข/ลบลานจอด (ลบ = ลบถาวร)</p>
                </div>

                <a href="{{ route('admin.parking-lots.create') }}" class="sp-btn sp-btn-primary">
                    + เพิ่มลานจอด
                </a>
            </div>

            {{-- Flash --}}
            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Search --}}
            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาชื่อ/สถานที่..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />
                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.parking-lots.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ชื่อ</th>
                            <th class="py-3 pr-4 text-left">สถานที่</th>
                            <th class="py-3 pr-4 text-left">จำนวนช่อง</th>
                            <th class="py-3 pr-4 text-left">เรท/ชม.</th>
                            <th class="py-3 pr-4 text-left">อัปเดต</th>
                            <th class="py-3 pr-4 text-right">จัดการ</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($lots as $lot)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-extrabold">{{ $lot->name }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $lot->location ? \Illuminate\Support\Str::limit($lot->location, 60) : '-' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-200">{{ $lot->total_slots }}</td>
                                <td class="py-3 pr-4 font-bold text-red-200">
                                    {{ number_format((float) $lot->hourly_rate, 2) }}
                                </td>
                                <td class="py-3 pr-4 text-gray-400">{{ $lot->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.parking-lots.edit', $lot) }}"
                                            class="sp-btn sp-btn-outline">
                                            แก้ไข
                                        </a>

                                        <form method="POST" action="{{ route('admin.parking-lots.destroy', $lot) }}"
                                            onsubmit="return confirm('ยืนยันลบลานจอดนี้? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">
                                                ลบ
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-300">
                                    ยังไม่มีลานจอด — กด “เพิ่มลานจอด” เพื่อเริ่มใช้งาน
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $lots->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
