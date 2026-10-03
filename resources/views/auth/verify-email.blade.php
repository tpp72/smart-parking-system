@php
    // ส่งลิงก์ได้ครั้งละ 1 ครั้งต่อ 60 วินาที (User::VERIFICATION_COOLDOWN) — นับรวมตอนสมัคร
    $cooldown = auth()->user()->verificationCooldownRemaining();
@endphp

<x-guest-layout title="ยืนยันอีเมล" description="ต้องยืนยันอีเมลก่อนจึงจะใช้การจอง การสแกน และการแจ้งเตือนได้">
    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tone="success" class="mb-6">ส่งลิงก์ยืนยันใหม่ไปที่ {{ auth()->user()->email }} แล้ว</x-ui.alert>
    @elseif (session('verification-cooldown'))
        <x-ui.alert tone="warning" class="mb-6">เพิ่งส่งลิงก์ไปเมื่อสักครู่ — ส่งใหม่ได้อีกครั้งในอีก {{ session('verification-cooldown') }} วินาที</x-ui.alert>
    @endif

    <p class="text-fg-2">
        ระบบส่งลิงก์ยืนยันไปที่
        <span class="font-semibold text-fg">{{ auth()->user()->email }}</span>
        เปิดอีเมลแล้วกดลิงก์เพื่อเริ่มใช้งาน หากไม่พบ ให้ดูในโฟลเดอร์จดหมายขยะ
    </p>

    <div class="mt-6 flex flex-col gap-3">
        {{-- นับถอยหลังบอกผู้ใช้เท่านั้น — เซิร์ฟเวอร์ตรวจ cooldown เองทุกครั้งที่กด --}}
        <form method="POST" action="{{ route('verification.send') }}"
            x-data="{ left: {{ $cooldown }} }"
            x-init="if (left > 0) { const t = setInterval(() => { if (--left <= 0) clearInterval(t) }, 1000) }">
            @csrf
            <x-ui.button type="submit" class="w-full" :disabled="$cooldown > 0" x-bind:disabled="left > 0">
                <span x-show="left <= 0" @if ($cooldown > 0) style="display: none" @endif>ส่งลิงก์ยืนยันอีกครั้ง</span>
                <span x-show="left > 0" @if ($cooldown <= 0) style="display: none" @endif>
                    ส่งอีกครั้งได้ใน <span class="num" x-text="left">{{ $cooldown }}</span> วินาที
                </span>
            </x-ui.button>
        </form>

        <div class="grid grid-cols-2 gap-3">
            <x-ui.button variant="secondary" :href="route('profile.edit')">แก้ไขอีเมล</x-ui.button>
            <form method="POST" action="{{ route('logout') }}" data-no-busy>
                @csrf
                <x-ui.button type="submit" variant="ghost" class="w-full">ออกจากระบบ</x-ui.button>
            </form>
        </div>
    </div>
</x-guest-layout>
