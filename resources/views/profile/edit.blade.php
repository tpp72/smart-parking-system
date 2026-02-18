<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-extrabold text-2xl text-red-500 sp-glow-text leading-tight">
                โปรไฟล์
            </h2>
            <a href="{{ route('dashboard') }}" class="sp-btn sp-btn-outline">กลับไป Dashboard</a>
        </div>
    </x-slot>

    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6">

            {{-- Warning from redirect (เช่น force reset) --}}
            @if (session('warning'))
                <div class="sp-card rounded-2xl p-4 border border-yellow-600/40 bg-yellow-900/20">
                    <p class="text-yellow-200 font-semibold">{{ session('warning') }}</p>
                </div>
            @endif

            {{-- Force reset alert --}}
            @if (auth()->user()->force_password_reset)
                <div class="sp-card rounded-2xl p-5 border border-red-700/50 bg-red-900/20">
                    <div class="flex items-start gap-3">
                        <div class="text-red-400 text-xl">⚠</div>
                        <div>
                            <p class="font-extrabold text-red-300 sp-glow-text">
                                กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งานระบบต่อ
                            </p>
                            <p class="text-red-200 text-sm mt-1">
                                บัญชีของคุณถูกรีเซ็ตรหัสผ่านโดยผู้ดูแลระบบ
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6">
                <h3 class="text-xl font-extrabold mb-4">ข้อมูลโปรไฟล์</h3>
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <h3 class="text-xl font-extrabold mb-4">เปลี่ยนรหัสผ่าน</h3>
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <h3 class="text-xl font-extrabold mb-4 text-red-200">ลบบัญชี</h3>
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
