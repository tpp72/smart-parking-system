{{-- ตาราง Payment (Deposit + Checkout) ใช้ร่วมกันระหว่าง Admin และ Owner — ต้องส่ง $payments และ $markPaidRoute --}}
<div class="sp-card rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full sp-table">
            <thead>
                <tr>
                    <th class="px-5 py-4 text-left">#</th>
                    <th class="px-5 py-4 text-left">ประเภท</th>
                    <th class="px-5 py-4 text-left">ทะเบียน</th>
                    <th class="px-5 py-4 text-left">ผู้ใช้</th>
                    <th class="px-5 py-4 text-left">ลาน</th>
                    <th class="px-5 py-4 text-right">ชั่วโมง</th>
                    <th class="px-5 py-4 text-right">ค่าจอด</th>
                    <th class="px-5 py-4 text-right">ส่วนลด</th>
                    <th class="px-5 py-4 text-right">ยอดรวม</th>
                    <th class="px-5 py-4 text-center">สถานะ</th>
                    <th class="px-5 py-4 text-left">วันที่</th>
                    <th class="px-5 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    @php
                        $isDeposit = $payment->type === \App\Models\Payment::TYPE_DEPOSIT;
                        $plate = $payment->parkingLog?->license_plate ?? $payment->reservation?->license_plate;
                        $brand = $payment->parkingLog?->brand ?? $payment->reservation?->brand;
                        $lotName = $payment->parkingLog?->parkingLot?->name ?? $payment->reservation?->parkingLot?->name;
                    @endphp
                    <tr>
                        <td class="px-5 py-3 text-gray-500 text-xs">#{{ $payment->id }}</td>
                        <td class="px-5 py-3">
                            @if($isDeposit)
                                <span class="sp-badge sp-badge-warn">มัดจำ</span>
                                <span class="block text-xs text-gray-500 mt-1">การจอง #{{ $payment->reservation_id }}</span>
                            @else
                                <span class="sp-badge sp-badge-ok">ค่าจอด</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-extrabold tracking-wider text-red-300">
                            {{ $plate ?? '—' }}
                            @if($brand)
                                <span class="block text-xs font-normal text-gray-500">{{ $brand }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-300">
                            {{ $payment->reservation?->user?->name ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-gray-300">{{ $lotName ?? '—' }}</td>
                        <td class="px-5 py-3 text-right text-gray-300">
                            @if($isDeposit)
                                <span class="text-gray-600">—</span>
                            @else
                                {{ $payment->total_hours }} ชม.
                            @endif
                            <span class="block text-xs text-gray-500">
                                {{ number_format((float)$payment->hourly_rate, 2) }} ฿/ชม.
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-300">
                            @if($isDeposit)
                                <span class="text-gray-600">—</span>
                            @else
                                ฿{{ number_format((float)$payment->parking_fee, 2) }}
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if((float)$payment->reservation_discount > 0)
                                <span class="text-green-400">-฿{{ number_format((float)$payment->reservation_discount, 2) }}</span>
                            @else
                                <span class="text-gray-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-extrabold
                            {{ $payment->payment_status === 'paid' ? 'text-green-300' : ($payment->payment_status === 'void' ? 'text-gray-500' : 'text-yellow-300') }}">
                            ฿{{ number_format((float)$payment->total_amount, 2) }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($payment->payment_status === 'paid')
                                <span class="sp-badge sp-badge-ok">✓ ชำระแล้ว</span>
                            @elseif($payment->payment_status === 'void')
                                <span class="sp-badge sp-badge-bad">void</span>
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
                                      action="{{ route($markPaidRoute, $payment) }}"
                                      onsubmit="return confirm('{{ $isDeposit
                                          ? 'ยืนยันรับเงินมัดจำ ฿' . number_format((float) $payment->total_amount, 2) . ' ของทะเบียน ' . $plate . '? ระบบจะยืนยันการจองและจัดช่องจอดให้'
                                          : 'ยืนยันรับชำระเงิน ฿' . number_format((float) $payment->total_amount, 2) . ' จากทะเบียน ' . $plate . '?' }}')">
                                    @csrf
                                    <button type="submit" title="ยืนยันว่าได้รับเงินจริงแล้ว" class="sp-btn sp-btn-success text-sm px-4 py-1.5">
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
                        <td colspan="12">
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
