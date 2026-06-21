<x-guest-layout>
    <p class="mb-5 text-sm text-gray-400 leading-relaxed">
        ขอบคุณที่สมัครสมาชิก! กรุณายืนยันอีเมลของคุณโดยคลิกลิงก์ที่ส่งไปในอีเมล
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-xl border border-green-800 bg-green-900/20 px-4 py-3
                    text-sm font-semibold text-green-400">
            ส่งลิงก์ยืนยันอีเมลใหม่ไปให้คุณแล้ว
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                ส่งอีเมลยืนยันอีกครั้ง
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="text-sm text-gray-400 hover:text-red-400 underline underline-offset-2
                       focus:outline-none focus:ring-2 focus:ring-red-500/60 rounded transition">
                ออกจากระบบ
            </button>
        </form>
    </div>
</x-guest-layout>
