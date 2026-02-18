<div class="sp-card rounded-2xl p-6">
    <h2 class="text-xl font-extrabold mb-4">ข้อมูลอุปกรณ์</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
            <select name="parking_lot_id" class="sp-select">
                @foreach ($lots as $lot)
                    <option value="{{ $lot->id }}" @selected(old('parking_lot_id', $device?->parking_lot_id) == $lot->id)>{{ $lot->name }}</option>
                @endforeach
            </select>
            @error('parking_lot_id')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm text-gray-200 mb-1">ประเภท *</label>
            <select name="device_type" class="sp-select">
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(old('device_type', $device?->device_type ?? 'gate') === $t)>{{ $t }}</option>
                @endforeach
            </select>
            @error('device_type')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm text-gray-200 mb-1">ตำแหน่งติดตั้ง *</label>
            <input name="location" value="{{ old('location', $device?->location) }}"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
            @error('location')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
            <select name="status" class="sp-select">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', $device?->status ?? 'online') === $s)>{{ $s }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-6 flex gap-2">
        <button class="sp-btn sp-btn-primary" type="submit">{{ $submitLabel }}</button>
        <a class="sp-btn sp-btn-outline" href="{{ route('admin.devices.index') }}">ยกเลิก</a>
    </div>
</div>
