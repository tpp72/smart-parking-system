{{-- เพิ่มทะเบียนเข้าบัญชีดำ (รายการกลางของทั้งระบบ ใช้กับกล้องทุกลาน) --}}
<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('admin.suspicious-vehicles.index') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> บัญชีดำ
        </a>
        <h1 class="mt-2 text-h1 text-fg">เพิ่มเข้าบัญชีดำ</h1>
        <p class="mt-1 text-fg-2">ใช้กับกล้องทุกลานในระบบ เมื่อพบรถคันนี้ ระบบแจ้งเจ้าของลานและผู้ดูแลระบบ</p>

        @include('admin.suspicious-vehicles.partials.form', ['entry' => null])
    </div>
</x-app-layout>
