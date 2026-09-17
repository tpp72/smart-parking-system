{{--
    ตัวกรอง + รายการ Log การจอง ใช้ร่วมกันระหว่าง Admin และ Owner — ต้องส่ง $logs, $lots, $statuses, $filters, $indexRoute
    แต่ละแถว: เวลา · การจอง/รถ · สถานะเดิม → สถานะใหม่ (ป้ายภาษาไทย) · ผู้ทำรายการ · หมายเหตุ
--}}
@use('App\Support\Format')
@use('App\Support\StatusCatalog')
@use('App\Support\Navigation')

@php
    $hasFilter = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

<form method="GET" role="search" class="mt-6 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
        <x-ui.field label="ค้นหา" for="q" class="sm:col-span-2 lg:col-span-1">
            <x-ui.input id="q" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="ทะเบียน ชื่อ อีเมล หมายเหตุ หรือ #การจอง" />
        </x-ui.field>
        <x-ui.field label="ลานจอด" for="lot_id">
            <x-ui.select id="lot_id" name="lot_id" placeholder="ทุกลาน">
                @foreach ($lots as $lot)
                    <option value="{{ $lot->id }}" @selected((string) ($filters['lot_id'] ?? '') === (string) $lot->id)>{{ $lot->name }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="เปลี่ยนเป็นสถานะ" for="new_status">
            <x-ui.select id="new_status" name="new_status" placeholder="ทุกสถานะ">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['new_status'] ?? '') === $s)>{{ StatusCatalog::label('reservation', $s, 'staff') }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="ผู้ทำรายการ" for="changed_by">
            <x-ui.select id="changed_by" name="changed_by" placeholder="ทุกคน">
                <option value="system" @selected(($filters['changed_by'] ?? '') === 'system')>เฉพาะระบบ</option>
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="ตั้งแต่วันที่" for="from">
            <x-ui.input id="from" name="from" data-flatpickr="date" :value="$filters['from'] ?? ''" placeholder="วันที่" />
        </x-ui.field>
        <x-ui.field label="ถึงวันที่" for="to">
            <x-ui.input id="to" name="to" data-flatpickr="date" :value="$filters['to'] ?? ''" placeholder="วันที่" />
        </x-ui.field>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
        @if ($hasFilter)
            <x-ui.button variant="ghost" :href="route($indexRoute)">ล้างตัวกรอง</x-ui.button>
        @endif
        <p class="ml-auto text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ $logs->total() }}</span> รายการ</p>
    </div>
</form>

<div class="mt-4">
    @if ($logs->isEmpty())
        <div class="rounded-card border border-line bg-surface shadow-1">
            <x-ui.empty-state :title="$hasFilter ? 'ไม่พบ Log ที่ตรงกับตัวกรอง' : 'ยังไม่มี Log การจอง'"
                description="ทุกครั้งที่การจองเปลี่ยนสถานะ ไม่ว่าคนหรือระบบทำ จะถูกบันทึกที่นี่" />
        </div>
    @else
        <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
            @foreach ($logs as $row)
                <li class="grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[8.5rem_minmax(0,1fr)_auto_minmax(0,1fr)] lg:items-center lg:gap-5">
                    <p class="text-label text-fg-2">{{ Format::short($row->created_at) }}</p>

                    <div class="flex min-w-0 items-center gap-3">
                        <x-ui.plate :plate="$row->license_plate" :province="$row->plate_province" size="sm" />
                        <div class="min-w-0 text-label">
                            <p class="font-semibold text-fg">
                                <span class="tabular">#{{ $row->reservation_id }}</span>
                                @if ($row->is_walk_in) <span class="font-normal text-fg-2">· Walk-in</span> @endif
                            </p>
                            <p class="truncate text-fg-2">{{ $row->lot_name }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($row->old_status)
                            <x-ui.status type="reservation" :value="$row->old_status" audience="staff" />
                        @else
                            <span class="rounded-control border border-line px-1.5 py-0.5 text-caption text-fg-2">สร้างใหม่</span>
                        @endif
                        <x-ui.icon name="chevron-right" class="h-4 w-4 text-fg-3" />
                        <span class="sr-only">เปลี่ยนเป็น</span>
                        <x-ui.status type="reservation" :value="$row->new_status" audience="staff" />
                    </div>

                    <div class="min-w-0 text-label">
                        <p class="text-fg">
                            @if (! $row->changed_by)
                                ระบบ
                            @else
                                {{ $row->changed_by_name ?? 'บัญชีถูกลบ' }}
                                <span class="text-fg-2">· {{ Navigation::ROLE_LABELS[$row->changed_by_role] ?? $row->changed_by_role }}</span>
                            @endif
                        </p>
                        <p class="text-fg-2">{{ $row->note ?? '—' }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <x-ui.pagination :paginator="$logs" class="mt-6" />
    @endif
</div>
