{{-- แก้ไขข้อมูลรถของการจอง — ได้เฉพาะ ทะเบียน / จังหวัด / ยี่ห้อ / สี และเฉพาะก่อน Check-in --}}
@use('App\Support\Format')

<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('user.reservations.index') }}"
            class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> การจองของฉัน
        </a>

        <div class="mb-6 mt-2">
            <h1 class="text-h1 text-fg">แก้ไขข้อมูลรถ</h1>
            <p class="mt-1 text-fg-2">แก้ได้เฉพาะข้อมูลรถและจนกว่ารถจะ Check-in · ลานและเวลาเริ่มจองแก้ไม่ได้</p>
        </div>

        {{-- สรุปการจองที่กำลังแก้ --}}
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-card border border-line bg-surface-2 px-5 py-4 text-label sm:grid-cols-4">
            <div class="col-span-2 sm:col-span-1">
                <dt class="text-caption text-fg-3">การจอง</dt>
                <dd class="num mt-0.5 font-semibold text-fg">#{{ $reservation->id }}</dd>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <dt class="text-caption text-fg-3">ลานจอด</dt>
                <dd class="mt-0.5 font-semibold text-fg">{{ $reservation->parkingLot?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-fg-3">เวลาเริ่มจอง</dt>
                <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($reservation->reserve_start) }}</dd>
            </div>
            <div>
                <dt class="text-caption text-fg-3">สถานะ</dt>
                <dd class="mt-1"><x-ui.status type="reservation" :value="$reservation->status" audience="user" /></dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('user.reservations.update-plate', $reservation) }}"
            class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
            @csrf
            @method('PATCH')

            @include('user.reservations.partials.vehicle-fields', [
                'plateNumber' => $plateNumber,
                'plateProvince' => $plateProvince,
                'brand' => $reservation->brand,
                'color' => $reservation->color,
            ])

            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-ui.button type="submit">บันทึกข้อมูลรถ</x-ui.button>
                <x-ui.button variant="ghost" :href="route('user.reservations.index')">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
