<div class="sp-card rounded-2xl p-6">
    <h2 class="text-xl font-extrabold mb-4">ข้อมูลช่องจอด</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
            <select name="parking_lot_id"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                @foreach ($lots as $lot)
                    <option value="{{ $lot->id }}" @selected(old('parking_lot_id', $slot?->parking_lot_id) == $lot->id)>{{ $lot->name }}</option>
                @endforeach
            </select>
            @error('parking_lot_id')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm text-gray-200 mb-1">เลขช่อง *</label>
            <input name="slot_number" value="{{ old('slot_number', $slot?->slot_number) }}"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
            @error('slot_number')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
            <select name="status"
                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected(old('status', $slot?->status ?? 'available') === $st)>{{ $st }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mt-6 flex gap-2">
        <button class="sp-btn sp-btn-primary" type="submit">{{ $submitLabel }}</button>
        <a class="sp-btn sp-btn-outline" href="{{ route('admin.parking-slots.index') }}">ยกเลิก</a>
    </div>
</div>
