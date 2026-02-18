<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการช่องจอด</h1>
                    <p class="text-gray-300 mt-1">ค้นหา/กรอง/เพิ่ม/แก้ไข/ลบช่องจอด</p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('admin.parking-slots.bulk.create') }}" class="sp-btn sp-btn-outline">+
                        เพิ่มหลายช่อง</a>
                    <a href="{{ route('admin.parking-slots.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มช่อง</a>
                </div>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาเลขช่อง..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="lot_id"
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                        <option value="">ทุกลานจอด</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string) $lotId === (string) $lot->id)>{{ $lot->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="status"
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="available" @selected($status === 'available')>available</option>
                        <option value="reserved" @selected($status === 'reserved')>reserved</option>
                        <option value="occupied" @selected($status === 'occupied')>occupied</option>
                    </select>

                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.parking-slots.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">ลาน</th>
                            <th class="py-3 pr-4">ช่อง</th>
                            <th class="py-3 pr-4">สถานะ</th>
                            <th class="py-3 pr-4">อัปเดต</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slots as $slot)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 text-gray-200">{{ $slot->lot?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 font-extrabold">{{ $slot->slot_number }}</td>
                                <td class="py-3 pr-4">
                                    @if ($slot->status === 'available')
                                        <span class="sp-badge sp-badge-ok">available</span>
                                    @elseif($slot->status === 'reserved')
                                        <span class="sp-badge sp-badge-warn">reserved</span>
                                    @else
                                        <span class="sp-badge sp-badge-bad">occupied</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-400">{{ $slot->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.parking-slots.edit', $slot) }}"
                                            class="sp-btn sp-btn-outline">แก้ไข</a>
                                        <form method="POST" action="{{ route('admin.parking-slots.destroy', $slot) }}"
                                            onsubmit="return confirm('ยืนยันลบช่องจอดนี้? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-gray-300">ยังไม่มีช่องจอด</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $slots->links() }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
