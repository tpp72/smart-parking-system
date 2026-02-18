<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มช่องจอดหลายรายการ</h1>
                <a href="{{ route('admin.parking-slots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.parking-slots.bulk.store') }}" class="mt-6 space-y-6"
                x-data="{ mode: '{{ old('mode', 'range') }}' }">
                @csrf

                <div class="sp-card rounded-2xl p-6 space-y-4">
                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
                        <select name="parking_lot_id"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                            @foreach ($lots as $lot)
                                <option value="{{ $lot->id }}" @selected(old('parking_lot_id') == $lot->id)>{{ $lot->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parking_lot_id')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">โหมด *</label>
                            <select name="mode" x-model="mode"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                                <option value="range">สร้างเป็นช่วง (Range)</option>
                                <option value="list">ใส่เป็นรายการ (List)</option>
                            </select>
                            @error('mode')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
                            <select name="status"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600 sp-select">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st }}" @selected(old('status', 'available') === $st)>{{ $st }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6" x-show="mode==='range'">
                    <h2 class="text-xl font-extrabold mb-4">สร้างเป็นช่วง</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm text-gray-200 mb-1">Prefix (เช่น A)</label>
                            <input name="prefix" value="{{ old('prefix') }}"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                            @error('prefix')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">เริ่ม *</label>
                            <input type="number" name="start" value="{{ old('start', 1) }}"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                            @error('start')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ถึง *</label>
                            <input type="number" name="end" value="{{ old('end', 50) }}"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                            @error('end')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm text-gray-200 mb-1">Pad (เช่น 3 → 001)</label>
                            <input type="number" name="pad" value="{{ old('pad', 0) }}"
                                class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                            @error('pad')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6" x-show="mode==='list'">
                    <h2 class="text-xl font-extrabold mb-4">ใส่เป็นรายการ</h2>
                    <p class="text-gray-300 text-sm mb-3">คั่นด้วย Enter หรือ comma เช่น: A1, A2, A3</p>
                    <textarea name="slot_numbers" rows="6"
                        class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600">{{ old('slot_numbers') }}</textarea>
                    @error('slot_numbers')
                        <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-2">
                    <button class="sp-btn sp-btn-primary" type="submit">บันทึกหลายช่อง</button>
                    <a class="sp-btn sp-btn-outline" href="{{ route('admin.parking-slots.index') }}">ยกเลิก</a>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
