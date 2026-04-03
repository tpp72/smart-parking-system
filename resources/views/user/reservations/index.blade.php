<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">การจองของฉัน (My Reservations)</h1>
                    <p class="text-gray-300 mt-1">รายการจองที่จอดรถทั้งหมดของคุณ</p>
                </div>
                <a href="{{ route('user.reservations.create') }}" class="sp-btn sp-btn-primary">+ จองที่จอด</a>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">ลาน / ช่อง</th>
                            <th class="py-3 pr-4 text-left">เวลาเริ่ม</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations as $r)
                            <tr class="border-b sp-divider hover:bg-white/5 transition">
                                <td class="py-3 pr-4 font-bold text-red-300">
                                    {{ $r->vehicle?->license_plate ?? '-' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $r->parkingLot?->name ?? '-' }}
                                    @if ($r->parkingSlot)
                                        <span class="text-gray-500">/ {{ $r->parkingSlot->slot_number }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($r->reserve_start)->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 pr-4 text-center">
                                    @php
                                        $badgeClass = match($r->status) {
                                            'confirmed' => 'sp-badge-ok',
                                            'pending'   => 'sp-badge-warn',
                                            default     => 'sp-badge-bad',
                                        };
                                    @endphp
                                    <span class="sp-badge {{ $badgeClass }}">{{ $r->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-gray-400">
                                    ยังไม่มีการจอง —
                                    <a href="{{ route('user.reservations.create') }}" class="text-red-300 underline">
                                        จองเลย
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $reservations->links('vendor.pagination.sp') }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
