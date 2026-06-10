<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">ช่องจอด</h1>
                    <p class="text-gray-400 text-sm mt-0.5">จัดการช่องจอดในลานของคุณ</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('owner.parking-slots.bulk.create') }}" class="sp-btn sp-btn-outline">+ เพิ่มหลายช่อง</a>
                    <a href="{{ route('owner.parking-slots.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มช่อง</a>
                </div>
            </div>

            @if(session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="sp-card rounded-2xl p-4 mt-6 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาเลขช่อง..." class="sp-select" />
                    <select name="lot_id" class="sp-select">
                        <option value="">ทุกลาน</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string)$lotId === (string)$lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="available" @selected($status === 'available')>✓ ว่าง</option>
                        <option value="reserved"  @selected($status === 'reserved')>⏳ จอง</option>
                        <option value="occupied"  @selected($status === 'occupied')>✗ ใช้งาน</option>
                    </select>
                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline flex-1" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline flex-1" href="{{ route('owner.parking-slots.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ช่อง</th>
                            <th class="py-3 pr-4 text-left">ลาน</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                            <th class="py-3 pr-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slots as $slot)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 font-bold">{{ $slot->slot_number }}</td>
                                <td class="py-3 pr-4 text-gray-300">{{ $slot->parkingLot?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-center">
                                    @php
                                        $sc = match($slot->status) {
                                            'available' => 'sp-badge-ok',
                                            'reserved'  => 'sp-badge-warn',
                                            default     => 'sp-badge-danger',
                                        };
                                    @endphp
                                    <span class="sp-badge {{ $sc }}">{{ $slot->status }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-2 justify-end">
                                        <a href="{{ route('owner.parking-slots.edit', $slot) }}" class="sp-btn sp-btn-outline">แก้ไข</a>
                                        <form method="POST" action="{{ route('owner.parking-slots.destroy', $slot) }}"
                                            onsubmit="return confirm('ยืนยันลบช่องจอดนี้?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-gray-400">ยังไม่มีช่องจอด</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $slots->links() }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
