{{-- แก้ไขรายการบัญชีดำ · ลบถาวรแยกไว้ท้ายหน้า (ระงับชั่วคราวใช้ช่อง "ใช้งาน" แทนได้) --}}
@use('App\Support\Format')

<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('admin.suspicious-vehicles.index') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> บัญชีดำ
        </a>
        <h1 class="mt-2 text-h1 text-fg">แก้ไขบัญชีดำ</h1>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <x-ui.plate :plate="$suspiciousVehicle->license_plate" :province="$suspiciousVehicle->plate_province" />
            <p class="text-label text-fg-2">เพิ่มโดย {{ $suspiciousVehicle->addedBy?->name ?? 'บัญชีถูกลบ' }} · {{ Format::short($suspiciousVehicle->created_at) }}</p>
        </div>

        @include('admin.suspicious-vehicles.partials.form', ['entry' => $suspiciousVehicle])

        <section aria-labelledby="delete-title" class="mt-10 rounded-card border border-danger/40 bg-surface p-5 shadow-1 sm:p-6">
            <h2 id="delete-title" class="text-h3 text-fg">ลบออกจากบัญชีดำ</h2>
            <p class="mt-1 text-label text-fg-2">ลบรายการนี้ถาวร กู้คืนไม่ได้ · ถ้าต้องการหยุดแจ้งเตือนชั่วคราว ให้ยกเลิก "ใช้งาน" แทน</p>
            <form method="POST" action="{{ route('admin.suspicious-vehicles.destroy', $suspiciousVehicle) }}" class="mt-4"
                data-confirm="ลบทะเบียน {{ $suspiciousVehicle->license_plate }} {{ $suspiciousVehicle->plate_province }} ออกจากบัญชีดำถาวร — กล้องจะไม่แจ้งเตือนรถคันนี้อีก"
                data-confirm-title="ลบออกจากบัญชีดำ?" data-confirm-label="ลบถาวร" data-confirm-tone="danger" data-confirm-cancel="เก็บไว้">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="danger">ลบถาวร</x-ui.button>
            </form>
        </section>
    </div>
</x-app-layout>
