<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">จองที่จอดรถ</h1>
                <p class="text-gray-300 mt-1">กรอกป้ายทะเบียน เลือกลาน และเวลาที่ต้องการ</p>
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
                allLots: {{ Js::from($lots) }},
                lotId: '{{ old('parking_lot_id') }}',
                get filteredSlots() {
                    if (!this.lotId) return [];
                    return this.allSlots.filter(s => String(s.parking_lot_id) === String(this.lotId));
                },
                get deposit() {
                    if (!this.lotId) return null;
                    const lot = this.allLots.find(l => String(l.id) === String(this.lotId));
                    return lot ? parseFloat(lot.hourly_rate).toFixed(2) : null;
                }
            }">

                <form method="POST" action="{{ route('user.reservations.store') }}" class="space-y-5">
                    @csrf

                    {{-- ป้ายทะเบียนรถ --}}
                    <div>
                        <x-input-label value="ป้ายทะเบียนรถ" />
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <div>
                                <x-text-input id="plate_number" name="plate_number" type="text"
                                    class="block w-full uppercase tracking-widest @error('plate_number') border-red-500 @enderror"
                                    value="{{ old('plate_number') }}"
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
                                        <option value="{{ $province }}" @selected(old('plate_province') === $province)>
                                            {{ $province }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('plate_province')" class="mt-2" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">กรอกป้ายทะเบียนรถที่จะนำมาจอด (แก้ไขได้ภายหลัง ก่อนเช็คอิน)</p>
                    </div>

                    {{-- ยี่ห้อ/สีรถ --}}
                    <div>
                        <x-input-label value="ยี่ห้อ / สีรถ" />
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <div>
                                <x-text-input id="brand" name="brand" type="text"
                                    class="block w-full @error('brand') border-red-500 @enderror"
                                    value="{{ old('brand') }}"
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
                                        <option value="{{ $c }}" @selected(old('color') === $c)>{{ $c }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('color')" class="mt-2" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">กรอกยี่ห้อและสีรถที่จะนำมาจอด (แก้ไขได้ภายหลัง ก่อนเช็คอิน)</p>
                    </div>

                    {{-- ลานจอด --}}
                    <div>
                        <x-input-label for="parking_lot_id" value="ลานจอด (Parking Lot)" />
                        @if ($lots->isEmpty())
                            <div class="mt-2 rounded-xl border border-yellow-700/40 bg-yellow-900/10 p-3">
                                <p class="text-yellow-300 text-sm">ขณะนี้ยังไม่มีลานจอดที่เปิดรับจองล่วงหน้า</p>
                            </div>
                        @else
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
                        @endif
                        <x-input-error :messages="$errors->get('parking_lot_id')" class="mt-2" />
                        <div x-show="deposit !== null" x-cloak
                             class="mt-2 rounded-xl border border-yellow-700/40 bg-yellow-900/10 p-3 text-sm text-yellow-300">
                            ค่ามัดจำ: <strong>฿<span x-text="deposit"></span></strong> (1 ชั่วโมง)
                            — หักจากค่าจอดเมื่อ Check-Out
                        </div>
                    </div>

                    {{-- ช่องจอด (filter by lot) --}}
                    <div>
                        <x-input-label for="parking_slot_id" value="ช่องจอด — ไม่บังคับ" />
                        <select id="parking_slot_id" name="parking_slot_id"
                            class="sp-select mt-1 w-full @error('parking_slot_id') border-red-500 @enderror">
                            <option value="">-- ให้ระบบจัดให้ --</option>
                            <template x-for="slot in filteredSlots" :key="slot.id">
                                <option :value="slot.id" :selected="slot.id == {{ old('parking_slot_id', 0) }}"
                                    x-text="slot.slot_number">
                                </option>
                            </template>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">แสดงเฉพาะช่องที่ว่างในลานที่เลือก</p>
                        <x-input-error :messages="$errors->get('parking_slot_id')" class="mt-2" />
                    </div>

                    {{-- เวลาเริ่ม --}}
                    @php
                        $minDatetime  = now()->format('Y-m-d\TH:i');
                        $exampleStart = now()->addHour()->startOfHour()->format('Y-m-d\TH:i');
                    @endphp
                    <div>
                        <x-input-label for="reserve_start" value="เวลาเริ่ม (Reserve Start)" />
                        <x-text-input id="reserve_start" name="reserve_start" type="text"
                            data-flatpickr="datetime"
                            class="mt-1 block w-full @error('reserve_start') border-red-500 @enderror"
                            value="{{ old('reserve_start', $exampleStart) }}" min="{{ $minDatetime }}" />
                        <p class="text-xs text-gray-500 mt-1">
                            ตัวอย่าง: {{ now()->addHour()->startOfHour()->format('d/m/Y H:i') }} น. — คลิกช่องด้านบนเพื่อเลือกจากปฏิทิน
                        </p>
                        <x-input-error :messages="$errors->get('reserve_start')" class="mt-2" />
                    </div>

                    <div class="rounded-xl border border-blue-700/40 bg-blue-900/10 p-3 text-sm text-blue-300">
                        การจองจะถูกยกเลิกอัตโนมัติหากไม่มีการเช็คอินภายใน 1 ชั่วโมงหลังเวลาที่จอง
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="sp-btn sp-btn-primary sp-glow-btn flex-1 justify-center py-3"
                            @if($lots->isEmpty()) disabled @endif>
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
