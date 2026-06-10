<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขช่องจอด</h1>
                <a href="{{ route('owner.parking-slots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('owner.parking-slots.update', $slot) }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
                            <select name="parking_lot_id" class="sp-select w-full">
                                @foreach($lots as $lot)
                                    <option value="{{ $lot->id }}" @selected(old('parking_lot_id', $slot->parking_lot_id) == $lot->id)>{{ $lot->name }}</option>
                                @endforeach
                            </select>
                            @error('parking_lot_id')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">เลขช่อง *</label>
                            <input name="slot_number" value="{{ old('slot_number', $slot->slot_number) }}" class="sp-select w-full" />
                            @error('slot_number')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
                            <select name="status" class="sp-select w-full">
                                @foreach($statuses as $st)
                                    <option value="{{ $st }}" @selected(old('status', $slot->status) === $st)>{{ $st }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="sp-btn sp-btn-primary">บันทึก</button>
                        <a href="{{ route('owner.parking-slots.index') }}" class="sp-btn sp-btn-outline">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
