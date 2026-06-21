<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <x-input-label for="name" :value="__('ชื่อ-นามสกุล')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                :value="old('name')" required autofocus autocomplete="name"
                placeholder="กรอกชื่อของคุณ" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('อีเมล')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required autocomplete="username"
                placeholder="your@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('รหัสผ่าน')" />
            <x-password-input id="password" name="password" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('ยืนยันรหัสผ่าน')" />
            <x-password-input id="password_confirmation" name="password_confirmation"
                autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                สมัครสมาชิก
            </x-primary-button>
        </div>

        <p class="mt-5 text-center text-sm text-gray-400">
            มีบัญชีอยู่แล้ว?
            <a href="{{ route('login') }}"
               class="text-red-400 hover:text-red-300 font-semibold underline underline-offset-2 transition">
                เข้าสู่ระบบ
            </a>
        </p>
    </form>
</x-guest-layout>
