<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขอุปกรณ์</h1>
                <a href="{{ route('admin.devices.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.devices.update', $device) }}" class="mt-6 space-y-6">
                @csrf
                @method('PUT')

                @include('admin.devices.partials.form', [
                    'device' => $device,
                    'lots' => $lots,
                    'types' => $types,
                    'statuses' => $statuses,
                    'submitLabel' => 'อัปเดต',
                ])
            </form>

            <div class="mt-4">
                <form method="POST" action="{{ route('admin.devices.destroy', $device) }}"
                    onsubmit="return confirm('ยืนยันลบอุปกรณ์นี้? (ลบถาวร)')">
                    @csrf
                    @method('DELETE')
                    <button class="sp-btn sp-btn-danger w-full">ลบอุปกรณ์ (ถาวร)</button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
