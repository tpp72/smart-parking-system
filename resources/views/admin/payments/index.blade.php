<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">จัดการการชำระเงิน</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Payments — กดยืนยันรับเงินเมื่อได้รับเงินจริงแล้ว (เงินมัดจำ = ยืนยันการจองและจัดช่องจอด)</p>
                </div>
            </div>

            {{-- Alerts --}}
            @if(session('success'))
                <x-sp-alert type="success" class="mb-5" :dismissible="true">{{ session('success') }}</x-sp-alert>
            @endif
            @if($errors->any())
                <x-sp-alert type="error" class="mb-5" :dismissible="true">{{ $errors->first() }}</x-sp-alert>
            @endif

            {{-- Filter tabs --}}
            <div class="flex gap-2 mb-5 flex-wrap">
                @foreach(['unpaid' => 'ค้างชำระ', 'paid' => 'ชำระแล้ว', 'void' => 'ยกเลิก (void)', 'all' => 'ทั้งหมด'] as $val => $label)
                    <a href="{{ route('admin.payments.index', ['status' => $val]) }}"
                       class="px-4 py-2 rounded-xl text-sm font-semibold border transition
                              {{ $status === $val
                                  ? 'bg-red-600/20 border-red-700/60 text-red-200'
                                  : 'border-white/10 text-gray-400 hover:text-white hover:bg-white/[0.05]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @include('partials.payments-table', ['markPaidRoute' => 'admin.payments.mark-paid'])

        </div>
    </div>
</x-app-layout>
