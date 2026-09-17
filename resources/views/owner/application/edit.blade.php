{{-- แก้ไขคำขอที่ไม่ได้รับการอนุมัติแล้วส่งใหม่ --}}
<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('owner.application.show') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> สถานะคำขอ
        </a>
        <h1 class="mt-2 text-h1 text-fg">แก้ไขคำขอและส่งใหม่</h1>
        <p class="mt-1 text-fg-2">แก้ข้อมูลตามเหตุผลที่ผู้ดูแลระบบแจ้ง แล้วส่งให้พิจารณาอีกครั้ง</p>

        @if ($application->rejection_reason)
            <x-ui.alert tone="warning" title="เหตุผลที่ไม่อนุมัติครั้งก่อน" class="mt-6">{{ $application->rejection_reason }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert tone="danger" title="ยังส่งคำขอไม่ได้ กรุณาแก้ไข {{ count($errors->all()) }} รายการ" class="mt-4">ดูข้อความใต้ช่องที่มีกรอบสีแดง</x-ui.alert>
        @endif

        <form action="{{ route('owner.application.update') }}" method="POST" enctype="multipart/form-data" class="mt-6">
            @csrf
            @method('PUT')
            @include('owner.application.partials.form', ['application' => $application])
            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit">ส่งคำขอใหม่</x-ui.button>
                <x-ui.button variant="ghost" :href="route('owner.application.show')">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
