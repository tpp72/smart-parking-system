<x-guest-layout>
    <p class="mb-5 text-sm text-gray-400 leading-relaxed">
        พื้นที่นี้ต้องยืนยันรหัสผ่านก่อนดำเนินการต่อ
    </p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div>
            <x-input-label for="password" :value="__('รหัสผ่าน')" />
            <x-password-input id="password" name="password" autocomplete="current-password" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                ยืนยัน
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
