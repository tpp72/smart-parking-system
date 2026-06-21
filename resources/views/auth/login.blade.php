<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('อีเมล')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="old('email')" required autofocus autocomplete="username"
                placeholder="your@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('รหัสผ่าน')" />
            <x-password-input id="password" name="password" autocomplete="current-password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember"
                    class="w-4 h-4 rounded border border-red-900 bg-black/60 text-red-600
                           checked:bg-red-600 checked:border-red-600
                           focus:ring-2 focus:ring-red-500/60 focus:ring-offset-0
                           transition duration-150">
                <span class="text-sm text-gray-400">จดจำฉัน</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm text-red-400 hover:text-red-300 underline underline-offset-2
                          focus:outline-none focus:ring-2 focus:ring-red-500/60 rounded transition">
                    ลืมรหัสผ่าน?
                </a>
            @endif
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                เข้าสู่ระบบ
            </x-primary-button>
        </div>

        <p class="mt-5 text-center text-sm text-gray-400">
            ยังไม่มีบัญชี?
            <a href="{{ route('register') }}"
               class="text-red-400 hover:text-red-300 font-semibold underline underline-offset-2 transition">
                สมัครสมาชิก
            </a>
        </p>
    </form>
</x-guest-layout>
