{{--
    ประวัติสแกน (Admin: ลานของผู้ดูแลระบบ · Owner: ลานของตัวเอง) — ใช้ร่วมกันทั้งสองบทบาท
    ตัวกรอง: ค้นหาทะเบียน · ผล AI (ผ่าน / ความแม่นยำต่ำ / อ่านไม่ได้) · พบรถในบัญชีดำ
--}}
@use('App\Support\Format')

@php
    $role = auth()->user()->role;
    $historyRoute = "{$role}.scan.history";
    $filters = [
        null => 'ทั้งหมด',
        'passed' => 'ผ่านเกณฑ์',
        'low_accuracy' => 'ความแม่นยำต่ำ',
        'unreadable' => 'อ่านทะเบียนไม่ได้',
        'blacklist' => 'พบบัญชีดำ',
    ];
    $active = array_key_exists((string) $result, $filters) ? $result : null;
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">ประวัติสแกน</h1>
                <p class="mt-1 text-fg-2">ผลที่ AI อ่านได้จากกล้องหน้าลาน · {{ $role === 'owner' ? 'ลานของคุณ' : 'ลานของผู้ดูแลระบบ' }}</p>
            </div>
            <x-ui.button :href="route($role.'.scan.create')">
                <x-ui.icon name="scan" class="h-4 w-4" /> สแกนรถ
            </x-ui.button>
        </div>

        <div class="flex flex-col gap-4 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <form method="GET" action="{{ route($historyRoute) }}" role="search" class="flex flex-wrap items-end gap-3">
                @if ($active)
                    <input type="hidden" name="result" value="{{ $active }}">
                @endif
                <x-ui.field label="ค้นหาทะเบียน" for="q" class="min-w-0 flex-1 sm:max-w-xs">
                    <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="เช่น กข 1234" />
                </x-ui.field>
                <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
                @if ($q !== '')
                    <x-ui.button variant="ghost" :href="route($historyRoute, array_filter(['result' => $active]))">ล้างคำค้น</x-ui.button>
                @endif
            </form>

            <nav aria-label="กรองตามผล" class="-mx-1 flex gap-1 overflow-x-auto border-t border-line px-1 pt-3">
                @foreach ($filters as $key => $label)
                    <a href="{{ route($historyRoute, array_filter(['q' => $q, 'result' => $key])) }}"
                        @if ((string) $active === (string) $key) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-touch shrink-0 items-center rounded-card px-3 text-label transition-colors duration-fast',
                            'bg-primary/10 font-semibold text-primary-ink' => (string) $active === (string) $key,
                            'text-fg-2 hover:bg-surface-2 hover:text-fg' => (string) $active !== (string) $key,
                        ])>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <div class="mt-4">
            @if ($scans->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$q !== '' || $active ? 'ไม่พบผลสแกนที่ตรงกับตัวกรอง' : 'ยังไม่มีประวัติสแกน'"
                        description="ผลสแกนทุกครั้งที่ประตูลานจะถูกบันทึกที่นี่ ยกเว้นกรณีลานเต็มที่ไม่บันทึกผล">
                        @if ($q !== '' || $active)
                            <x-ui.button variant="secondary" :href="route($historyRoute)">ดูทั้งหมด</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($scans as $scan)
                        <li class="grid grid-cols-[4.5rem_1fr] gap-x-4 gap-y-3 px-4 py-4 sm:px-5 md:grid-cols-[5.5rem_minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,11rem)] md:items-center">
                            {{-- ภาพ --}}
                            @if ($scan->image_path)
                                <a href="{{ Storage::url($scan->image_path) }}" target="_blank" rel="noopener"
                                    class="row-span-2 block overflow-hidden rounded-control border border-line md:row-span-1"
                                    aria-label="เปิดภาพสแกน #{{ $scan->id }} ในแท็บใหม่">
                                    <img src="{{ Storage::url($scan->image_path) }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.replace('hidden', 'flex')">
                                    <span class="hidden aspect-[4/3] items-center justify-center px-1 text-center text-mini leading-tight text-fg-3">ไม่พบไฟล์ภาพ</span>
                                </a>
                            @else
                                <span class="row-span-2 flex aspect-[4/3] items-center justify-center rounded-control border border-dashed border-field px-1 text-center text-mini leading-tight text-fg-3 md:row-span-1">ไม่มีภาพ</span>
                            @endif

                            {{-- ทะเบียน + ที่ไหน เมื่อไร --}}
                            <div class="min-w-0">
                                @if ($scan->license_plate)
                                    <x-ui.plate :plate="$scan->license_plate" :province="$scan->plate_province" size="sm" />
                                @else
                                    <span class="text-label font-semibold text-fg-2">อ่านทะเบียนไม่ได้</span>
                                @endif
                                <p class="mt-1.5 truncate text-label text-fg">{{ $scan->parkingLot?->name ?? '—' }}</p>
                                <p class="text-caption text-fg-3">
                                    <span class="tabular">#{{ $scan->id }}</span> · {{ Format::short($scan->scan_time) }} · อัปโหลดโดย {{ $scan->user?->name ?? 'ระบบ' }}
                                </p>
                            </div>

                            {{-- ค่าที่ AI อ่านได้ --}}
                            <dl class="col-start-2 grid grid-cols-3 gap-x-3 text-label md:col-start-auto">
                                <div class="min-w-0">
                                    <dt class="text-caption text-fg-3">ยี่ห้อ</dt>
                                    <dd class="truncate text-fg">{{ $scan->brand ?: 'ไม่ระบุ' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-caption text-fg-3">สี</dt>
                                    <dd class="text-fg"><x-ui.car-color :color="$scan->color" /></dd>
                                </div>
                                <div>
                                    <dt class="text-caption text-fg-3">ความแม่นยำ</dt>
                                    <dd class="tabular text-fg">{{ $scan->confidence !== null ? number_format($scan->confidence, 1).'%' : '—' }}</dd>
                                </div>
                            </dl>

                            {{-- ผล --}}
                            <div class="col-start-2 flex flex-wrap items-center gap-2 md:col-start-auto md:flex-col md:items-end">
                                <x-ui.status type="scan" :value="$scan->result" />
                                @if ($scan->is_suspicious)
                                    <span class="inline-flex items-center gap-1 rounded-control border border-danger px-1.5 py-0.5 text-caption font-semibold text-danger">
                                        <x-ui.icon name="blacklist" class="h-3.5 w-3.5" /> พบในบัญชีดำ
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$scans" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
