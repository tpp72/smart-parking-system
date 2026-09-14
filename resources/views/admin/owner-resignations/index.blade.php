<x-app-layout>
    @php
        $statusLabels = ['pending' => 'รอพิจารณา', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'];
        $statusBadges = ['pending' => 'sp-badge-warn', 'approved' => 'sp-badge-ok', 'rejected' => 'sp-badge-bad'];
    @endphp

    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <div>
                <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">คำร้องลาออกของเจ้าของลานจอด</h1>
                <p class="text-gray-400 text-sm mt-0.5">
                    เมื่ออนุมัติ ระบบจะยกเลิกการจองที่ยังไม่ Check-in, เช็คเอาท์รถที่จอดอยู่, แจ้งผู้จองให้ติดต่อ Admin,
                    ลบลานจอดของ Owner (ข้อมูลของลานถูกลบตาม) และเปลี่ยนบัญชีกลับเป็น User
                </p>
            </div>

            @if(session('success'))
                <div class="sp-card rounded-xl p-4 border border-green-500/40 text-green-300 text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="sp-card rounded-xl p-4 border border-red-500/40 text-red-300 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="grid grid-cols-3 gap-4">
                @foreach($statusLabels as $key => $label)
                    <a href="{{ route('admin.owner-resignations.index', ['status' => $key]) }}"
                       class="sp-card rounded-2xl p-4 text-center {{ $status === $key ? 'ring-2 ring-red-500/60' : '' }}">
                        <p class="text-2xl font-extrabold">{{ $counts[$key] ?? 0 }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $label }}</p>
                    </a>
                @endforeach
            </div>

            <div class="space-y-4">
                @forelse($resignations as $resignation)
                    <div class="sp-card rounded-2xl p-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <p class="font-bold text-white">{{ $resignation->user?->name ?? 'บัญชีถูกลบ' }}</p>
                                <p class="text-xs text-gray-400">{{ $resignation->user?->email }} · ยื่นเมื่อ {{ $resignation->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <span class="sp-badge {{ $statusBadges[$resignation->status] ?? '' }}">{{ $statusLabels[$resignation->status] ?? $resignation->status }}</span>
                        </div>

                        <div class="text-sm">
                            <p class="text-gray-400">เหตุผลในการลาออก</p>
                            <p class="text-gray-200">{{ $resignation->reason }}</p>
                        </div>

                        @if($resignation->isPending())
                            @php $effect = $impact[$resignation->id]; @endphp
                            <div class="grid grid-cols-3 gap-3 text-center text-sm">
                                <div class="rounded-xl bg-black/30 border border-white/10 p-3">
                                    <p class="text-xl font-extrabold text-red-300">{{ $effect['lots'] }}</p>
                                    <p class="text-xs text-gray-400">ลานที่จะถูกลบ</p>
                                </div>
                                <div class="rounded-xl bg-black/30 border border-white/10 p-3">
                                    <p class="text-xl font-extrabold text-yellow-300">{{ $effect['bookings'] }}</p>
                                    <p class="text-xs text-gray-400">การจองที่จะถูกยกเลิก</p>
                                </div>
                                <div class="rounded-xl bg-black/30 border border-white/10 p-3">
                                    <p class="text-xl font-extrabold text-sky-300">{{ $effect['parked'] }}</p>
                                    <p class="text-xs text-gray-400">รถที่จะถูกเช็คเอาท์</p>
                                </div>
                            </div>

                            <div class="flex flex-col lg:flex-row gap-4">
                                <form method="POST" action="{{ route('admin.owner-resignations.approve', $resignation) }}"
                                      onsubmit="return confirm('ยืนยันอนุมัติคำร้องลาออก? ระบบจะยกเลิกการจอง เช็คเอาท์รถ และลบลานจอดของ Owner นี้ทั้งหมด (ย้อนกลับไม่ได้)')">
                                    @csrf
                                    <button type="submit" class="sp-btn sp-btn-danger">อนุมัติคำร้องลาออก</button>
                                </form>

                                <form method="POST" action="{{ route('admin.owner-resignations.reject', $resignation) }}" class="flex-1 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <input type="text" name="rejection_reason" required minlength="10" maxlength="1000"
                                           placeholder="เหตุผลที่ไม่อนุมัติ (อย่างน้อย 10 ตัวอักษร)"
                                           class="flex-1 rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-500 text-sm focus:ring-0 focus:border-red-600" />
                                    <button type="submit" class="sp-btn sp-btn-outline whitespace-nowrap">ไม่อนุมัติ</button>
                                </form>
                            </div>
                        @elseif($resignation->status === 'rejected')
                            <div class="text-sm">
                                <p class="text-gray-400">เหตุผลที่ไม่อนุมัติ ({{ $resignation->reviewer?->name ?? '-' }} · {{ $resignation->reviewed_at?->format('d/m/Y H:i') }})</p>
                                <p class="text-gray-200">{{ $resignation->rejection_reason }}</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-400">
                                อนุมัติโดย {{ $resignation->reviewer?->name ?? '-' }} · {{ $resignation->reviewed_at?->format('d/m/Y H:i') }} —
                                ยกเลิกการจอง {{ $resignation->result['reservations_cancelled'] ?? 0 }} รายการ ·
                                เช็คเอาท์รถ {{ $resignation->result['cars_checked_out'] ?? 0 }} คัน ·
                                ลบลานจอด {{ $resignation->result['lots_deleted'] ?? 0 }} แห่ง
                            </p>
                        @endif
                    </div>
                @empty
                    <div class="sp-card rounded-2xl p-10 text-center text-gray-400">ไม่มีคำร้องลาออก</div>
                @endforelse
            </div>

            <div>{{ $resignations->links('vendor.pagination.sp') }}</div>

        </div>
    </div>
</x-app-layout>
