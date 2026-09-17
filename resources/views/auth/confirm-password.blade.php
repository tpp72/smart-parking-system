<x-guest-layout title="ยืนยันรหัสผ่าน" description="ส่วนนี้ต้องยืนยันรหัสผ่านอีกครั้งก่อนดำเนินการต่อ">
    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.field label="รหัสผ่าน" for="password" required>
            <x-password-input id="password" name="password" autocomplete="current-password" required autofocus />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full">ยืนยัน</x-ui.button>
    </form>
</x-guest-layout>
