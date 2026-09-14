<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">Reservation Log</h1>
                    <p class="text-gray-300 mt-1">ประวัติการเปลี่ยนสถานะการจองทั้งระบบ — ทุกลาน ทุก Role รวมรายการที่ระบบดำเนินการ</p>
                </div>

                <a href="{{ route('admin.reservation-logs.export', request()->query()) }}" class="sp-btn sp-btn-outline">
                    Export CSV
                </a>
            </div>

            @include('partials.reservation-logs-table', ['indexRoute' => 'admin.reservation-logs.index'])

        </div>
    </div>
</x-app-layout>
