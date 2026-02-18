<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไข Reservation</h1>
                    <p class="text-gray-300 mt-1">
                        {{ $reservation->vehicle?->license_plate ?? '-' }} • {{ $reservation->user?->name ?? '-' }}
                    </p>
                </div>
                <a href="{{ route('admin.reservations.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6 mt-6">
                <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ลานจอด *</label>
                            <select name="parking_lot_id" class="sp-select">
                                @foreach ($lots as $lot)
                                    <option value="{{ $lot->id }}" @selected(old('parking_lot_id', $reservation->parking_lot_id) == $lot->id)>
                                        {{ $lot->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parking_lot_id')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ช่องจอด (optional)</label>
                            <select name="parking_slot_id" class="sp-select">
                                <option value="">- ไม่ระบุ -</option>
                                @foreach ($slots as $s)
                                    <option value="{{ $s->id }}" @selected((string) old('parking_slot_id', $reservation->parking_slot_id) === (string) $s->id)>
                                        {{ $s->slot_number }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parking_slot_id')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">เริ่ม *</label>
                            <input type="datetime-local" name="reserve_start"
                                value="{{ old('reserve_start', \Carbon\Carbon::parse($reservation->reserve_start)->format('Y-m-d\TH:i')) }}"
                                class="sp-select" />
                            @error('reserve_start')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">สิ้นสุด *</label>
                            <input type="datetime-local" name="reserve_end"
                                value="{{ old('reserve_end', \Carbon\Carbon::parse($reservation->reserve_end)->format('Y-m-d\TH:i')) }}"
                                class="sp-select" />
                            @error('reserve_end')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">ค่าจอง *</label>
                            <input type="number" step="0.01" name="reservation_fee"
                                value="{{ old('reservation_fee', $reservation->reservation_fee) }}"
                                class="sp-select" />
                            @error('reservation_fee')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-gray-200 mb-1">สถานะ *</label>
                            <select name="status" class="sp-select">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st }}" @selected(old('status', $reservation->status) === $st)>
                                        {{ $st }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button class="sp-btn sp-btn-primary" type="submit">บันทึก</button>
                    </div>
                </form>

                <div class="mt-6">
                    <form method="POST" action="{{ route('admin.reservations.destroy', $reservation) }}"
                        onsubmit="return confirm('ยืนยันลบ reservation นี้? (ลบถาวร)')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sp-btn sp-btn-danger w-full">ลบ Reservation (ถาวร)</button>
                    </form>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
