<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <a href="{{ route('admin.reservations.index') }}"
                    class="text-gray-400 hover:text-white text-sm transition">← รายการจอง</a>
                <h1 class="text-2xl font-extrabold sp-glow-text mt-2">สร้างการจอง</h1>
                <p class="text-gray-400 text-sm mt-0.5">New Reservation — เลือกรถ ลาน ช่อง และช่วงเวลา</p>
            </div>

            @if ($errors->any())
                <x-sp-alert type="error" class="mb-5" :dismissible="true">
                    <ul class="space-y-0.5">
                        @foreach ($errors->all() as $e)
                            <li>• {{ $e }}</li>
                        @endforeach
                    </ul>
                </x-sp-alert>
            @endif

            <div class="sp-card rounded-2xl p-6" x-data="{
                allSlots: {{ Js::from($slots) }},
                lotId: '{{ old('parking_lot_id') }}',
                get filteredSlots() {
                    if (!this.lotId) return [];
                    return this.allSlots.filter(s => String(s.parking_lot_id) === String(this.lotId));
                }
            }">

                <form method="POST" action="{{ route('admin.reservations.store') }}" class="space-y-5">
                    @csrf

                    @php
                        $minDatetime = now()->format('Y-m-d\TH:i');
                        $exampleStart = now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
                    @endphp

                    {{-- รถ --}}
                    <div>
                        <x-input-label for="vehicle_id" value="รถ (Vehicle)" />
                        <select id="vehicle_id" name="vehicle_id"
                            class="sp-select mt-1 w-full @error('vehicle_id') border-red-500 @enderror">
                            <option value="">-- เลือกรถ --</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>
                                    {{ $v->license_plate }}
                                    @if ($v->brand)
                                        — {{ $v->brand }}
                                    @endif
                                    @if ($v->user)
                                        ({{ $v->user->name }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
                    </div>

                    {{-- ลานจอด --}}
                    <div>
                        <x-input-label for="parking_lot_id" value="ลานจอด (Parking Lot)" />
                        <select id="parking_lot_id" name="parking_lot_id"
                            class="sp-select mt-1 w-full @error('parking_lot_id') border-red-500 @enderror"
                            x-model="lotId">
                            <option value="">-- เลือกลาน --</option>
                            @foreach ($lots as $lot)
                                <option value="{{ $lot->id }}" @selected(old('parking_lot_id') == $lot->id)>
                                    {{ $lot->name }} ({{ number_format($lot->hourly_rate, 2) }} ฿/ชม.)
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parking_lot_id')" class="mt-2" />
                    </div>

                    {{-- ช่องจอด --}}
                    <div>
                        <x-input-label for="parking_slot_id" value="ช่องจอด (Slot) — ไม่บังคับ" />
                        <select id="parking_slot_id" name="parking_slot_id"
                            class="sp-select mt-1 w-full @error('parking_slot_id') border-red-500 @enderror">
                            <option value="">-- ให้ระบบจัดให้อัตโนมัติ --</option>
                            <template x-for="slot in filteredSlots" :key="slot.id">
                                <option :value="slot.id" :selected="slot.id == {{ old('parking_slot_id', 0) }}"
                                    x-text="slot.slot_number">
                                </option>
                            </template>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">แสดงเฉพาะช่องที่ว่าง (available) ในลานที่เลือก</p>
                        <x-input-error :messages="$errors->get('parking_slot_id')" class="mt-2" />
                    </div>

                    {{-- เวลาเริ่ม --}}
                    <div>
                        <x-input-label for="reserve_start" value="เวลาเริ่ม (Reserve Start)" />
                        <x-text-input id="reserve_start" name="reserve_start" type="datetime-local"
                            class="mt-1 block w-full @error('reserve_start') border-red-500 @enderror"
                            value="{{ old('reserve_start', $exampleStart) }}" min="{{ $minDatetime }}" />
                        <p class="text-xs text-gray-500 mt-1">ตัวอย่าง:
                            {{ now()->addHour()->startOfHour()->format('d/m/Y H:i') }} น.</p>
                        <x-input-error :messages="$errors->get('reserve_start')" class="mt-2" />
                    </div>

                    {{-- หมายเหตุ: ระบบจะยกเลิกการจองอัตโนมัติหากไม่เช็คอินภายใน 1 ชั่วโมงหลังเวลาเริ่ม --}}
                    <div class="rounded-xl border border-blue-700/40 bg-blue-900/10 p-3 text-sm text-blue-300">
                        ระบบจะยกเลิกการจองอัตโนมัติหากไม่มีการเช็คอินภายใน 1 ชั่วโมงหลังเวลาเริ่ม
                    </div>

                    {{-- ค่าจอง (admin only) --}}
                    <div>
                        <x-input-label for="reservation_fee" value="ค่าจอง (Reservation Fee) ฿" />
                        <x-text-input id="reservation_fee" name="reservation_fee" type="number" step="0.01"
                            min="0" class="mt-1 block w-full @error('reservation_fee') border-red-500 @enderror"
                            value="{{ old('reservation_fee', 0) }}" />
                        <p class="text-xs text-gray-500 mt-1">ใส่ 0 หากไม่มีค่ามัดจำ</p>
                        <x-input-error :messages="$errors->get('reservation_fee')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="sp-btn sp-btn-primary sp-glow-btn flex-1 justify-center py-3">
                            สร้างการจอง
                        </button>
                        <a href="{{ route('admin.reservations.index') }}"
                            class="sp-btn sp-btn-outline flex-1 text-center py-3">ยกเลิก</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
