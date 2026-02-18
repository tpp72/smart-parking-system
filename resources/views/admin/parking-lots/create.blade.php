<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มลานจอด</h1>
                <a href="{{ route('admin.parking-lots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.parking-lots.store') }}" class="mt-6 space-y-6">
                @csrf

                @include('admin.parking-lots.partials.form', [
                    'lot' => null,
                    'submitLabel' => 'บันทึก',
                ])
            </form>

        </div>
    </div>
</x-app-layout>
