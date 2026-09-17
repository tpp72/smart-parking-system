<x-guest-layout title="ลืมรหัสผ่าน" description="กรอกอีเมลของบัญชี ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้">
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.field label="อีเมล" for="email" required>
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus
                autocomplete="username" inputmode="email" placeholder="name@example.com" />
        </x-ui.field>

        <x-ui.button type="submit" class="w-full">ส่งลิงก์ตั้งรหัสผ่านใหม่</x-ui.button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-center">
        <a href="{{ route('login') }}" class="font-semibold text-primary-ink underline-offset-4 hover:underline">กลับไปหน้าเข้าสู่ระบบ</a>
    </p>
</x-guest-layout>
