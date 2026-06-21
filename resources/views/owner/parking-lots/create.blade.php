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
                        <label class="block text-sm text-gray-200 mb-1">ที่อยู่ (เลขที่ / ถนน)</label>
                        <input type="text" name="address" value="{{ old('address') }}"
                            class="sp-select w-full" placeholder="เช่น 123/4 ถ.สุขุมวิท" />
                        @error('address')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">แขวง / ตำบล</label>
                            <input type="text" name="district" value="{{ old('district') }}"
                                class="sp-select w-full" placeholder="เช่น คลองเตย" />
                            @error('district')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">จังหวัด</label>
                            <input type="text" name="province" value="{{ old('province') }}"
                                class="sp-select w-full" placeholder="เช่น กรุงเทพมหานคร" />
                            @error('province')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">จุดสังเกต / ใกล้กับ</label>
                        <input type="text" name="landmark" value="{{ old('landmark') }}"
                            class="sp-select w-full" placeholder="เช่น ใกล้ BTS อโศก" />
                        @error('landmark')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">หมายเหตุสถานที่ (เพิ่มเติม)</label>
                        <textarea name="location" rows="2"
                            class="sp-select w-full" placeholder="คำอธิบายเพิ่มเติม เช่น ทางเข้าด้านหลังอาคาร">{{ old('location') }}</textarea>
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

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="reservations_enabled" value="0" />
                        <input type="checkbox" name="reservations_enabled" value="1" id="reservations_enabled" checked
                            class="w-4 h-4 rounded bg-black/40 border-gray-700 text-red-600 focus:ring-red-600" />
                        <label for="reservations_enabled" class="text-sm text-gray-200">
                            รับจองล่วงหน้า (Reservations)
                        </label>
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
