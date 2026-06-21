<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">บัญชีดำทะเบียนรถ</h1>
                    <p class="text-gray-400 text-sm mt-0.5">Suspicious Vehicle Blacklist</p>
                </div>
                <a href="{{ route('admin.suspicious-vehicles.create') }}" class="sp-btn sp-btn-danger inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    เพิ่มทะเบียน
                </a>
            </div>

            {{-- Flash --}}
            @if(session('success'))
                <div class="rounded-xl border border-green-600/40 bg-green-900/20 px-4 py-3 text-sm text-green-300">
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="rounded-xl border border-red-600/40 bg-red-900/20 px-4 py-3 text-sm text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Search --}}
            <form method="GET" action="{{ route('admin.suspicious-vehicles.index') }}" class="flex gap-2">
                <input type="text" name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียน หรือเหตุผล…"
                    class="sp-input flex-1 rounded-xl px-4 py-2 text-sm bg-white/5 border border-white/10 text-white placeholder-gray-500 focus:outline-none focus:border-red-500/60">
                <button type="submit" class="sp-btn sp-btn-outline px-4">ค้นหา</button>
                @if($q)
                    <a href="{{ route('admin.suspicious-vehicles.index') }}" class="sp-btn sp-btn-outline px-4">ล้าง</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="sp-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="sp-table w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                <th class="px-4 py-3 text-left font-medium">ทะเบียน</th>
                                <th class="px-4 py-3 text-left font-medium">เหตุผล</th>
                                <th class="px-4 py-3 text-left font-medium">ระดับ</th>
                                <th class="px-4 py-3 text-left font-medium">สถานะ</th>
                                <th class="px-4 py-3 text-left font-medium">เพิ่มโดย</th>
                                <th class="px-4 py-3 text-left font-medium">วันที่เพิ่ม</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @forelse($entries as $entry)
                                <tr class="hover:bg-white/[0.03] transition">
                                    <td class="px-4 py-3 font-bold font-mono tracking-wide">{{ $entry->license_plate }}</td>
                                    <td class="px-4 py-3 text-gray-300 max-w-xs truncate">{{ $entry->reason ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($entry->level === 'high')
                                            <span class="sp-badge text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30">สูง</span>
                                        @elseif($entry->level === 'medium')
                                            <span class="sp-badge text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30">กลาง</span>
                                        @else
                                            <span class="sp-badge text-xs px-2 py-0.5 rounded-full bg-gray-500/20 text-gray-300 border border-gray-500/30">ต่ำ</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($entry->is_active)
                                            <span class="sp-badge text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30">ใช้งาน</span>
                                        @else
                                            <span class="sp-badge text-xs px-2 py-0.5 rounded-full bg-gray-600/20 text-gray-400 border border-gray-600/30">ระงับ</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $entry->addedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $entry->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            {{-- Toggle --}}
                                            <form method="POST" action="{{ route('admin.suspicious-vehicles.toggle', $entry) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="text-xs px-2.5 py-1 rounded-lg border transition {{ $entry->is_active ? 'border-gray-600/40 text-gray-400 hover:border-yellow-500/40 hover:text-yellow-300' : 'border-green-600/40 text-green-400 hover:bg-green-500/10' }}">
                                                    {{ $entry->is_active ? 'ระงับ' : 'เปิด' }}
                                                </button>
                                            </form>
                                            {{-- Edit --}}
                                            <a href="{{ route('admin.suspicious-vehicles.edit', $entry) }}"
                                                class="sp-btn sp-btn-outline text-xs px-2.5 py-1">แก้ไข</a>
                                            {{-- Delete --}}
                                            <form method="POST" action="{{ route('admin.suspicious-vehicles.destroy', $entry) }}"
                                                onsubmit="return confirm('ลบทะเบียน {{ $entry->license_plate }} ออกจากบัญชีดำ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="sp-btn sp-btn-danger text-xs px-2.5 py-1">ลบ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                        @if($q)
                                            ไม่พบทะเบียน "<span class="text-gray-300">{{ $q }}</span>"
                                        @else
                                            ยังไม่มีทะเบียนในบัญชีดำ
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($entries->hasPages())
                    <div class="px-4 py-4 border-t border-white/10">
                        {{ $entries->links('vendor.pagination.sp') }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
