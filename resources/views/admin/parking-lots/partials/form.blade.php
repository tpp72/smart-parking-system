<div class="sp-card rounded-2xl p-6">
    <h2 class="text-xl font-extrabold mb-4">ข้อมูลลานจอด</h2>

    <div class="space-y-4">
        <div>
            <label class="block text-sm text-gray-200 mb-1">ชื่อ *</label>
            <input name="name" value="{{ old('name', $lot?->name) }}"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
            @error('name')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm text-gray-200 mb-1">สถานที่</label>
            <textarea name="location" rows="3"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600">{{ old('location', $lot?->location) }}</textarea>
            @error('location')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

<div class="sp-card rounded-2xl p-6">
    <h2 class="text-xl font-extrabold mb-4">ตั้งค่า</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-gray-200 mb-1">จำนวนช่อง *</label>
            <input type="number" name="total_slots" min="0" value="{{ old('total_slots', $lot?->total_slots) }}"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
            @error('total_slots')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm text-gray-200 mb-1">เรท/ชั่วโมง (฿) *</label>
            <input type="number" name="hourly_rate" min="0" step="0.01"
                value="{{ old('hourly_rate', $lot?->hourly_rate) }}"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
            @error('hourly_rate')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-6 flex gap-2">
        <button class="sp-btn sp-btn-primary" type="submit">{{ $submitLabel }}</button>
        <a class="sp-btn sp-btn-outline" href="{{ route('admin.parking-lots.index') }}">ยกเลิก</a>
    </div>
</div>
