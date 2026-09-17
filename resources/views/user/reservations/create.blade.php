{{--
    จองที่จอด — เลือกลาน + เวลาเริ่ม (ล่วงหน้าไม่เกิน 1 วัน) + ข้อมูลรถ · ช่องจอดระบบเลือกให้หลังยืนยันรับมัดจำ
    สรุปยอดด้านข้าง: มัดจำ = ค่าจอด 1 ชม. · ส่วนลดการจอง = ค่าจอด 1 ชม. (หักตอน Check-out) — project-plan.md §3.1
--}}
@use('App\Support\Format')

@php
    $exampleStart = now()->addHour()->startOfHour();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10"
        x-data="{
            allLots: {{ Js::from($lots) }},
            lotId: '{{ old('parking_lot_id', $selectedLotId) }}',
            get lot() { return this.allLots.find(l => String(l.id) === String(this.lotId)) ?? null; },
            baht(v) { return '฿' + Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        }">
        <div class="mb-8">
            <h1 class="text-h1 text-fg">จองที่จอด</h1>
            <p class="mt-1 text-fg-2">จองล่วงหน้าได้ไม่เกิน 1 วัน ช่องจอดระบบเลือกให้หลังเจ้าหน้าที่ยืนยันรับมัดจำ</p>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" title="ยังจองไม่ได้ กรุณาแก้ไข {{ count($errors->all()) }} รายการ" class="mb-6">
                ดูข้อความใต้ช่องที่มีกรอบสีแดง
            </x-ui.alert>
        @endif

        @if ($lots->isEmpty())
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state title="ยังไม่มีลานที่จองได้" description="ขณะนี้ไม่มีลานที่เปิดรับจองและมีช่องว่าง ลองใหม่ภายหลัง">
                    <x-ui.button variant="secondary" :href="route('user.dashboard')">กลับหน้าหลัก</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            <form method="POST" action="{{ route('user.reservations.store') }}"
                class="grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
                @csrf

                <div class="flex flex-col gap-6">
                    <section aria-labelledby="booking-when" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                        <h2 id="booking-when" class="text-h3 text-fg">ลานและเวลา</h2>

                        <div class="mt-5 flex flex-col gap-5">
                            <x-ui.field label="ลานจอด" for="parking_lot_id" required hint="แสดงเฉพาะลานที่เปิดรับจองและยังมีช่องว่าง">
                                <x-ui.select id="parking_lot_id" name="parking_lot_id" x-model="lotId" required placeholder="เลือกลานจอด">
                                    @foreach ($lots as $lot)
                                        <option value="{{ $lot->id }}" @selected(old('parking_lot_id', $selectedLotId) == $lot->id)>{{ $lot->name }} — {{ Format::baht($lot->hourly_rate) }}/ชม.</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>

                            <x-ui.field label="เวลาเริ่มจอด" for="reserve_start" required
                                hint="ต้องเป็นเวลาหลังจากนี้ และไม่เกิน {{ Format::short(now()->addDay()) }} · นำรถเข้าลานได้ภายใน 60 นาทีหลังเวลาเริ่ม">
                                <x-ui.input id="reserve_start" name="reserve_start" data-flatpickr="datetime" required
                                    :value="old('reserve_start', $exampleStart->format('Y-m-d\TH:i'))"
                                    data-min="{{ now()->format('Y-m-d\TH:i') }}"
                                    data-max="{{ now()->addDay()->subMinute()->format('Y-m-d\TH:i') }}" />
                            </x-ui.field>
                        </div>
                    </section>

                    <section aria-labelledby="booking-car" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                        <h2 id="booking-car" class="text-h3 text-fg">ข้อมูลรถ</h2>
                        <p class="mt-1 text-label text-fg-3">แก้ไขได้ภายหลังจนกว่ารถจะ Check-in</p>
                        <div class="mt-5">
                            @include('user.reservations.partials.vehicle-fields', ['plateNumber' => null, 'plateProvince' => null, 'brand' => null, 'color' => null])
                        </div>
                    </section>
                </div>

                {{-- สรุปยอด (ใบเสร็จ) --}}
                <aside aria-labelledby="booking-summary" class="rounded-card border border-line bg-surface shadow-1 lg:sticky lg:top-20">
                    <h2 id="booking-summary" class="border-b border-line px-5 py-4 text-h3 text-fg">สรุปการจอง</h2>

                    <div class="px-5 py-4">
                        <p x-show="!lot" class="text-label text-fg-2">เลือกลานจอดเพื่อดูยอดมัดจำ</p>

                        <dl x-show="lot" x-cloak class="flex flex-col gap-3 text-label">
                            <div class="flex justify-between gap-3">
                                <dt class="text-fg-2">ค่าจอดต่อชั่วโมง</dt>
                                <dd class="num text-fg" x-text="lot && baht(lot.hourly_rate)"></dd>
                            </div>
                            <div class="border-t border-dashed border-field pt-3">
                                <div class="flex justify-between gap-3 font-semibold">
                                    <dt class="text-fg">มัดจำ</dt>
                                    <dd class="num text-h3 text-fg" x-text="lot && baht(lot.hourly_rate)"></dd>
                                </div>
                                <p class="mt-1 text-caption text-fg-3">เท่ากับค่าจอด 1 ชั่วโมง · การจองยืนยันเมื่อเจ้าหน้าที่ยืนยันรับเงิน</p>
                            </div>
                            <div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-fg-2">ส่วนลดการจอง</dt>
                                    <dd class="num text-fg" x-text="lot && ('−' + baht(lot.hourly_rate))"></dd>
                                </div>
                                <p class="mt-1 text-caption text-fg-3">หักจากค่าจอดตอน Check-out ต่อจากมัดจำ ยอดสุทธิไม่ติดลบ</p>
                            </div>
                        </dl>
                    </div>

                    <div class="border-t border-line px-5 py-4">
                        <ul class="flex flex-col gap-1.5 text-caption text-fg-2">
                            <li>ยกเลิกหลังยืนยันรับมัดจำแล้ว มัดจำไม่คืน</li>
                            <li>ไม่ Check-in ภายใน 60 นาทีหลังเวลาเริ่ม การจองหมดอายุ</li>
                        </ul>
                        <x-ui.button type="submit" class="mt-4 w-full">ยืนยันการจอง</x-ui.button>
                        <x-ui.button variant="ghost" :href="route('user.dashboard')" class="mt-2 w-full">ยกเลิก</x-ui.button>
                    </div>
                </aside>
            </form>
        @endif
    </div>
</x-app-layout>
