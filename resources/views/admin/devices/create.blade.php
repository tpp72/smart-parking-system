<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มอุปกรณ์</h1>
                <a href="{{ route('admin.devices.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.devices.store') }}" class="mt-6 space-y-6">
                @csrf
                @include('admin.devices.partials.form', [
                    'device' => null,
                    'lots' => $lots,
                    'types' => $types,
                    'statuses' => $statuses,
                    'submitLabel' => 'บันทึก',
                ])
            </form>

        </div>
    </div>
</x-app-layout>
