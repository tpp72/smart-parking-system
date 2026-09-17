{{--
    เพิ่ม / แก้ไขลานจอด (ใช้ร่วม Owner และ Admin) — $lot = null คือเพิ่มใหม่ · $scope = 'owner' | 'admin'
    ลานไม่มีสถานะเปิด/ปิด มีเฉพาะการเปิดรับจองล่วงหน้า (reservations_enabled) — project-plan.md
--}}
@use('App\Support\Format')
@use('App\Models\Reservation')

@php
    $isEdit = $lot !== null;
    $value = fn (string $field, $default = null) => old($field, $lot?->{$field} ?? $default);
    $provinces = collect(config('thai_provinces'));
    $currentProvince = $value('province');
    if ($currentProvince && ! $provinces->contains($currentProvince)) {
        $provinces->prepend($currentProvince);
    }
    $activeReservations = $isEdit ? $lot->reservations()->whereIn('status', Reservation::ACTIVE_STATUSES)->count() : 0;
@endphp

<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route("{$scope}.parking-lots.index") }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> ลานจอด
        </a>
        <h1 class="mt-2 text-h1 text-fg">{{ $isEdit ? 'แก้ไขลานจอด' : 'เพิ่มลานจอด' }}</h1>
        <p class="mt-1 text-fg-2">{{ $isEdit ? $lot->name : 'หลังบันทึก ไปสร้างช่องจอดต่อ ลูกค้าจึงจะจองลานนี้ได้' }}</p>

        @if ($errors->any())
            <x-ui.alert tone="danger" title="ยังบันทึกไม่ได้ กรุณาแก้ไข {{ count($errors->all()) }} รายการ" class="mt-6">ดูข้อความใต้ช่องที่มีกรอบสีแดง</x-ui.alert>
        @endif

        <form method="POST" action="{{ $isEdit ? route("{$scope}.parking-lots.update", $lot->id) : route("{$scope}.parking-lots.store") }}" class="mt-6 flex flex-col gap-6">
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <section aria-labelledby="lot-info" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="lot-info" class="text-h3 text-fg">ข้อมูลลาน</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="ชื่อลาน" for="name" required class="sm:col-span-2">
                        <x-ui.input id="name" name="name" :value="$value('name')" required maxlength="255" placeholder="เช่น ลานจอดรถ ตัวอย่าง สาขาสยาม" />
                    </x-ui.field>
                    <x-ui.field label="ที่อยู่ (เลขที่ / ถนน)" for="address" class="sm:col-span-2">
                        <x-ui.input id="address" name="address" :value="$value('address')" maxlength="500" placeholder="เช่น 123/4 ถ.สุขุมวิท" />
                    </x-ui.field>
                    <x-ui.field label="แขวง / ตำบล / เขต" for="district">
                        <x-ui.input id="district" name="district" :value="$value('district')" maxlength="255" />
                    </x-ui.field>
                    <x-ui.field label="จังหวัด" for="province">
                        <x-ui.select id="province" name="province" placeholder="ไม่ระบุ">
                            @foreach ($provinces as $province)
                                <option value="{{ $province }}" @selected($currentProvince === $province)>{{ $province }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="จุดสังเกต / ใกล้กับ" for="landmark" class="sm:col-span-2">
                        <x-ui.input id="landmark" name="landmark" :value="$value('landmark')" maxlength="500" placeholder="เช่น ใกล้ BTS อโศก" />
                    </x-ui.field>
                    <x-ui.field label="หมายเหตุสถานที่" for="location" hint="เช่น ทางเข้าอยู่ด้านหลังอาคาร" class="sm:col-span-2">
                        <x-ui.textarea id="location" name="location" rows="2">{{ $value('location') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </section>

            <section aria-labelledby="lot-settings" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="lot-settings" class="text-h3 text-fg">ค่าจอดและการรับจอง</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="ค่าจอดต่อชั่วโมง (บาท)" for="hourly_rate" required
                        hint="มัดจำและส่วนลดการจองเท่ากับค่าจอด 1 ชั่วโมง · การจองเดิมและรถที่เข้าลานแล้วยังใช้อัตราเดิม">
                        <x-ui.input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" :value="$value('hourly_rate')" required inputmode="decimal" numeric />
                    </x-ui.field>
                    <x-ui.field label="จำนวนช่องจอด" for="total_slots" required hint="สร้างช่องจอดจริงได้ที่หน้าช่องจอด">
                        <x-ui.input id="total_slots" name="total_slots" type="number" min="0" :value="$value('total_slots', 0)" required inputmode="numeric" numeric />
                    </x-ui.field>
                    <div class="sm:col-span-2">
                        <input type="hidden" name="reservations_enabled" value="0">
                        <x-ui.checkbox name="reservations_enabled" id="reservations_enabled" value="1"
                            :checked="(bool) $value('reservations_enabled', true)"
                            label="เปิดรับจองล่วงหน้า"
                            description="ปิดแล้วลูกค้าจองลานนี้ล่วงหน้าไม่ได้ แต่รถ Walk-in ยังเข้าได้ และการจองที่มีอยู่ยังใช้ได้ตามปกติ" />
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">{{ $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มลานจอด' }}</x-ui.button>
                <x-ui.button variant="ghost" :href="route($scope.'.parking-lots.index')">ยกเลิก</x-ui.button>
            </div>
        </form>

        @if ($isEdit)
            <section aria-labelledby="lot-delete" class="mt-10 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="lot-delete" class="text-h3 text-fg">ลบลานจอด</h2>
                <p class="mt-1 text-label text-fg-2">
                    ลบถาวร พร้อมช่องจอด ประวัติการจอง การจอด ผลสแกน และรายการชำระเงินทั้งหมดของลานนี้ กู้คืนไม่ได้
                </p>
                @if ($activeReservations > 0)
                    <x-ui.alert tone="warning" class="mt-4">
                        ยังมีการจองที่ยังไม่จบ {{ $activeReservations }} รายการ — ปิดรับจองล่วงหน้า แล้วรอให้การจองเหล่านั้นเสร็จสิ้นก่อนจึงจะลบได้
                    </x-ui.alert>
                @else
                    <form method="POST" action="{{ route("{$scope}.parking-lots.destroy", $lot->id) }}" class="mt-4"
                        data-confirm="ลบ {{ $lot->name }} ถาวร — ช่องจอดและประวัติทั้งหมดของลานนี้จะถูกลบไปด้วย กู้คืนไม่ได้"
                        data-confirm-title="ลบลานจอดถาวร?" data-confirm-label="ลบลานจอด" data-confirm-tone="danger" data-confirm-cancel="เก็บลานไว้">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="danger">ลบลานจอด</x-ui.button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
