<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการอุปกรณ์เข้า-ออก</h1>
                    <p class="text-gray-300 mt-1">gate / camera / scanner</p>
                </div>

                <a href="{{ route('admin.devices.create') }}" class="sp-btn sp-btn-primary">
                    + เพิ่มอุปกรณ์
                </a>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาตำแหน่งติดตั้ง..."
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="lot_id" class="sp-select">
                        <option value="">ทุกลานจอด</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((string) $lotId === (string) $lot->id)>{{ $lot->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="device_type" class="sp-select">
                        <option value="">ทุกประเภท</option>
                        <option value="gate" @selected($type === 'gate')>gate</option>
                        <option value="camera" @selected($type === 'camera')>camera</option>
                        <option value="scanner" @selected($type === 'scanner')>scanner</option>
                    </select>

                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        <option value="online" @selected($status === 'online')>online</option>
                        <option value="offline" @selected($status === 'offline')>offline</option>
                    </select>

                    <div class="flex gap-2">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.devices.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">ลาน</th>
                            <th class="py-3 pr-4">ประเภท</th>
                            <th class="py-3 pr-4">ตำแหน่ง</th>
                            <th class="py-3 pr-4">สถานะ</th>
                            <th class="py-3 pr-4">อัปเดต</th>
                            <th class="py-3 pr-4" style="text-align:right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($devices as $d)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 text-gray-200">{{ $d->parkingLot?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 font-bold">{{ $d->device_type }}</td>
                                <td class="py-3 pr-4 text-gray-300">{{ $d->location }}</td>
                                <td class="py-3 pr-4">
                                    @if ($d->status === 'online')
                                        <span class="sp-badge sp-badge-ok">online</span>
                                    @else
                                        <span class="sp-badge sp-badge-bad">offline</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-400">{{ $d->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.devices.edit', $d) }}"
                                            class="sp-btn sp-btn-outline">แก้ไข</a>
                                        <form method="POST" action="{{ route('admin.devices.destroy', $d) }}"
                                            onsubmit="return confirm('ยืนยันลบอุปกรณ์นี้? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-300">ยังไม่มีอุปกรณ์</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $devices->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
