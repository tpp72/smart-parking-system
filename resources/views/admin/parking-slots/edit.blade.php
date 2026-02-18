<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขช่องจอด</h1>
                <a href="{{ route('admin.parking-slots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.parking-slots.update', $slot) }}" class="mt-6 space-y-6">
                @csrf
                @method('PUT')
                @include('admin.parking-slots.partials.form', [
                    'slot' => $slot,
                    'lots' => $lots,
                    'statuses' => $statuses,
                    'submitLabel' => 'อัปเดต',
                ])
            </form>
        </div>
    </div>
</x-app-layout>
