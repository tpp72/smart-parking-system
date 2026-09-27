{{--
    ยืนยันก่อนผูกทะเบียนกับบัญชี — มาได้เฉพาะหลังเช็คสถานะรถด้วยรหัสอ้างอิงสำเร็จในรอบนี้
    ต้องการ: $plate, $province, $owner (บัญชีที่ผูกทะเบียนนี้ไว้แล้ว หรือ null)
--}}
<x-app-layout>
    <div class="mx-auto max-w-lg px-4 py-8 sm:px-6 lg:py-10">
        <h1 class="text-h1 text-fg">ผูกรถกับบัญชี</h1>
        <p class="mt-1 text-fg-2">ครั้งต่อไปที่รถคันนี้เข้าลานโดยไม่ได้จอง ระบบจะแจ้งเตือนคุณและแสดงรถในหน้าหลัก</p>

        <div class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
            <div class="flex justify-center">
                <x-ui.plate :plate="$plate" :province="$province" size="lg" />
            </div>

            <x-ui.error id="claim-error" :messages="$errors->get('plate')" class="mt-4" />

            @if ($owner && $owner->id === auth()->id())
                <p class="mt-5 text-center text-fg-2">รถคันนี้ผูกกับบัญชีของคุณอยู่แล้ว</p>
                <x-ui.button :href="route('profile.edit')" class="mt-5 w-full">ไปที่โปรไฟล์</x-ui.button>
            @elseif ($owner)
                <x-ui.alert tone="warning" title="ทะเบียนนี้ถูกผูกกับบัญชีอื่นแล้ว" class="mt-5">
                    1 ทะเบียนผูกได้กับบัญชีเดียว หากเป็นรถของคุณ กรุณาติดต่อผู้ดูแลระบบ
                </x-ui.alert>
                <x-ui.button :href="route('track.show')" variant="secondary" class="mt-5 w-full">กลับไปหน้าเช็คสถานะรถ</x-ui.button>
            @else
                <ul class="mt-5 flex flex-col gap-2 text-label text-fg-2">
                    <li>· ได้รับการแจ้งเตือนเมื่อรถเข้าและออกจากลาน</li>
                    <li>· เห็นรถในหน้าหลักและในประวัติการจอดของคุณ</li>
                    <li>· ไม่ต้องใช้รหัสอ้างอิงอีก</li>
                </ul>

                <p class="mt-4 text-caption text-fg-3">
                    การผูกรถไม่เปลี่ยนวิธีคิดค่าจอด — รถที่ไม่ได้จองล่วงหน้ายังคิดค่าจอดเต็มจำนวนตามเวลาจริง ไม่มีมัดจำและไม่มีส่วนลด
                </p>

                <form method="POST" action="{{ route('track.claim') }}" class="mt-5 flex flex-col gap-3">
                    @csrf
                    <x-ui.button type="submit" class="w-full">ผูกรถคันนี้กับบัญชีของฉัน</x-ui.button>
                    <x-ui.button :href="route('track.show')" variant="ghost" class="w-full">ไม่ใช่ตอนนี้</x-ui.button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
