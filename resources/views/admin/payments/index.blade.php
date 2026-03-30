<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">จัดการการชำระเงิน</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Payments — กดปุ่มยืนยันรับเงินเมื่อลูกค้าโอนแล้ว</p>
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
            <div class="flex gap-2 mb-5">
                @foreach(['unpaid' => 'ค้างชำระ', 'paid' => 'ชำระแล้ว', 'all' => 'ทั้งหมด'] as $val => $label)
                    <a href="{{ route('admin.payments.index', ['status' => $val]) }}"
                       class="px-4 py-2 rounded-xl text-sm font-semibold border transition
                              {{ $status === $val
                                  ? 'bg-red-600/20 border-red-700/60 text-red-200'
                                  : 'border-white/10 text-gray-400 hover:text-white hover:bg-white/[0.05]' }}">
                        {{ $label }}
                        @if($val === 'unpaid')
                            <span class="ml-1 text-xs bg-red-500/20 text-red-300 rounded-full px-1.5">
                                {{ \App\Models\Payment::where('payment_status','unpaid')->count() }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="sp-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr>
                                <th class="px-5 py-4 text-left">#</th>
                                <th class="px-5 py-4 text-left">ทะเบียน</th>
                                <th class="px-5 py-4 text-left">ลาน</th>
                                <th class="px-5 py-4 text-right">ชั่วโมง</th>
                                <th class="px-5 py-4 text-right">ยอดรวม</th>
                                <th class="px-5 py-4 text-center">สถานะ</th>
                                <th class="px-5 py-4 text-left">วันที่</th>
                                <th class="px-5 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="px-5 py-3 text-gray-500 text-xs">#{{ $payment->id }}</td>
                                    <td class="px-5 py-3 font-extrabold tracking-wider text-red-300">
                                        {{ $payment->parkingLog?->vehicle?->license_plate ?? '—' }}
                                        @if($payment->parkingLog?->vehicle?->brand)
                                            <span class="block text-xs font-normal text-gray-500">
                                                {{ $payment->parkingLog->vehicle->brand }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-300">
                                        {{ $payment->parkingLog?->parkingLot?->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-gray-300">
                                        {{ $payment->total_hours }} ชม.
                                        <span class="block text-xs text-gray-500">
                                            {{ number_format((float)$payment->hourly_rate, 2) }} ฿/ชม.
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-extrabold
                                        {{ $payment->payment_status === 'paid' ? 'text-green-300' : 'text-yellow-300' }}">
                                        ฿{{ number_format((float)$payment->total_amount, 2) }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        @if($payment->payment_status === 'paid')
                                            <span class="sp-badge sp-badge-ok">✓ ชำระแล้ว</span>
                                        @else
                                            <span class="sp-badge sp-badge-warn">ค้างชำระ</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">
                                        {{ $payment->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if($payment->payment_status === 'unpaid')
                                            <form method="POST"
                                                  action="{{ route('admin.payments.mark-paid', $payment) }}"
                                                  onsubmit="return confirm('ยืนยันรับชำระเงิน ฿{{ number_format((float)$payment->total_amount, 2) }} จากทะเบียน {{ $payment->parkingLog?->vehicle?->license_plate }}?')">
                                                @csrf
                                                <button type="submit" class="sp-btn sp-btn-success text-sm px-4 py-1.5">
                                                    ✓ รับชำระแล้ว
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-sp-empty
                                            message="{{ $status === 'unpaid' ? 'ไม่มีรายการค้างชำระ' : 'ไม่มีข้อมูล' }}"
                                            sub="{{ $status === 'unpaid' ? 'ลูกค้าทุกคนชำระเงินครบแล้ว' : '' }}" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($payments->hasPages())
                    <div class="px-5 py-4 border-t border-white/10">
                        {{ $payments->links('vendor.pagination.sp') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
