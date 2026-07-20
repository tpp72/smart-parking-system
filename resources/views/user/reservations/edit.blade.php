<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">แก้ไขข้อมูลรถ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">การจอง #{{ $reservation->id }} — {{ $reservation->parkingLot?->name }}</p>
                </div>
                <a href="{{ route('user.reservations.index') }}" class="sp-btn sp-btn-outline text-sm">← กลับ</a>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-5 border border-red-600/40">
                    <ul class="text-red-300 text-sm space-y-1">
                        @foreach ($errors->all() as $e) <li>• {{ $e }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6">

                {{-- ข้อมูลการจอง --}}
                <div class="grid grid-cols-2 gap-3 mb-6 text-sm">
                    <div>
                        <p class="text-gray-500">ลานจอด</p>
                        <p class="font-semibold">{{ $reservation->parkingLot?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">ช่องจอด</p>
                        <p class="font-semibold">{{ $reservation->parkingSlot?->slot_number ?? 'ระบบจัดให้' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">เวลาเริ่ม</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($reservation->reserve_start)->format('d/m/Y H:i') }} น.</p>
                    </div>
                    <div>
                        <p class="text-gray-500">สถานะ</p>
                        <span class="sp-badge {{ $reservation->status === 'confirmed' ? 'sp-badge-ok' : 'sp-badge-warn' }}">
                            {{ $reservation->status }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <form method="POST" action="{{ route('user.reservations.update-plate', $reservation) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label value="ป้ายทะเบียนรถ" />
                            <div class="grid grid-cols-2 gap-3 mt-1">
                                <div>
                                    <x-text-input id="plate_number" name="plate_number" type="text"
                                        class="block w-full uppercase tracking-widest @error('plate_number') border-red-500 @enderror"
                                        value="{{ old('plate_number', $plateNumber) }}"
                                        placeholder="เช่น กข 1234"
                                        maxlength="15"
                                        autocomplete="off" />
                                    <x-input-error :messages="$errors->get('plate_number')" class="mt-2" />
                                </div>
                                <div>
                                    <select id="plate_province" name="plate_province"
                                        class="sp-select w-full @error('plate_province') border-red-500 @enderror">
                                        <option value="">-- จังหวัด --</option>
                                        @foreach (config('thai_provinces') as $province)
                                            <option value="{{ $province }}" @selected(old('plate_province', $plateProvince) === $province)>
                                                {{ $province }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('plate_province')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="ยี่ห้อ / สีรถ" />
                            <div class="grid grid-cols-2 gap-3 mt-1">
                                <div>
                                    <x-text-input id="brand" name="brand" type="text"
                                        class="block w-full @error('brand') border-red-500 @enderror"
                                        value="{{ old('brand', $reservation->brand) }}"
                                        placeholder="เช่น Toyota"
                                        maxlength="60"
                                        autocomplete="off"
                                        required />
                                    <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                                </div>
                                <div>
                                    <select id="color" name="color"
                                        class="sp-select w-full @error('color') border-red-500 @enderror"
                                        required>
                                        <option value="">-- สีรถ --</option>
                                        @foreach (config('car_colors') as $c)
                                            <option value="{{ $c }}" @selected(old('color', $reservation->color) === $c)>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('color')" class="mt-2" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">กรอกยี่ห้อและสีรถที่จะนำมาจอด (แก้ไขได้ภายหลัง ก่อนเช็คอิน)</p>
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit" class="sp-btn sp-btn-primary flex-1 justify-center">
                                บันทึก
                            </button>
                            <a href="{{ route('user.reservations.index') }}"
                                class="sp-btn sp-btn-outline flex-1 text-center">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
