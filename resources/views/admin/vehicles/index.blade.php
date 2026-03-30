<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">จัดการยานพาหนะ</h1>
                    <p class="text-gray-300 mt-1">ค้นหา / เพิ่ม / แก้ไข / ลบ</p>
                </div>
                <a href="{{ route('admin.vehicles.create') }}" class="sp-btn sp-btn-primary">+ เพิ่มรถ</a>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Search --}}
            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียน / ยี่ห้อ / สี / เจ้าของ..."
                        class="flex-1 rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600 px-4 py-2" />
                    <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                    <a class="sp-btn sp-btn-outline" href="{{ route('admin.vehicles.index') }}">ล้าง</a>
                </form>
            </div>

            {{-- Table --}}
            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">ยี่ห้อ</th>
                            <th class="py-3 pr-4 text-left">สี</th>
                            <th class="py-3 pr-4 text-left">เจ้าของ</th>
                            <th class="py-3 pr-4 text-right">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr class="border-b sp-divider hover:bg-white/5 transition">
                                <td class="py-3 pr-4 font-extrabold text-red-300">
                                    {{ $vehicle->license_plate }}
                                </td>
                                <td class="py-3 pr-4 text-gray-200">{{ $vehicle->brand }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $vehicle->color }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $vehicle->user?->name ?? '-' }}
                                    @if ($vehicle->user?->email)
                                        <span class="block text-xs text-gray-500">{{ $vehicle->user->email }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.vehicles.edit', $vehicle) }}"
                                            class="sp-btn sp-btn-outline text-sm">แก้ไข</a>

                                        <form method="POST"
                                            action="{{ route('admin.vehicles.destroy', $vehicle) }}"
                                            onsubmit="return confirm('ยืนยันลบรถ {{ $vehicle->license_plate }}? (ลบถาวร)')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sp-btn sp-btn-danger text-sm">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-gray-400">ยังไม่มีข้อมูลรถ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $vehicles->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
