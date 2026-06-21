<x-guest-layout>
    <p class="mb-5 text-sm text-gray-400 leading-relaxed">
        กรอกอีเมลของคุณ แล้วเราจะส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปให้
    </p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('อีเมล')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required autofocus placeholder="your@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                ส่งลิงก์รีเซ็ตรหัสผ่าน
            </x-primary-button>
        </div>

        <p class="mt-5 text-center text-sm text-gray-400">
            <a href="{{ route('login') }}"
               class="text-red-400 hover:text-red-300 underline underline-offset-2 transition">
                ← กลับไปเข้าสู่ระบบ
            </a>
        </p>
    </form>
</x-guest-layout>
