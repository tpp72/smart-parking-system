{{-- โปรไฟล์: ข้อมูลบัญชี · เปลี่ยนรหัสผ่าน · ลบบัญชี (เฉพาะ User — UserAccountService::selfDeletionBlocker) --}}
<x-app-layout>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-8">
            <h1 class="text-h1 text-fg">โปรไฟล์</h1>
            <p class="mt-1 text-fg-2">ข้อมูลบัญชี {{ \App\Support\Navigation::ROLE_LABELS[$user->role] ?? '' }} · {{ $user->email }}</p>
        </div>

        <div class="flex flex-col gap-4">
            @if ($user->force_password_reset)
                <x-ui.alert tone="warning" title="ต้องตั้งรหัสผ่านใหม่ก่อนใช้งานต่อ">
                    ผู้ดูแลระบบรีเซ็ตรหัสผ่านของบัญชีนี้ ตั้งรหัสผ่านใหม่ใน<a href="#update-password" class="font-semibold text-fg underline underline-offset-4">ส่วนเปลี่ยนรหัสผ่าน</a>
                    แล้วจึงใช้เมนูอื่นได้
                </x-ui.alert>
            @elseif (session('warning'))
                <x-ui.alert tone="warning">{{ session('warning') }}</x-ui.alert>
            @endif

            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
