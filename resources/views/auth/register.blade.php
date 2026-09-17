<x-guest-layout title="สมัครสมาชิก" description="สมัครแล้วยืนยันอีเมลก่อน จึงจะจองที่จอดได้">
    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.field label="ชื่อ-นามสกุล" for="name" required>
            <x-ui.input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field label="อีเมล" for="email" required hint="ใช้รับลิงก์ยืนยันบัญชีและลิงก์ตั้งรหัสผ่านใหม่">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required
                autocomplete="username" inputmode="email" placeholder="name@example.com" />
        </x-ui.field>

        <x-ui.field label="รหัสผ่าน" for="password" required hint="อย่างน้อย 8 ตัวอักษร">
            <x-password-input id="password" name="password" autocomplete="new-password" required />
        </x-ui.field>

        <x-ui.field label="ยืนยันรหัสผ่าน" for="password_confirmation" required>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full">สมัครสมาชิก</x-ui.button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-center text-fg-2">
        มีบัญชีอยู่แล้ว?
        <a href="{{ route('login') }}" class="font-semibold text-primary-ink underline-offset-4 hover:underline">เข้าสู่ระบบ</a>
    </p>
</x-guest-layout>
