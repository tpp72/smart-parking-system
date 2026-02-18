<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">Reservation Logs</h1>
                    <p class="text-gray-300 mt-1">ดู/ค้นหา/Export CSV</p>
                </div>

                <a href="{{ route('admin.reservation-logs.export', request()->query()) }}" class="sp-btn sp-btn-outline">
                    Export CSV
                </a>
            </div>

            <div class="sp-card rounded-2xl p-5 mt-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหา: ชื่อ/อีเมล/ทะเบียน/status..."
                        class="md:col-span-2 w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

                    <select name="old_status" class="sp-select">
                        <option value="">old_status ทั้งหมด</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s }}" @selected($old === $s)>{{ $s }}
                            </option>
                        @endforeach
                    </select>

                    <select name="new_status" class="sp-select">
                        <option value="">new_status ทั้งหมด</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s }}" @selected($new === $s)>{{ $s }}
                            </option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ $from }}" class="sp-select" />
                    <input type="date" name="to" value="{{ $to }}" class="sp-select" />

                    <div class="flex gap-2 md:col-span-6">
                        <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
                        <a class="sp-btn sp-btn-outline" href="{{ route('admin.reservation-logs.index') }}">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 mt-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider">
                            <th class="py-3 pr-4">เวลา</th>
                            <th class="py-3 pr-4">Reservation</th>
                            <th class="py-3 pr-4">ทะเบียน</th>
                            <th class="py-3 pr-4">old → new</th>
                            <th class="py-3 pr-4">เปลี่ยนโดย</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $row)
                            <tr class="border-b sp-divider">
                                <td class="py-3 pr-4 text-gray-300">{{ $row->created_at }}</td>
                                <td class="py-3 pr-4 font-bold">#{{ $row->reservation_id }}</td>
                                <td class="py-3 pr-4 font-extrabold">{{ $row->license_plate }}</td>
                                <td class="py-3 pr-4">
                                    <span class="sp-badge sp-badge-warn">{{ $row->old_status }}</span>
                                    <span class="text-gray-400 mx-1">→</span>
                                    <span class="sp-badge sp-badge-ok">{{ $row->new_status }}</span>
                                </td>
                                <td class="py-3 pr-4 text-gray-200">
                                    {{ $row->changed_by_name }} <span
                                        class="text-gray-400">({{ $row->changed_by_email }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-gray-300">ยังไม่มี logs</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $logs->links('vendor.pagination.sp') }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
