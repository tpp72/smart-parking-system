<x-guest-layout title="ตั้งรหัสผ่านใหม่" description="ตั้งรหัสผ่านใหม่แล้วเข้าสู่ระบบด้วยรหัสผ่านนั้น">
    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field label="อีเมล" for="email" required>
            <x-ui.input id="email" type="email" name="email" :value="old('email', $request->email)" required
                autocomplete="username" inputmode="email" />
        </x-ui.field>

        <x-ui.field label="รหัสผ่านใหม่" for="password" required hint="อย่างน้อย 8 ตัวอักษร">
            <x-password-input id="password" name="password" autocomplete="new-password" required autofocus />
        </x-ui.field>

        <x-ui.field label="ยืนยันรหัสผ่านใหม่" for="password_confirmation" required>
            <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" required />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full">บันทึกรหัสผ่านใหม่</x-ui.button>
    </form>
</x-guest-layout>
