{{-- สมัครเป็นเจ้าของลาน — ผู้ดูแลระบบพิจารณาและแจ้งผลผ่านการแจ้งเตือน --}}
<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <h1 class="text-h1 text-fg">สมัครเป็นเจ้าของลาน</h1>
        <p class="mt-1 text-fg-2">กรอกข้อมูลผู้สมัครและลานจอด ผู้ดูแลระบบจะพิจารณาและแจ้งผลผ่านการแจ้งเตือน</p>

        @if ($errors->any())
            <x-ui.alert tone="danger" title="ยังส่งคำขอไม่ได้ กรุณาแก้ไข {{ count($errors->all()) }} รายการ" class="mt-6">ดูข้อความใต้ช่องที่มีกรอบสีแดง</x-ui.alert>
        @endif

        <form action="{{ route('owner.application.store') }}" method="POST" enctype="multipart/form-data" class="mt-6">
            @csrf
            @include('owner.application.partials.form', ['application' => null])
            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit">ส่งคำขอ</x-ui.button>
                <x-ui.button variant="ghost" :href="route('user.dashboard')">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
