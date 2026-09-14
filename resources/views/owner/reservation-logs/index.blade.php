<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div>
                <h1 class="text-3xl font-extrabold sp-glow-text">ประวัติการจอง</h1>
                <p class="text-gray-300 mt-1">Reservation Log ของลานจอดของคุณ — ใคร/ระบบเปลี่ยนสถานะการจองอะไร เมื่อไร</p>
            </div>

            @include('partials.reservation-logs-table', ['indexRoute' => 'owner.reservation-logs.index'])

        </div>
    </div>
</x-app-layout>
