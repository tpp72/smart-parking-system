<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มช่องจอดหลายรายการ</h1>
                <a href="{{ route('owner.parking-slots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('owner.parking-slots.bulk.store') }}" class="space-y-6"
                x-data="{ mode: '{{ old('mode', 'range') }}' }">
                @csrf

                @if($errors->any())
                    <div class="sp-card rounded-2xl p-4 border border-red-600/40">
                        <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                    </div>
                @endif

                <div class="sp-card rounded-2xl p-6 space-y-4">
                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
                        <select name="parking_lot_id" class="sp-select w-full">
                            @foreach($lots as $lot)
                                <option value="{{ $lot->id }}" @selected(old('parking_lot_id') == $lot->id)>{{ $lot->name }}</option>
                            @endforeach
                        </select>
                        @error('parking_lot_id')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">โหมด *</label>
                            <select name="mode" x-model="mode" class="sp-select w-full">
                                <option value="range">สร้างเป็นช่วง (Range)</option>
                                <option value="list">ใส่เป็นรายการ (List)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
                            <select name="status" class="sp-select w-full">
                                @foreach($statuses as $st)
                                    <option value="{{ $st }}" @selected(old('status', 'available') === $st)>{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6" x-show="mode==='range'">
                    <h2 class="text-xl font-extrabold mb-4">สร้างเป็นช่วง</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm text-gray-200 mb-1">Prefix (เช่น A)</label>
                            <input name="prefix" value="{{ old('prefix') }}" class="sp-select w-full" />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">เริ่ม *</label>
                            <input type="number" name="start" value="{{ old('start', 1) }}" class="sp-select w-full" />
                            @error('start')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ถึง *</label>
                            <input type="number" name="end" value="{{ old('end', 50) }}" class="sp-select w-full" />
                            @error('end')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm text-gray-200 mb-1">Pad (เช่น 3 → 001)</label>
                            <input type="number" name="pad" value="{{ old('pad', 0) }}" class="sp-select w-full" />
                        </div>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6" x-show="mode==='list'">
                    <h2 class="text-xl font-extrabold mb-4">ใส่เป็นรายการ</h2>
                    <p class="text-gray-400 text-sm mb-3">คั่นด้วย Enter หรือ comma เช่น: A1, A2, A3</p>
                    <textarea name="slot_numbers" rows="6"
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600">{{ old('slot_numbers') }}</textarea>
                    @error('slot_numbers')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-2">
                    <button class="sp-btn sp-btn-primary" type="submit">บันทึก</button>
                    <a class="sp-btn sp-btn-outline" href="{{ route('owner.parking-slots.index') }}">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
