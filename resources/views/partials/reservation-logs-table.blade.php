{{-- ตัวกรอง + ตาราง Reservation Log ใช้ร่วมกันระหว่าง Admin และ Owner — ต้องส่ง $logs, $lots, $statuses, $filters, $indexRoute --}}
<div class="sp-card rounded-2xl p-5 mt-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ค้นหา: ทะเบียน / ชื่อ / อีเมล / หมายเหตุ / #การจอง"
            class="md:col-span-2 w-full rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600" />

        <select name="lot_id" class="sp-select">
            <option value="">ทุกลาน</option>
            @foreach ($lots as $lot)
                <option value="{{ $lot->id }}" @selected((string) ($filters['lot_id'] ?? '') === (string) $lot->id)>{{ $lot->name }}</option>
            @endforeach
        </select>

        <select name="new_status" class="sp-select">
            <option value="">ทุกสถานะใหม่</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(($filters['new_status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>

        <select name="changed_by" class="sp-select">
            <option value="">ทุกผู้ทำรายการ</option>
            <option value="system" @selected(($filters['changed_by'] ?? '') === 'system')>เฉพาะระบบ</option>
        </select>

        <div class="grid grid-cols-2 gap-3">
            <input type="text" name="from" data-flatpickr="date" value="{{ $filters['from'] ?? '' }}" class="sp-select" placeholder="วันที่เริ่ม" />
            <input type="text" name="to" data-flatpickr="date" value="{{ $filters['to'] ?? '' }}" class="sp-select" placeholder="วันที่สิ้นสุด" />
        </div>

        <div class="flex gap-2 md:col-span-6">
            <button class="sp-btn sp-btn-outline" type="submit">ค้นหา</button>
            <a class="sp-btn sp-btn-outline" href="{{ route($indexRoute) }}">ล้าง</a>
        </div>
    </form>
</div>

<div class="sp-card rounded-2xl mt-6 overflow-hidden">
    <div class="overflow-x-auto p-6">
        <table class="w-full sp-table min-w-[760px]">
            <thead>
                <tr class="border-b sp-divider">
                    <th class="py-3 pr-4 text-left">เวลา</th>
                    <th class="py-3 pr-4 text-left">การจอง</th>
                    <th class="py-3 pr-4 text-left">ลาน</th>
                    <th class="py-3 pr-4 text-left">ทะเบียน</th>
                    <th class="py-3 pr-4 text-left">สถานะ</th>
                    <th class="py-3 pr-4 text-left">ผู้ทำรายการ</th>
                    <th class="py-3 pr-4 text-left">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $row)
                    <tr class="border-b sp-divider">
                        <td class="py-3 pr-4 text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="py-3 pr-4 font-bold">
                            #{{ $row->reservation_id }}
                            @if($row->is_walk_in)
                                <span class="block text-xs font-normal text-gray-400">Walk-in</span>
                            @endif
                        </td>
                        <td class="py-3 pr-4 text-gray-300">{{ $row->lot_name }}</td>
                        <td class="py-3 pr-4 font-extrabold">
                            {{ $row->license_plate }}
                            <span class="block text-xs font-normal text-gray-500">{{ $row->plate_province }}</span>
                        </td>
                        <td class="py-3 pr-4 whitespace-nowrap">
                            <span class="sp-badge sp-badge-warn">{{ $row->old_status ?? 'ใหม่' }}</span>
                            <span class="text-gray-400 mx-1">→</span>
                            <span class="sp-badge sp-badge-ok">{{ $row->new_status }}</span>
                        </td>
                        <td class="py-3 pr-4 text-gray-200">
                            @if(!$row->changed_by)
                                <span class="text-gray-400">ระบบ</span>
                            @else
                                {{ $row->changed_by_name ?? 'บัญชีถูกลบ' }}
                                <span class="block text-xs text-gray-400">{{ $row->changed_by_role }} · {{ $row->changed_by_email }}</span>
                            @endif
                        </td>
                        <td class="py-3 pr-4 text-gray-300 text-xs max-w-xs">{{ $row->note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-gray-300">ยังไม่มีประวัติการจอง</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 pb-6">
        {{ $logs->links('vendor.pagination.sp') }}
    </div>
</div>
