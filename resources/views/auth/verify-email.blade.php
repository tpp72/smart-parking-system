<x-guest-layout title="ยืนยันอีเมล" description="ต้องยืนยันอีเมลก่อนจึงจะใช้การจอง การสแกน และการแจ้งเตือนได้">
    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tone="success" class="mb-6">ส่งลิงก์ยืนยันใหม่ไปที่ {{ auth()->user()->email }} แล้ว</x-ui.alert>
    @endif

    <p class="text-fg-2">
        ระบบส่งลิงก์ยืนยันไปที่
        <span class="font-semibold text-fg">{{ auth()->user()->email }}</span>
        เปิดอีเมลแล้วกดลิงก์เพื่อเริ่มใช้งาน หากไม่พบ ให้ดูในโฟลเดอร์จดหมายขยะ
    </p>

    <div class="mt-6 flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" class="w-full">ส่งลิงก์ยืนยันอีกครั้ง</x-ui.button>
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
