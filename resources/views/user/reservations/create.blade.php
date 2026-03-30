<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">จองที่จอดรถ (New Reservation)</h1>
                <p class="text-gray-300 mt-1">เลือกรถ ลาน ช่อง และช่วงเวลาที่ต้องการ</p>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-6 border border-red-600/40">
                    <ul class="text-red-300 text-sm space-y-1">
                        @foreach ($errors->all() as $e)
                            <li>• {{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6" x-data="{
                allSlots: {{ Js::from($slots) }},
                lotId: '{{ old('parking_lot_id') }}',
                get filteredSlots() {
                    if (!this.lotId) return [];
                    return this.allSlots.filter(s => String(s.parking_lot_id) === String(this.lotId));
                }
            }">

                <form method="POST" action="{{ route('user.reservations.store') }}" class="space-y-5">
                    @csrf

                    {{-- รถของฉัน --}}
                    <div>
                        <x-input-label for="vehicle_id" value="รถของฉัน (My Vehicle)" />
                        @if ($vehicles->isEmpty())
                            <div class="mt-2 rounded-xl border border-red-700/40 bg-red-900/10 p-3 flex items-center justify-between gap-3">
                                <p class="text-red-300 text-sm">ยังไม่มีรถในระบบ — ต้องเพิ่มรถก่อนจึงจะจองได้</p>
                                <a href="{{ route('user.vehicles.create') }}"
                                    class="text-xs shrink-0 sp-btn sp-btn-outline px-3 py-1.5">+ เพิ่มรถ</a>
                            </div>
                        @else
                            <select id="vehicle_id" name="vehicle_id"
                                class="sp-select mt-1 w-full @error('vehicle_id') border-red-500 @enderror">
                                <option value="">-- เลือกรถ --</option>
                                @foreach ($vehicles as $v)
                                    <option value="{{ $v->id }}" @selected(old('vehicle_id') == $v->id)>
                                        {{ $v->license_plate }} — {{ $v->brand }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
                        @endif
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
                                    {{ $lot->name }}
                                    ({{ number_format($lot->hourly_rate, 2) }} ฿/ชม.)
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parking_lot_id')" class="mt-2" />
                    </div>

                    {{-- ช่องจอด (filter by lot) --}}
                    <div>
                        <x-input-label for="parking_slot_id" value="ช่องจอด (Slot) — ไม่บังคับ" />
                        <select id="parking_slot_id" name="parking_slot_id"
                            class="sp-select mt-1 w-full @error('parking_slot_id') border-red-500 @enderror">
                            <option value="">-- ให้ระบบจัดให้ --</option>
                            <template x-for="slot in filteredSlots" :key="slot.id">
                                <option :value="slot.id" :selected="slot.id == {{ old('parking_slot_id', 0) }}"
                                    x-text="slot.slot_number">
                                </option>
                            </template>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">แสดงเฉพาะช่องที่ว่าง (available) ในลานที่เลือก</p>
                        <x-input-error :messages="$errors->get('parking_slot_id')" class="mt-2" />
                    </div>

                    {{-- เวลาเริ่ม --}}
                    @php
                        $minDatetime = now()->format('Y-m-d\TH:i');
                        $exampleStart = now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
                        $exampleEnd = now()->addHours(3)->startOfHour()->format('Y-m-d\TH:i');
                    @endphp
                    <div>
                        <x-input-label for="reserve_start" value="เวลาเริ่ม (Reserve Start)" />
                        <x-text-input id="reserve_start" name="reserve_start" type="datetime-local"
                            class="mt-1 block w-full @error('reserve_start') border-red-500 @enderror"
                            value="{{ old('reserve_start', $exampleStart) }}" min="{{ $minDatetime }}" />
                        <p class="text-xs text-gray-500 mt-1">ตัวอย่าง:
                            {{ now()->addHour()->startOfHour()->format('d/m/Y H:i') }} น.</p>
                        <x-input-error :messages="$errors->get('reserve_start')" class="mt-2" />
                    </div>

                    {{-- เวลาสิ้นสุด --}}
                    <div>
                        <x-input-label for="reserve_end" value="เวลาสิ้นสุด (Reserve End)" />
                        <x-text-input id="reserve_end" name="reserve_end" type="datetime-local"
                            class="mt-1 block w-full @error('reserve_end') border-red-500 @enderror"
                            value="{{ old('reserve_end', $exampleEnd) }}" min="{{ $minDatetime }}" />
                        <p class="text-xs text-gray-500 mt-1">ตัวอย่าง:
                            {{ now()->addHours(3)->startOfHour()->format('d/m/Y H:i') }} น. (ต้องหลังเวลาเริ่มเสมอ)</p>
                        <x-input-error :messages="$errors->get('reserve_end')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="sp-btn sp-btn-primary sp-glow-btn flex-1 justify-center py-3"
                            @if ($vehicles->isEmpty()) disabled @endif>
                            ยืนยันการจอง
                        </button>
                        <a href="{{ route('user.reservations.index') }}"
                            class="sp-btn sp-btn-outline flex-1 text-center py-3">ยกเลิก</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
