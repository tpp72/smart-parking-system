<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขลานจอด</h1>
                    <p class="text-gray-400 mt-1">{{ $lot->name }}</p>
                </div>
                <a href="{{ route('owner.parking-lots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            @if(session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('owner.parking-lots.update', $lot->id) }}" class="space-y-4">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ชื่อลาน *</label>
                        <input type="text" name="name" value="{{ old('name', $lot->name) }}"
                            class="sp-select w-full" />
                        @error('name')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">สถานที่ / ที่อยู่</label>
                        <textarea name="location" rows="2"
                            class="sp-select w-full">{{ old('location', $lot->location) }}</textarea>
                        @error('location')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">จำนวนช่องจอด *</label>
                            <input type="number" name="total_slots" value="{{ old('total_slots', $lot->total_slots) }}"
                                min="0" class="sp-select w-full" />
                            @error('total_slots')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">อัตรา/ชั่วโมง (บาท) *</label>
                            <input type="number" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', $lot->hourly_rate) }}"
                                min="0" class="sp-select w-full" />
                            @error('hourly_rate')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="submit" class="sp-btn sp-btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>

            {{-- Toggle Active --}}
            <div class="sp-card rounded-2xl p-6 mt-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-200">สถานะลาน</h3>
                        <p class="text-sm text-gray-400">
                            ปัจจุบัน:
                            @if($lot->is_active)
                                <span class="text-green-400 font-bold">เปิดใช้งาน</span> — ปรากฏในตลาด, รับจองได้
                            @else
                                <span class="text-red-400 font-bold">ปิดใช้งาน</span> — ซ่อนจากตลาด
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('owner.parking-lots.toggle', $lot->id) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="sp-btn {{ $lot->is_active ? 'sp-btn-danger' : 'sp-btn-primary' }}">
                            {{ $lot->is_active ? 'ปิดลาน' : 'เปิดลาน' }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="sp-card rounded-2xl p-6 mt-4 border border-red-900/40">
                <h3 class="font-bold text-red-300 mb-2">Danger Zone</h3>
                <form method="POST" action="{{ route('owner.parking-lots.destroy', $lot->id) }}"
                    onsubmit="return confirm('ยืนยันลบลานจอดนี้? (ลบถาวร)')">
                    @csrf @method('DELETE')
                    <button type="submit" class="sp-btn sp-btn-danger w-full">ลบลานจอด (ถาวร)</button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
