<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มลานจอด</h1>
                    <p class="text-gray-400 mt-1">สร้างลานจอดใหม่ที่คุณเป็นเจ้าของ</p>
                </div>
                <a href="{{ route('owner.parking-lots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('owner.parking-lots.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ชื่อลาน *</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="sp-select w-full" placeholder="เช่น Central Parking A" />
                        @error('name')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">สถานที่ / ที่อยู่</label>
                        <textarea name="location" rows="2"
                            class="sp-select w-full" placeholder="ระบุที่อยู่หรือคำอธิบายตำแหน่ง">{{ old('location') }}</textarea>
                        @error('location')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">จำนวนช่องจอด *</label>
                            <input type="number" name="total_slots" value="{{ old('total_slots', 0) }}"
                                min="0" class="sp-select w-full" />
                            @error('total_slots')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">อัตรา/ชั่วโมง (บาท) *</label>
                            <input type="number" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', 0) }}"
                                min="0" class="sp-select w-full" />
                            @error('hourly_rate')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <a href="{{ route('owner.parking-lots.index') }}" class="sp-btn sp-btn-outline">ยกเลิก</a>
                        <button type="submit" class="sp-btn sp-btn-primary">สร้างลานจอด</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
