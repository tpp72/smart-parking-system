<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">สถานะคำขอ</h1>
                @if(auth()->user()->role === 'owner')
                    <a href="{{ route('owner.dashboard') }}" class="sp-btn sp-btn-outline text-sm">← Dashboard</a>
                @else
                    <a href="{{ route('user.dashboard') }}" class="sp-btn sp-btn-outline text-sm">← Dashboard</a>
                @endif
            </div>

            @if(!$application)
            <div class="sp-card rounded-2xl p-8 text-center">
                <p class="text-gray-400 mb-4">ยังไม่มีคำขอ</p>
                <a href="{{ route('owner.application.create') }}" class="sp-btn sp-btn-primary">สมัครเลย</a>
            </div>
            @else

            {{-- Status badge --}}
            <div class="sp-card rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-4">
                    @if($application->isPending())
                        <div class="w-12 h-12 rounded-full bg-yellow-500/20 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-yellow-300">รอการพิจารณา</p>
                            <p class="text-sm text-gray-400">ส่งเมื่อ {{ $application->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    @elseif($application->isApproved())
                        <div class="w-12 h-12 rounded-full bg-green-500/20 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-green-300">ได้รับการอนุมัติ</p>
                            <p class="text-sm text-gray-400">อนุมัติเมื่อ {{ $application->reviewed_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    @else
                        <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold text-red-300">ไม่ได้รับการอนุมัติ</p>
                            <p class="text-sm text-gray-400">พิจารณาเมื่อ {{ $application->reviewed_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    @endif
                </div>

                @if($application->isRejected() && $application->rejection_reason)
                <div class="mt-4 p-4 rounded-xl bg-red-500/10 border border-red-500/30">
                    <p class="text-xs text-red-300 font-semibold uppercase tracking-wide mb-1">เหตุผลที่ไม่อนุมัติ</p>
                    <p class="text-sm text-gray-300">{{ $application->rejection_reason }}</p>
                </div>
                @endif
            </div>

            {{-- Application detail --}}
            <div class="sp-card rounded-2xl p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">รายละเอียดคำขอ</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="text-gray-400">ชื่อธุรกิจ</dt><dd class="font-medium">{{ $application->business_name }}</dd></div>
                    <div><dt class="text-gray-400">ผู้ติดต่อ</dt><dd class="font-medium">{{ $application->contact_name }}</dd></div>
                    <div><dt class="text-gray-400">เบอร์โทร</dt><dd class="font-medium">{{ $application->phone }}</dd></div>
                    <div><dt class="text-gray-400">อีเมล</dt><dd class="font-medium">{{ $application->email }}</dd></div>
                    <div><dt class="text-gray-400">ชื่อลานจอด</dt><dd class="font-medium">{{ $application->parking_lot_name }}</dd></div>
                    <div><dt class="text-gray-400">จำนวนช่องจอดประมาณ</dt><dd class="font-medium">{{ number_format($application->estimated_slots) }} ช่อง</dd></div>
                    @if($application->address)
                    <div class="sm:col-span-2"><dt class="text-gray-400">ที่อยู่</dt><dd class="font-medium">{{ $application->address }}</dd></div>
                    @endif
                    <div><dt class="text-gray-400">เขต / อำเภอ</dt><dd class="font-medium">{{ $application->district ?? '-' }}</dd></div>
                    <div><dt class="text-gray-400">จังหวัด</dt><dd class="font-medium">{{ $application->province ?? '-' }}</dd></div>
                    @if($application->description)
                    <div class="sm:col-span-2"><dt class="text-gray-400">รายละเอียดเพิ่มเติม</dt><dd class="font-medium">{{ $application->description }}</dd></div>
                    @endif
                    @if($application->document_path)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-400 mb-1">เอกสารแนบ</dt>
                        <dd><a href="{{ Storage::url($application->document_path) }}" target="_blank"
                            class="text-red-400 hover:text-red-300 underline text-sm">ดูเอกสาร →</a></dd>
                    </div>
                    @endif
                </dl>
            </div>

            @if($application->isRejected())
            <div class="mt-6">
                <a href="{{ route('owner.application.edit') }}" class="sp-btn sp-btn-primary w-full text-center">แก้ไขและส่งคำขอใหม่</a>
            </div>
            @endif

            @endif

        </div>
    </div>
</x-app-layout>
