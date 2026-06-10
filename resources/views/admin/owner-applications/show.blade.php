<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">รายละเอียดคำขอ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">จาก {{ $ownerApplication->user?->name }}</p>
                </div>
                <a href="{{ route('admin.owner-applications.index') }}" class="sp-btn sp-btn-outline text-sm">← กลับ</a>
            </div>

            {{-- Flash --}}
            @if(session('success'))
            <div class="sp-card rounded-xl p-4 border border-green-500/40 text-green-300 text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
            <div class="sp-card rounded-xl p-4 border border-red-500/40">
                <ul class="list-disc list-inside text-sm text-red-300 space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif

            {{-- Status --}}
            <div class="sp-card rounded-2xl p-5 flex items-center gap-4">
                @if($ownerApplication->status === 'pending')
                    <span class="sp-badge sp-badge-warn text-sm px-3 py-1">รอพิจารณา</span>
                @elseif($ownerApplication->status === 'approved')
                    <span class="sp-badge sp-badge-ok text-sm px-3 py-1">อนุมัติแล้ว</span>
                    @if($ownerApplication->reviewer)
                        <span class="text-sm text-gray-400">โดย {{ $ownerApplication->reviewer->name }} · {{ $ownerApplication->reviewed_at?->format('d/m/Y H:i') }}</span>
                    @endif
                @else
                    <span class="sp-badge sp-badge-danger text-sm px-3 py-1">ไม่อนุมัติ</span>
                    @if($ownerApplication->reviewer)
                        <span class="text-sm text-gray-400">โดย {{ $ownerApplication->reviewer->name }} · {{ $ownerApplication->reviewed_at?->format('d/m/Y H:i') }}</span>
                    @endif
                @endif
            </div>

            @if($ownerApplication->status === 'rejected' && $ownerApplication->rejection_reason)
            <div class="sp-card rounded-2xl p-5 border border-red-500/30">
                <p class="text-xs text-red-300 font-semibold uppercase tracking-wide mb-2">เหตุผลที่ไม่อนุมัติ</p>
                <p class="text-sm text-gray-300">{{ $ownerApplication->rejection_reason }}</p>
            </div>
            @endif

            {{-- Applicant info --}}
            <div class="sp-card rounded-2xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลผู้ยื่นคำขอ</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-gray-400">ชื่อในระบบ</dt><dd class="font-medium">{{ $ownerApplication->user?->name }}</dd></div>
                    <div><dt class="text-gray-400">อีเมลในระบบ</dt><dd class="font-medium">{{ $ownerApplication->user?->email }}</dd></div>
                    <div><dt class="text-gray-400">ชื่อผู้ติดต่อ</dt><dd class="font-medium">{{ $ownerApplication->contact_name }}</dd></div>
                    <div><dt class="text-gray-400">เบอร์โทร</dt><dd class="font-medium">{{ $ownerApplication->phone }}</dd></div>
                    <div><dt class="text-gray-400">อีเมลธุรกิจ</dt><dd class="font-medium">{{ $ownerApplication->email }}</dd></div>
                </dl>
            </div>

            {{-- Business info --}}
            <div class="sp-card rounded-2xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลธุรกิจและลานจอด</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-gray-400">ชื่อธุรกิจ</dt><dd class="font-medium">{{ $ownerApplication->business_name }}</dd></div>
                    <div><dt class="text-gray-400">ชื่อลานจอด</dt><dd class="font-medium">{{ $ownerApplication->parking_lot_name }}</dd></div>
                    <div><dt class="text-gray-400">จำนวนช่องจอดโดยประมาณ</dt><dd class="font-medium">{{ number_format($ownerApplication->estimated_slots) }} ช่อง</dd></div>
                    <div><dt class="text-gray-400">ส่งคำขอเมื่อ</dt><dd class="font-medium">{{ $ownerApplication->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-400">ที่อยู่ / สถานที่</dt><dd class="font-medium">{{ $ownerApplication->address }}</dd></div>
                    @if($ownerApplication->description)
                    <div class="sm:col-span-2"><dt class="text-gray-400">รายละเอียดเพิ่มเติม</dt><dd class="font-medium">{{ $ownerApplication->description }}</dd></div>
                    @endif
                    @if($ownerApplication->document_path)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-400 mb-1">เอกสารแนบ</dt>
                        <dd><a href="{{ Storage::url($ownerApplication->document_path) }}" target="_blank"
                            class="text-red-400 hover:text-red-300 underline text-sm">ดูเอกสาร →</a></dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Actions (only for pending) --}}
            @if($ownerApplication->isPending())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Approve --}}
                <form action="{{ route('admin.owner-applications.approve', $ownerApplication) }}" method="POST">
                    @csrf
                    <button type="submit"
                        onclick="return confirm('อนุมัติคำขอของ {{ addslashes($ownerApplication->user?->name) }}?')"
                        class="sp-btn sp-btn-primary w-full">
                        อนุมัติคำขอ
                    </button>
                </form>

                {{-- Reject --}}
                <div x-data="{ open: false }">
                    <button @click="open = !open" class="sp-btn sp-btn-outline w-full border-red-500/50 text-red-300 hover:border-red-400">
                        ไม่อนุมัติ
                    </button>
                    <div x-show="open" x-transition class="mt-4 sp-card rounded-2xl p-5 border border-red-500/30 col-span-2">
                        <form action="{{ route('admin.owner-applications.reject', $ownerApplication) }}" method="POST" class="space-y-3">
                            @csrf
                            <label class="block text-sm font-medium text-gray-300">เหตุผลที่ไม่อนุมัติ <span class="text-red-400">*</span></label>
                            <textarea name="rejection_reason" rows="4"
                                class="sp-input w-full resize-none"
                                placeholder="ระบุเหตุผลอย่างน้อย 10 ตัวอักษร เพื่อให้ผู้ยื่นคำขอสามารถแก้ไขได้ถูกต้อง"
                                required minlength="10">{{ old('rejection_reason') }}</textarea>
                            <button type="submit" class="sp-btn w-full bg-red-700 hover:bg-red-600 text-white">ยืนยันการไม่อนุมัติ</button>
                        </form>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

</x-app-layout>
