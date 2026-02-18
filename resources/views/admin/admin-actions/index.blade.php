<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">Admin Actions</h1>
                    <p class="text-gray-300 mt-1">Audit log: ดู/ค้นหา/Export CSV</p>
                </div>

                <a href="{{ route('admin.admin-actions.export', request()->query()) }}"
                    class="sp-btn sp-btn-outline">Export CSV</a>
            </div>

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหา action/admin/subject..."
                        class="md:col-span-2 w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="action" class="sp-select">
                        <option value="">ทุก action</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected($action === $a)>{{ $a }}
                            </option>
                        @endforeach
                    </select>

                    <select name="subject_type" class="sp-select">
                        <option value="">ทุก subject</option>
                        @foreach ($subjectTypes as $s)
                            <option value="{{ $s }}" @selected($subjectType === $s)>{{ $s }}
                            </option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ $from }}" class="sp-select" />
                    <input type="date" name="to" value="{{ $to }}" class="sp-select" />

                    <div class="flex gap-2 md:col-span-6">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.admin-actions.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">เวลา</th>
                            <th class="py-3 pr-4">admin</th>
                            <th class="py-3 pr-4">action</th>
                            <th class="py-3 pr-4">subject</th>
                            <th class="py-3 pr-4">ip</th>
                            <th class="py-3 pr-4">meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 text-gray-300">{{ $r->created_at }}</td>
                                <td class="py-3 pr-4 text-gray-200">
                                    {{ $r->admin_name ?? '-' }}
                                    <div class="text-xs text-gray-400">{{ $r->admin_email ?? '' }}</div>
                                </td>
                                <td class="py-3 pr-4 font-bold">{{ $r->action }}</td>
                                <td class="py-3 pr-4 text-gray-200">
                                    {{ $r->subject_type ?? '-' }}{{ $r->subject_id ? ' #' . $r->subject_id : '' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-300">{{ $r->ip_address ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    <div class="max-w-md truncate">
                                        {{ is_string($r->meta) ? $r->meta : json_encode($r->meta, JSON_UNESCAPED_UNICODE) }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-300">ยังไม่มี admin actions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $rows->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
