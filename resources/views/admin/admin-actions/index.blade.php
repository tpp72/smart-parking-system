<x-app-layout>
    @php
        $roleLabels = ['user' => 'User', 'owner' => 'Owner', 'admin' => 'Admin', 'system' => 'ระบบ'];
        $roleBadges = ['user' => 'sp-badge-ok', 'owner' => 'sp-badge-warn', 'admin' => 'sp-badge-bad', 'system' => ''];
    @endphp

    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">Audit Log</h1>
                    <p class="text-gray-300 mt-1">การกระทำของผู้ใช้ทุก Role และเหตุการณ์ที่ระบบดำเนินการ (รวม Payment Log)</p>
                </div>

                <a href="{{ route('admin.admin-actions.export', request()->query()) }}"
                    class="sp-btn sp-btn-outline">Export CSV</a>
            </div>

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ค้นหา: action / ชื่อ / อีเมล / ทะเบียนใน meta / #subject"
                        class="md:col-span-2 w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="actor_role" class="sp-select">
                        <option value="">ทุก Role</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r }}" @selected(($filters['actor_role'] ?? '') === $r)>{{ $roleLabels[$r] }}</option>
                        @endforeach
                    </select>

                    <select name="action" class="sp-select">
                        <option value="">ทุก action</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>

                    <select name="subject_type" class="sp-select">
                        <option value="">ทุก subject</option>
                        @foreach ($subjectTypes as $s)
                            <option value="{{ $s }}" @selected(($filters['subject_type'] ?? '') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>

                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="from" data-flatpickr="date" value="{{ $filters['from'] ?? '' }}" class="sp-select" placeholder="วันที่เริ่ม" />
                        <input type="text" name="to" data-flatpickr="date" value="{{ $filters['to'] ?? '' }}" class="sp-select" placeholder="วันที่สิ้นสุด" />
                    </div>

                    <div class="flex gap-2 md:col-span-6">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.admin-actions.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl mt-6 overflow-hidden">
                <div class="overflow-x-auto p-6">
                <table class="w-full sp-table min-w-[820px]">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4 text-left">เวลา</th>
                            <th class="py-3 pr-4 text-left">ผู้กระทำ</th>
                            <th class="py-3 pr-4 text-left">Role</th>
                            <th class="py-3 pr-4 text-left">action</th>
                            <th class="py-3 pr-4 text-left">subject</th>
                            <th class="py-3 pr-4 text-left">ip</th>
                            <th class="py-3 pr-4 text-left">meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            @php $meta = \App\Http\Controllers\Admin\AdminActionController::metaText($r->meta); @endphp
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td class="py-3 pr-4 text-gray-200">
                                    @if ($r->actor_role === 'system')
                                        <span class="text-gray-400">ระบบ</span>
                                    @else
                                        {{ $r->actor_name ?? 'บัญชีถูกลบ' }}
                                        <div class="text-xs text-gray-400">{{ $r->actor_email ?? '' }}</div>
                                    @endif
                                </td>
                                <td class="py-3 pr-4">
                                    <span class="sp-badge {{ $roleBadges[$r->actor_role] ?? '' }}">{{ $roleLabels[$r->actor_role] ?? $r->actor_role }}</span>
                                </td>
                                <td class="py-3 pr-4 font-bold">{{ $r->action }}</td>
                                <td class="py-3 pr-4 text-gray-200">
                                    {{ $r->subject_type ?? '-' }}{{ $r->subject_id ? ' #' . $r->subject_id : '' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">{{ $r->ip_address ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    <div class="max-w-md truncate" title="{{ $meta }}">{{ $meta ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-300">ยังไม่มีรายการ</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="mt-4 px-6 pb-6">
                    {{ $rows->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
