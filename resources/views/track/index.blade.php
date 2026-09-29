{{--
    เช็คสถานะรถโดยไม่ต้องล็อกอิน — สำหรับคนขับที่เข้าลานแบบ Walk-in · กด Check-out และชำระก่อนสแกนออกได้ที่นี่ (§12.6)
    ยืนยันตัวด้วย ทะเบียน + จังหวัด + รหัสอ้างอิงที่ได้จากจอทางเข้าลาน
    การจองล่วงหน้าไม่ออกรหัส (§4.0.1) — เจ้าของบัญชีดูรายการของตัวเองในหน้าหลักได้อยู่แล้ว

    แสดงเฉพาะข้อมูลของรถคันนั้น ณ ตอนนี้ · ไม่แสดงชื่อเจ้าของ อีเมล หรือประวัติครั้งก่อน
    ต้องการ: $result (null = ยังไม่ได้ค้นหา)
--}}
@use('App\Support\Format')

<x-guest-layout title="เช็คสถานะรถ" description="ดูช่องจอดและค่าจอด ณ ตอนนี้ โดยไม่ต้องเข้าสู่ระบบ">
    @if ($result)
        @php
            $r = $result['reservation'];
            $log = $result['log'];
            $est = $result['exit']['charge'];
            $minutes = (int) $log->check_in_time->diffInMinutes(now());
        @endphp

        <div class="flex flex-col items-center gap-4">
            <x-ui.plate :plate="$result['plate']" :province="$r->plate_province" size="lg" />

            <p class="text-center text-label text-fg-2">
                {{ $r->parkingLot?->name }}
                @if ($r->parkingLot?->district) <br>{{ collect([$r->parkingLot->district, $r->parkingLot->province])->filter()->implode(' ') }} @endif
            </p>
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-4">
            <div>
                <dt class="text-caption text-fg-3">ช่องจอด</dt>
                <dd class="num mt-0.5 text-h2 text-fg">{{ $r->parkingSlot?->slot_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-fg-3">เข้าลานเมื่อ</dt>
                <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($log->check_in_time) }}</dd>
            </div>
            <div>
                <dt class="text-caption text-fg-3">จอดมาแล้ว</dt>
                <dd class="mt-0.5 font-semibold text-fg">{{ Format::duration($minutes) }}</dd>
            </div>
            <div>
                <dt class="text-caption text-fg-3">คิดเป็น</dt>
                <dd class="mt-0.5 font-semibold text-fg"><span class="tabular">{{ $est['total_hours'] }}</span> ชั่วโมง</dd>
            </div>
        </dl>

        {{-- ชำระก่อนออก (§12.6) — ปุ่มยืนยันตัวซ้ำด้วยทะเบียน + รหัสทุกครั้ง ไม่จำไว้ใน session --}}
        <div class="mt-6">
            @include('partials.checkout-panel', [
                'exit' => $result['exit'],
                'checkoutAction' => route('track.checkout'),
                'payAction' => route('track.pay'),
                'hidden' => $result['credentials'],
                'error' => $result['error'],
            ])
        </div>

        <div class="mt-4">
            <x-ui.button :href="route('track.show')" variant="ghost" class="w-full">ค้นหาคันอื่น</x-ui.button>
        </div>
    @else
        <form method="POST" action="{{ route('track.find') }}" class="flex flex-col gap-5">
            @csrf

            <x-ui.field label="เลขทะเบียน" for="plate_number" required :error="$errors->get('plate_number')">
                <x-ui.input id="plate_number" name="plate_number" :value="old('plate_number')" maxlength="20"
                    autocomplete="off" required data-plate-input placeholder="กข 1234"
                    :invalid="$errors->has('plate_number')" />
            </x-ui.field>

            <x-ui.field label="จังหวัดของป้ายทะเบียน" for="plate_province" required :error="$errors->get('plate_province')">
                <x-ui.select id="plate_province" name="plate_province" required placeholder="เลือกจังหวัด">
                    @foreach (config('thai_provinces') as $province)
                        <option value="{{ $province }}" @selected(old('plate_province') === $province)>{{ $province }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>

            <x-ui.field label="รหัสอ้างอิง" for="reference_code" required
                hint="รหัส 6 ตัวจากจอที่ทางเข้าลาน หรือสอบถามเจ้าหน้าที่"
                :error="$errors->get('reference_code')">
                <x-ui.input id="reference_code" name="reference_code" maxlength="6" required
                    autocomplete="off" autocapitalize="characters" spellcheck="false"
                    class="num uppercase" placeholder="ABC123" :invalid="$errors->has('reference_code')" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full">เช็คสถานะรถ</x-ui.button>
        </form>
    @endif

    <x-slot name="after">
        <p class="mt-6 text-center text-label text-fg-3">
            {{-- ลิงก์ที่อยู่กลางย่อหน้าต้องขีดเส้นใต้ตลอด ไม่ใช่แยกด้วยสีอย่างเดียว (axe: link-in-text-block) --}}
            มีบัญชีอยู่แล้ว? <a href="{{ route('login') }}" class="font-semibold text-primary-ink underline underline-offset-4">เข้าสู่ระบบ</a>
            เพื่อดูการจองและประวัติทั้งหมดของคุณ
        </p>
    </x-slot>
</x-guest-layout>
