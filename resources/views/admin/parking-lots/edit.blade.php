<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขลานจอด</h1>
                <a href="{{ route('admin.parking-lots.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            <form method="POST" action="{{ route('admin.parking-lots.update', $parking_lot) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('admin.parking-lots.partials.form', [
                    'lot' => $parking_lot,
                    'submitLabel' => 'อัปเดต',
                ])
            </form>

            <div class="mt-4">
                <form method="POST" action="{{ route('admin.parking-lots.destroy', $parking_lot) }}"
                    onsubmit="return confirm('ยืนยันลบลานจอดนี้? (ลบถาวร)')">
                    @csrf
                    @method('DELETE')
                    <button class="sp-btn sp-btn-danger w-full">ลบลานจอด (ถาวร)</button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
