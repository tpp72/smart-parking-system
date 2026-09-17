{{--
    Log การจอง — ใครหรือระบบเปลี่ยนสถานะการจองอะไร เมื่อไร
    Owner: ลานของตัวเอง (ไม่มี CSV) · Admin: ทั้งระบบ ทุกลาน ทุก Role + ส่งออก CSV ตามตัวกรอง
    ต้องการ: $logs, $lots, $statuses, $filters, $scope ('owner' | 'admin')
--}}
<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">Log การจอง</h1>
                <p class="mt-1 text-fg-2">
                    {{ $scope === 'admin'
                        ? 'ประวัติการเปลี่ยนสถานะการจองทั้งระบบ ทุกลาน ทั้งที่คนและระบบดำเนินการ'
                        : 'ประวัติการเปลี่ยนสถานะการจองในลานของคุณ ทั้งที่คนและระบบดำเนินการ' }}
                </p>
            </div>
            @if ($scope === 'admin')
                <x-ui.button variant="secondary" :href="route('admin.reservation-logs.export', request()->query())">
                    <x-ui.icon name="export" class="h-4 w-4" /> ส่งออก CSV
                </x-ui.button>
            @endif
        </div>

        @include('partials.reservation-logs-table', ['indexRoute' => $scope.'.reservation-logs.index'])
    </div>
</x-app-layout>
