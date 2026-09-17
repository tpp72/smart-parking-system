{{--
    ชำระเงิน (ใช้ร่วม Owner: ลานของตัวเอง · Admin: ลานของผู้ดูแลระบบ) — กด "รับชำระแล้ว" เมื่อได้รับเงินจริง
    (มัดจำ = ยืนยันการจองและจัดช่องจอด) · ต้องการ: $payments, $status, $scope ('owner' | 'admin')
--}}
<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6">
            <h1 class="text-h1 text-fg">ชำระเงิน</h1>
            <p class="mt-1 text-fg-2">{{ $scope === 'admin' ? 'ลานของผู้ดูแลระบบ' : 'ลานของคุณ' }} · การชำระเงินเป็นการจำลอง กด "รับชำระแล้ว" เมื่อได้รับเงินจริง</p>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        <nav aria-label="กรองตามสถานะ" class="mb-4 flex gap-1 overflow-x-auto border-b border-line">
            @foreach (['unpaid' => 'รอยืนยันรับเงิน', 'paid' => 'ชำระแล้ว', 'void' => 'ยกเลิก', 'all' => 'ทั้งหมด'] as $val => $label)
                <a href="{{ route($scope.'.payments.index', ['status' => $val]) }}" @if ($status === $val) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-touch shrink-0 items-center px-3 text-label transition-colors duration-fast',
                        'font-semibold text-fg shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => $status === $val,
                        'text-fg-2 hover:text-fg' => $status !== $val,
                    ])>{{ $label }}</a>
            @endforeach
        </nav>

        @include('partials.payments-table', ['markPaidRoute' => $scope.'.payments.mark-paid'])
    </div>
</x-app-layout>
