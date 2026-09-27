{{--
    เช็คสถานะรถโดยไม่ต้องล็อกอิน — สำหรับคนขับที่เข้าลานแบบ Walk-in หรือคนที่ไม่ได้ล็อกอินอยู่
    ยืนยันตัวด้วย ทะเบียน + จังหวัด + รหัสอ้างอิงที่ได้จากจอทางเข้าลาน

    แสดงเฉพาะข้อมูลของรถคันนั้น ณ ตอนนี้ · ไม่แสดงชื่อเจ้าของ อีเมล หรือประวัติครั้งก่อน
    ต้องการ: $result (null = ยังไม่ได้ค้นหา)
--}}
@use('App\Support\Format')

<x-guest-layout title="เช็คสถานะรถ" description="ดูช่องจอดและค่าจอด ณ ตอนนี้ โดยไม่ต้องเข้าสู่ระบบ">
    @if ($result)
        @php
            $r = $result['reservation'];
            $log = $result['log'];
            $est = $result['estimate'];
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

        <div class="mt-6 rounded-card border border-line bg-surface-2 p-4">
            <p class="flex items-baseline justify-between gap-3">
                <span class="text-label text-fg-2">ค่าจอด ณ ตอนนี้</span>
                <span class="num text-h2 text-fg">{{ Format::baht($est['total_amount']) }}</span>
            </p>
            @if ($est['deposit_deduction'] > 0 || $est['reservation_discount'] > 0)
                <p class="mt-2 text-caption text-fg-3">
                    ค่าจอด <span class="tabular">{{ Format::baht($est['parking_fee']) }}</span>
                    @if ($est['deposit_deduction'] > 0) · หักมัดจำ <span class="tabular">{{ Format::baht($est['deposit_deduction']) }}</span> @endif
                    @if ($est['reservation_discount'] > 0) · ส่วนลด <span class="tabular">{{ Format::baht($est['reservation_discount']) }}</span> @endif
                </p>
            @endif
            <p class="mt-2 text-caption text-fg-3">
                เป็นยอดประมาณการ คิดรายชั่วโมง ปัดขึ้น ขั้นต่ำ 1 ชั่วโมง · ยอดจริงคิดตอนรถออกจากลาน และชำระที่เจ้าหน้าที่
            </p>
        </div>

        <div class="mt-6 flex flex-col gap-3">
            @if (! $result['claimedBy'])
                {{-- ผูกรถกับบัญชีได้ เพราะเพิ่งพิสูจน์ด้วยรหัสอ้างอิงไปแล้วในรอบนี้ --}}
                <x-ui.button :href="route('track.claim.form')" class="w-full">บันทึกรถคันนี้ไว้ในบัญชี</x-ui.button>
                <p class="text-center text-caption text-fg-3">ครั้งต่อไปที่รถเข้าลานโดยไม่ได้จอง ระบบจะแจ้งเตือนคุณ ไม่ต้องใช้รหัสอีก</p>
            @elseif (auth()->check() && $result['claimedBy']->id === auth()->id())
                <p class="text-center text-caption text-fg-3">รถคันนี้ผูกกับบัญชีของคุณอยู่แล้ว</p>
            @endif

            <x-ui.button :href="route('track.show')" variant="secondary" class="w-full">ค้นหาคันอื่น</x-ui.button>
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
