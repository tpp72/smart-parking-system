{{--
    แผนผังช่องจอดทีละลาน (ใช้ร่วม Owner และ Admin)
    จัดกลุ่มเป็นแถวตามอักษรนำหน้าเลขช่อง (A001 → แถว A) · สถานะบอกด้วยรูปทรง + ข้อความ + สี
    สถานะช่องระบบเป็นผู้เปลี่ยน (ว่าง / จอง / ใช้งาน) — ผู้ดูแลแก้ได้เฉพาะเลขช่อง และลบได้เฉพาะช่องที่ว่าง
    ต้องการ: $lots, $lot (ลานที่เลือก|null), $slots, $occupants (keyBy parking_slot_id), $q, $status, $scope
--}}
@use('App\Support\Format')
@use('App\Support\StatusCatalog')

@php
    $r = fn (string $name, ...$params) => route("{$scope}.{$name}", ...$params);
    $counts = $slots->countBy('status');
    $rows = $slots->groupBy(fn ($slot) => preg_match('/^\D*/u', $slot->slot_number, $m) && $m[0] !== '' ? rtrim($m[0], '-_ ') : '');
    $matches = fn ($slot) => (! $status || $slot->status === $status)
        && ($q === '' || mb_stripos($slot->slot_number, $q) !== false);
    $matchCount = $slots->filter($matches)->count();
    $filtered = $status || $q !== '';
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10" x-data="{ slot: null, confirmDelete: false }">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">ช่องจอด</h1>
                <p class="mt-1 text-fg-2">สถานะช่องเปลี่ยนตามการจองและรถเข้า-ออกโดยระบบ · กดช่องเพื่อดูรายละเอียด แก้เลขช่อง หรือลบ</p>
            </div>
            @if ($lot)
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" :href="$r('parking-slots.bulk.create', ['lot_id' => $lot->id])">เพิ่มหลายช่อง</x-ui.button>
                    <x-ui.button :href="$r('parking-slots.create', ['lot_id' => $lot->id])">
                        <x-ui.icon name="plus" class="h-4 w-4" /> เพิ่มช่อง
                    </x-ui.button>
                </div>
            @endif
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        @if ($lots->isEmpty())
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state title="ยังไม่มีลานจอด" description="สร้างลานก่อน แล้วจึงเพิ่มช่องจอดในลานนั้น">
                    <x-ui.button :href="$r('parking-lots.create')">เพิ่มลานจอด</x-ui.button>
                </x-ui.empty-state>
            </div>
        @else
            {{-- ── เลือกลาน + ตัวกรอง ─────────────────────────────────── --}}
            <form method="GET" role="search" class="rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] sm:items-end">
                    <x-ui.field label="ลานจอด" for="lot_id">
                        <x-ui.select id="lot_id" name="lot_id" onchange="this.form.requestSubmit()">
                            @foreach ($lots as $item)
                                <option value="{{ $item->id }}" @selected($lot?->id === $item->id)>{{ $item->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field label="ค้นหาเลขช่อง" for="q">
                        <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="เช่น A01" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary">แสดง</x-ui.button>
                </div>
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif

                <nav aria-label="กรองตามสถานะ" class="mt-4 flex flex-wrap gap-1 border-t border-line pt-3">
                    @foreach ([null => ['ทั้งหมด', $slots->count()], 'available' => ['ว่าง', $counts['available'] ?? 0], 'reserved' => ['จอง', $counts['reserved'] ?? 0], 'occupied' => ['ใช้งาน', $counts['occupied'] ?? 0]] as $key => [$label, $count])
                        <a href="{{ $r('parking-slots.index', array_filter(['lot_id' => $lot?->id, 'q' => $q, 'status' => $key])) }}"
                            @if ((string) $status === (string) $key) aria-current="page" @endif
                            @class([
                                'inline-flex min-h-touch items-center gap-2 rounded-card px-3 text-label transition-colors duration-fast',
                                'bg-primary/10 font-semibold text-primary-ink' => (string) $status === (string) $key,
                                'text-fg-2 hover:bg-surface-2 hover:text-fg' => (string) $status !== (string) $key,
                            ])>
                            {{ $label }} <span class="tabular text-fg-3">{{ $count }}</span>
                        </a>
                    @endforeach
                </nav>
            </form>

            {{-- ── แผนผัง ──────────────────────────────────────────────── --}}
            <section aria-labelledby="map-title" class="mt-4 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 id="map-title" class="text-h3 text-fg">{{ $lot->name }}</h2>
                    <p class="text-label text-fg-2">
                        @if ($filtered)
                            ตรงตัวกรอง <span class="tabular font-semibold text-fg">{{ $matchCount }}</span> จาก {{ $slots->count() }} ช่อง
                        @else
                            ทั้งหมด <span class="tabular font-semibold text-fg">{{ $slots->count() }}</span> ช่อง
                        @endif
                    </p>
                </div>

                @if ($slots->isEmpty())
                    <x-ui.empty-state title="ลานนี้ยังไม่มีช่องจอด" description="เพิ่มช่องจอดทีละช่อง หรือสร้างหลายช่องพร้อมกัน เช่น A001–A040">
                        <x-ui.button :href="$r('parking-slots.bulk.create', ['lot_id' => $lot->id])">เพิ่มหลายช่อง</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <div class="mt-5 flex flex-col gap-6">
                        @foreach ($rows as $prefix => $rowSlots)
                            <div>
                                <h3 class="mb-2 text-caption font-semibold text-fg-3">{{ $prefix !== '' ? 'แถว '.$prefix : 'ช่องไม่มีอักษรนำหน้า' }} · <span class="tabular">{{ $rowSlots->count() }}</span> ช่อง</h3>
                                <ul class="grid grid-cols-[repeat(auto-fill,minmax(5.25rem,1fr))] gap-2">
                                    @foreach ($rowSlots as $s)
                                        @php
                                            $occ = $occupants[$s->id] ?? null;
                                            $payload = [
                                                'number' => $s->slot_number,
                                                'status' => StatusCatalog::label('slot', $s->status),
                                                'plate' => $occ ? trim($occ->license_plate.' '.$occ->plate_province) : null,
                                                'booking' => $occ ? ($occ->is_walk_in ? 'Walk-in' : ($occ->user?->name ?? '—')).' · การจอง #'.$occ->id : null,
                                                'time' => $occ ? ($occ->status === 'checked_in' && $occ->parkingLog
                                                    ? 'เข้าลาน '.Format::short($occ->parkingLog->check_in_time)
                                                    : 'เริ่มจอง '.Format::short($occ->reserve_start)) : null,
                                                'edit' => $r('parking-slots.edit', $s),
                                                'destroy' => $s->status === 'available' ? $r('parking-slots.destroy', $s) : null,
                                                'reason' => match ($s->status) {
                                                    'reserved' => 'ช่องนี้ถูก Lock ให้การจองที่ยืนยันแล้ว จึงลบไม่ได้',
                                                    'occupied' => 'มีรถจอดอยู่ จึงลบไม่ได้',
                                                    default => null,
                                                },
                                            ];
                                        @endphp
                                        <li>
                                            <button type="button"
                                                x-on:click="slot = {{ Js::from($payload) }}; confirmDelete = false; $dispatch('open-modal', 'slot-detail')"
                                                aria-label="ช่อง {{ $s->slot_number }} {{ $payload['status'] }}{{ $occ ? ' ทะเบียน '.$payload['plate'] : '' }}"
                                                @class([
                                                    'flex min-h-16 w-full flex-col items-start justify-between rounded-control border-2 px-2 py-1.5 text-start transition-colors duration-fast hover:bg-surface-2',
                                                    'border-line bg-surface' => $s->status === 'available',
                                                    'border-warning bg-warning/10' => $s->status === 'reserved',
                                                    'border-danger bg-danger/10' => $s->status === 'occupied',
                                                    'opacity-35' => $filtered && ! $matches($s),
                                                ])>
                                                <span class="num text-label font-semibold text-fg">{{ $s->slot_number }}</span>
                                                <x-ui.status type="slot" :value="$s->status" size="sm" class="!border-0 !px-0" />
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ── รายละเอียดช่อง ─────────────────────────────────────── --}}
            <x-ui.modal name="slot-detail" maxWidth="sm" title="รายละเอียดช่องจอด">
                <div class="px-5 py-4" x-show="slot" x-cloak>
                    <p class="text-h2 text-fg"><span class="num" x-text="slot?.number"></span></p>
                    <p class="mt-1 text-label text-fg-2">สถานะ: <span class="font-semibold text-fg" x-text="slot?.status"></span></p>

                    <dl class="mt-4 flex flex-col gap-2 rounded-control border border-line bg-surface-2 px-4 py-3 text-label" x-show="slot?.plate">
                        <div class="flex justify-between gap-3"><dt class="text-fg-2">ทะเบียน</dt><dd class="font-semibold text-fg" x-text="slot?.plate"></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-fg-2">ผู้จอง</dt><dd class="text-end text-fg" x-text="slot?.booking"></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-fg-2">เวลา</dt><dd class="text-end text-fg" x-text="slot?.time"></dd></div>
                    </dl>
                    <p class="mt-4 text-label text-fg-3" x-show="slot?.reason" x-text="slot?.reason"></p>

                    {{-- ยืนยันการลบในหน้าต่างเดียวกัน (ไม่ซ้อนกล่องยืนยันบน modal) --}}
                    <div x-show="confirmDelete" x-cloak role="alert" class="mt-4 rounded-control border border-danger/60 px-4 py-3 text-label text-fg">
                        ลบช่อง <span class="num font-semibold" x-text="slot?.number"></span> ถาวร? ลบแล้วกู้คืนไม่ได้
                    </div>
                </div>

                <x-slot name="footer">
                    <template x-if="slot?.destroy && !confirmDelete">
                        <x-ui.button variant="ghost" class="text-danger" x-on:click="confirmDelete = true">ลบช่อง</x-ui.button>
                    </template>
                    <template x-if="slot?.destroy && confirmDelete">
                        <form method="POST" x-bind:action="slot?.destroy" class="flex flex-col-reverse gap-2 sm:flex-row">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="ghost" x-on:click="confirmDelete = false">ไม่ลบ</x-ui.button>
                            <x-ui.button type="submit" variant="danger">ยืนยันลบช่อง</x-ui.button>
                        </form>
                    </template>
                    <x-ui.button variant="secondary" x-show="!confirmDelete" x-bind:href="slot?.edit" href="#">แก้ไขเลขช่อง</x-ui.button>
                </x-slot>
            </x-ui.modal>
        @endif
    </div>
</x-app-layout>
