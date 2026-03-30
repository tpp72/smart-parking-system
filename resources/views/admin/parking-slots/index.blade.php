<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">ช่องจอดรถ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Parking Slots — ค้นหา / กรอง / แก้ไข</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.parking-slots.bulk.create') }}" class="sp-btn sp-btn-outline">+ เพิ่มหลายช่อง</a>
                    <a href="{{ route('admin.parking-slots.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มช่อง</a>
                </div>
            </div>

            {{-- Alerts --}}
            @if(session('success'))
                <x-sp-alert type="success" class="mt-5" :dismissible="true">{{ session('success') }}</x-sp-alert>
            @endif
            @if(session('error') || $errors->any())
                <x-sp-alert type="error" class="mt-5" :dismissible="true">
                    {{ session('error') ?? $errors->first() }}
                </x-sp-alert>
            @endif

            {{-- Filter --}}
            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาเลขช่อง..."
                        class="sp-select" />

                    <select name="lot_id" class="sp-select">
                        <option value="">ทุกลานจอด</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string)$lotId === (string)$lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="available" @selected($status === 'available')>✓ ว่าง (available)</option>
                        <option value="reserved"  @selected($status === 'reserved')>⏳ จอง (reserved)</option>
                        <option value="occupied"  @selected($status === 'occupied')>✗ ไม่ว่าง (occupied)</option>
                    </select>

                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline flex-1" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline flex-1" href="{{ route('admin.parking-slots.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="sp-card rounded-2xl mt-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr>
                                <th class="px-5 py-4 text-left">ลานจอด</th>
                                <th class="px-5 py-4 text-left">ช่อง</th>
                                <th class="px-5 py-4 text-center">สถานะ</th>
                                <th class="px-5 py-4 text-left">อัปเดต</th>
                                <th class="px-5 py-4 text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($slots as $slot)
                                <tr>
                                    <td class="px-5 py-3 text-gray-300">{{ $slot->lot?->name ?? '—' }}</td>
                                    <td class="px-5 py-3 font-extrabold tracking-wider">{{ $slot->slot_number }}</td>
                                    <td class="px-5 py-3 text-center">
                                        @if($slot->status === 'available')
                                            <span class="sp-badge sp-badge-ok">● ว่าง</span>
                                        @elseif($slot->status === 'reserved')
                                            <span class="sp-badge sp-badge-warn">⏳ จอง</span>
                                        @else
                                            <span class="sp-badge sp-badge-bad">✗ ไม่ว่าง</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">
                                        {{ $slot->updated_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.parking-slots.edit', $slot) }}"
                                                class="sp-btn sp-btn-outline text-sm py-1.5 px-3">แก้ไข</a>
                                            <form method="POST" action="{{ route('admin.parking-slots.destroy', $slot) }}"
                                                onsubmit="return confirm('ลบช่องจอด {{ $slot->slot_number }}? (ไม่สามารถกู้คืนได้)')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="sp-btn sp-btn-danger text-sm py-1.5 px-3">ลบ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-sp-empty message="ยังไม่มีช่องจอด" sub="กดปุ่ม '+ เพิ่มช่อง' เพื่อเริ่มต้น">
                                            <a href="{{ route('admin.parking-slots.create') }}" class="sp-btn sp-btn-primary mt-2">+ เพิ่มช่อง</a>
                                        </x-sp-empty>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($slots->hasPages())
                    <div class="px-5 py-4 border-t border-white/10">
                        {{ $slots->links('vendor.pagination.sp') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
