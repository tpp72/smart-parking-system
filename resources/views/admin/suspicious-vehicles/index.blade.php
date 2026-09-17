{{--
    บัญชีดำทะเบียนรถ — รายการกลางของทั้งระบบ กล้องทุกลานตรวจด้วย ทะเบียน + จังหวัด
    แต่ละแถว: ป้ายทะเบียน · เหตุผล · ระดับความเสี่ยง · ใช้งาน/ระงับ · ผู้เพิ่ม · ระงับ/เปิดใช้ · แก้ไข · ลบ
--}}
@use('App\Support\Format')

@php
    $levels = ['low' => 'ต่ำ', 'medium' => 'กลาง', 'high' => 'สูง'];
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">บัญชีดำ</h1>
                <p class="mt-1 text-fg-2">ใช้กับกล้องทุกลานในระบบ · เมื่อพบรถที่ใช้งานอยู่ในรายการ ระบบแจ้งผู้ดูแลระบบและเจ้าของลานนั้น</p>
            </div>
            <x-ui.button :href="route('admin.suspicious-vehicles.create')">
                <x-ui.icon name="plus" class="h-4 w-4" /> เพิ่มทะเบียน
            </x-ui.button>
        </div>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="GET" role="search" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <x-ui.field label="ค้นหา" for="q" class="min-w-0 flex-1 sm:max-w-sm">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="ทะเบียน จังหวัด หรือเหตุผล" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($q !== '')
                <x-ui.button variant="ghost" :href="route('admin.suspicious-vehicles.index')">ล้างคำค้น</x-ui.button>
            @endif
            <p class="ml-auto self-center text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ $entries->total() }}</span> รายการ</p>
        </form>

        <div class="mt-4">
            @if ($entries->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$q !== '' ? 'ไม่พบทะเบียนที่ตรงกับคำค้น' : 'ยังไม่มีทะเบียนในบัญชีดำ'"
                        description="เพิ่มทะเบียนและจังหวัดของรถที่ต้องการให้กล้องแจ้งเตือน">
                        @if ($q === '')
                            <x-ui.button :href="route('admin.suspicious-vehicles.create')">เพิ่มทะเบียน</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($entries as $entry)
                        <li @class([
                            'grid gap-3 px-4 py-4 sm:px-5 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center lg:gap-6',
                            'bg-surface-2/60' => ! $entry->is_active,
                        ])>
                            <x-ui.plate :plate="$entry->license_plate" :province="$entry->plate_province" size="sm" class="justify-self-start" />

                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-label">
                                    @if ($entry->is_active)
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-danger">
                                            <span aria-hidden="true" class="h-2 w-2 rounded-full bg-danger"></span> ใช้งาน
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-fg-2">
                                            <span aria-hidden="true" class="h-2 w-2 rounded-full border border-fg-3"></span> ระงับ
                                        </span>
                                    @endif
                                    <span @class(['text-fg-2', 'font-semibold text-danger' => $entry->level === 'high'])>ความเสี่ยง{{ $levels[$entry->level] ?? $entry->level }}</span>
                                </p>
                                <p class="mt-1 text-fg">{{ $entry->reason ?: 'ไม่ได้ระบุเหตุผล' }}</p>
                                <p class="text-caption text-fg-3">เพิ่มโดย {{ $entry->addedBy?->name ?? 'บัญชีถูกลบ' }} · {{ Format::short($entry->created_at) }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <form method="POST" action="{{ route('admin.suspicious-vehicles.toggle', $entry) }}"
                                    @if ($entry->is_active)
                                        data-confirm="ระงับทะเบียน {{ $entry->license_plate }} {{ $entry->plate_province }} — กล้องจะไม่แจ้งเตือนรถคันนี้จนกว่าจะเปิดใช้งานอีกครั้ง"
                                        data-confirm-title="ระงับรายการนี้?" data-confirm-label="ระงับ" data-confirm-cancel="ใช้งานต่อ"
                                    @endif>
                                    @csrf
                                    <x-ui.button type="submit" variant="secondary" size="sm">{{ $entry->is_active ? 'ระงับ' : 'เปิดใช้งาน' }}</x-ui.button>
                                </form>
                                <x-ui.button variant="ghost" size="sm" :href="route('admin.suspicious-vehicles.edit', $entry)">แก้ไข</x-ui.button>
                                <form method="POST" action="{{ route('admin.suspicious-vehicles.destroy', $entry) }}"
                                    data-confirm="ลบทะเบียน {{ $entry->license_plate }} {{ $entry->plate_province }} ออกจากบัญชีดำถาวร — กล้องจะไม่แจ้งเตือนรถคันนี้อีก"
                                    data-confirm-title="ลบออกจากบัญชีดำ?" data-confirm-label="ลบถาวร" data-confirm-tone="danger" data-confirm-cancel="เก็บไว้">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger">ลบ</x-ui.button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$entries" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
