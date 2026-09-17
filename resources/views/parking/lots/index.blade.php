{{--
    ลานจอด (ใช้ร่วม Owner: ลานของตัวเอง · Admin: ลานของผู้ดูแลระบบ owner_id = NULL)
    แต่ละลาน: ที่ตั้ง · ค่าจอด · การรับจอง · สถานะช่องจอดจริง ณ ตอนนี้ · จัดการช่องจอด / แก้ไข / ลบ
    ต้องการ: $lots (withCount slots + สถานะ + active_reservations), $q, $scope ('owner' | 'admin')
--}}
@use('App\Support\Format')

@php
    $r = fn (string $name, ...$params) => route("{$scope}.{$name}", ...$params);
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">ลานจอด</h1>
                <p class="mt-1 text-fg-2">{{ $scope === 'owner' ? 'ลานที่คุณเป็นเจ้าของ' : 'ลานของผู้ดูแลระบบ (ใช้ร่วมกันทุกบัญชีผู้ดูแลระบบ)' }}</p>
            </div>
            <x-ui.button :href="$r('parking-lots.create')">
                <x-ui.icon name="plus" class="h-4 w-4" /> เพิ่มลานจอด
            </x-ui.button>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="GET" role="search" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <x-ui.field label="ค้นหาลาน" for="q" class="min-w-0 flex-1 sm:max-w-sm">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="ชื่อ ที่อยู่ เขต จังหวัด หรือจุดสังเกต" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($q !== '')
                <x-ui.button variant="ghost" :href="$r('parking-lots.index')">ล้างคำค้น</x-ui.button>
            @endif
        </form>

        <div class="mt-4">
            @if ($lots->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$q !== '' ? 'ไม่พบลานที่ตรงกับคำค้น' : 'ยังไม่มีลานจอด'"
                        description="เพิ่มลาน แล้วสร้างช่องจอด ลูกค้าจึงจะจองหรือเข้าแบบ Walk-in ได้">
                        @if ($q === '')
                            <x-ui.button :href="$r('parking-lots.create')">เพิ่มลานจอด</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($lots as $lot)
                        @php
                            $address = collect([$lot->address, $lot->district, $lot->province])->filter()->implode(' ');
                            $blocked = $lot->active_reservations_count > 0;
                        @endphp
                        <li class="grid gap-4 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1fr)_17rem_16rem] lg:items-center lg:gap-6">
                            <div class="min-w-0">
                                <h2 class="text-h3 text-fg">{{ $lot->name }}</h2>
                                <p class="mt-0.5 text-label text-fg-2">{{ $address ?: 'ยังไม่ได้ระบุที่อยู่' }}@if ($lot->landmark) · {{ $lot->landmark }}@endif</p>
                                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-label">
                                    <span class="text-fg"><span class="num font-semibold">{{ Format::baht($lot->hourly_rate) }}</span> / ชม.</span>
                                    <span @class(['inline-flex items-center gap-1', 'text-success' => $lot->reservations_enabled, 'text-fg-3' => ! $lot->reservations_enabled])>
                                        <span aria-hidden="true" @class(['inline-block h-2 w-2 rounded-full', 'bg-success' => $lot->reservations_enabled, 'border border-field' => ! $lot->reservations_enabled])></span>
                                        {{ $lot->reservations_enabled ? 'เปิดรับจองล่วงหน้า' : 'ปิดรับจองล่วงหน้า (รับเฉพาะ Walk-in)' }}
                                    </span>
                                    @if ($lot->active_reservations_count > 0)
                                        <span class="text-fg-2">การจองที่ยังไม่จบ <span class="tabular font-semibold text-fg">{{ $lot->active_reservations_count }}</span></span>
                                    @endif
                                </p>
                            </div>

                            <div>
                                @if ($lot->slots_count > 0)
                                    <x-ui.occupancy-bar :available="$lot->available_count" :reserved="$lot->reserved_count" :occupied="$lot->occupied_count" />
                                @else
                                    <p class="text-label text-warning">ยังไม่มีช่องจอด — ลูกค้าจองลานนี้ไม่ได้</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <x-ui.button variant="secondary" size="sm" :href="$r('parking-slots.index', ['lot_id' => $lot->id])">
                                    <x-ui.icon name="slots" class="h-4 w-4" /> ช่องจอด
                                </x-ui.button>
                                <x-ui.button variant="ghost" size="sm" :href="$r('parking-lots.edit', $lot->id)">แก้ไข</x-ui.button>
                                @if ($blocked)
                                    {{-- ปุ่มที่ปิดใช้งานรับโฟกัส/hover ไม่ได้ จึงบอกเหตุผลเป็นข้อความแทน tooltip --}}
                                    <x-ui.button variant="ghost" size="sm" disabled aria-describedby="lot-{{ $lot->id }}-delete-reason">ลบ</x-ui.button>
                                    <span id="lot-{{ $lot->id }}-delete-reason" class="basis-full text-caption text-fg-3 lg:text-end">ลบได้เมื่อไม่มีการจองที่ยังไม่จบ</span>
                                @else
                                    <form method="POST" action="{{ $r('parking-lots.destroy', $lot->id) }}"
                                        data-confirm="ลบ {{ $lot->name }} ถาวร — ช่องจอด {{ $lot->slots_count }} ช่อง และประวัติการจอง การจอด ผลสแกน และรายการชำระเงินทั้งหมดของลานนี้จะถูกลบไปด้วย กู้คืนไม่ได้"
                                        data-confirm-title="ลบลานจอดถาวร?" data-confirm-label="ลบลานจอด" data-confirm-tone="danger" data-confirm-cancel="เก็บลานไว้">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger">ลบ</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$lots" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
