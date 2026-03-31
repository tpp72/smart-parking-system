<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">ประวัติการสแกนรถ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Scan History — บันทึกการตรวจรถด้วย AI ทั้งหมด</p>
                </div>
                <a href="{{ route('admin.scan.create') }}"
                   class="sp-btn sp-btn-primary sp-glow-btn gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    สแกนใหม่
                </a>
            </div>

            @if(session('success'))
                <x-sp-alert type="success" class="mb-5" :dismissible="true">{{ session('success') }}</x-sp-alert>
            @endif

            {{-- Search --}}
            <form method="GET" action="{{ route('admin.scan.history') }}" class="mb-5 flex gap-2">
                <input type="text" name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียน..."
                       class="sp-select flex-1 max-w-xs px-4 py-2 text-sm">
                <button type="submit" class="sp-btn sp-btn-outline text-sm px-4">ค้นหา</button>
                @if($q)
                    <a href="{{ route('admin.scan.history') }}" class="sp-btn sp-btn-outline text-sm px-4">ล้าง</a>
                @endif
            </form>

            <div class="sp-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full sp-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-4">#</th>
                                <th class="px-4 py-4">รูป</th>
                                <th class="px-4 py-4">ทะเบียน</th>
                                <th class="px-4 py-4">สี</th>
                                <th class="px-4 py-4">ยี่ห้อ</th>
                                <th class="px-4 py-4">ความมั่นใจ</th>
                                <th class="px-4 py-4">สถานะ</th>
                                <th class="px-4 py-4">ผู้สแกน</th>
                                <th class="px-4 py-4">เวลา</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scans as $scan)
                                <tr class="{{ $scan->is_suspicious ? 'bg-red-950/20' : '' }}">
                                    <td class="px-4 py-3 text-gray-500 text-xs">#{{ $scan->id }}</td>

                                    {{-- Thumbnail --}}
                                    <td class="px-4 py-3">
                                        @if($scan->image_path)
                                            <img src="{{ Storage::url($scan->image_path) }}"
                                                 alt="scan"
                                                 class="w-16 h-10 object-cover rounded-lg border border-white/10">
                                        @else
                                            <div class="w-16 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- License Plate --}}
                                    <td class="px-4 py-3 font-extrabold tracking-wider text-red-300">
                                        {{ $scan->license_plate ?: '—' }}
                                        @if($scan->vehicle)
                                            <span class="block text-xs font-normal text-green-400">✓ พบในระบบ</span>
                                        @endif
                                    </td>

                                    {{-- Color --}}
                                    <td class="px-4 py-3">
                                        @if($scan->color)
                                            @php
                                                $cmap = ['white'=>'#f8fafc','black'=>'#1e1e1e','silver'=>'#c0c0c0','gray'=>'#6b7280','red'=>'#dc2626','blue'=>'#2563eb','green'=>'#16a34a','yellow'=>'#eab308','orange'=>'#f97316','brown'=>'#92400e'];
                                                $hex = $cmap[strtolower($scan->color)] ?? '#6b7280';
                                            @endphp
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="w-3.5 h-3.5 rounded-full border border-white/20"
                                                      style="background:{{ $hex }}"></span>
                                                <span class="text-gray-300">{{ $scan->color }}</span>
                                            </span>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>

                                    {{-- Brand --}}
                                    <td class="px-4 py-3 text-gray-300">{!! $scan->brand ?? '<span class="text-gray-600">ไม่ระบุ</span>' !!}</td>

                                    {{-- Confidence --}}
                                    <td class="px-4 py-3">
                                        @if($scan->confidence !== null)
                                            @php
                                                $conf = $scan->confidence;
                                                $confClass = $conf >= 70 ? 'text-green-400' : ($conf >= 40 ? 'text-yellow-400' : 'text-red-400');
                                            @endphp
                                            <span class="font-bold {{ $confClass }}">{{ number_format($conf, 0) }}%</span>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        @if($scan->is_suspicious)
                                            <span class="sp-badge sp-badge-bad">⚠ Blacklist</span>
                                        @else
                                            <span class="sp-badge sp-badge-ok">✓ ปกติ</span>
                                        @endif
                                    </td>

                                    {{-- Scanned by --}}
                                    <td class="px-4 py-3 text-gray-400 text-xs">
                                        {{ $scan->user?->name ?? '—' }}
                                    </td>

                                    {{-- Time --}}
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        {{ $scan->scan_time?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-sp-empty message="ยังไม่มีประวัติการสแกน"
                                                    sub="อัปโหลดรูปรถเพื่อเริ่มต้น">
                                            <a href="{{ route('admin.scan.create') }}"
                                               class="sp-btn sp-btn-primary sp-glow-btn mt-4 gap-2">
                                                สแกนรถเลย
                                            </a>
                                        </x-sp-empty>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($scans->hasPages())
                    <div class="px-5 py-4 border-t border-white/10">
                        {{ $scans->links('vendor.pagination.sp') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
