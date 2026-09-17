{{--
    ส่งออก CSV — ข้อมูลทั้งระบบ ทุกลาน (กรองลานได้) · 5 ชุดข้อมูล แต่ละชุดมีตัวกรองของตัวเองและดาวน์โหลดทันที
    ไฟล์ใช้หัวคอลัมน์และรหัสสถานะภาษาอังกฤษ เพื่อเปิดใน Excel / นำไปประมวลผลต่อ · ต้องการ: $lots, $statuses
--}}
@use('App\Support\Navigation')
@use('App\Support\StatusCatalog')

@php
    $sets = [
        'reservations' => ['การจอง', 'การจองทุกรายการรวม Walk-in พร้อมมัดจำและเวลา Check-in', 'ช่วงวันที่นับจากเวลาเริ่มจอง', route('admin.exports.reservations')],
        'parking-logs' => ['ประวัติการจอด', 'เวลาเข้า-ออก อัตราค่าจอด และยอดชำระหลัง Check-out', 'ช่วงวันที่นับจากเวลาเข้าลาน', route('admin.exports.parking-logs')],
        'revenue' => ['รายงานรายได้รายวัน', '1 แถว = 1 วัน × 1 ลาน · เงินที่รับจริง แยกมัดจำและค่าจอด', 'นับตามวันที่ยืนยันรับเงิน · ไม่ระบุช่วงวันที่ = เดือนนี้', route('admin.exports.revenue')],
        'reservation-logs' => ['Log การจอง', 'ประวัติการเปลี่ยนสถานะการจอง รวมรายการที่ระบบดำเนินการ', 'ช่วงวันที่นับจากเวลาที่เปลี่ยนสถานะ', route('admin.reservation-logs.export')],
        'audit' => ['Audit Log', 'การกระทำของทุกบทบาทและเหตุการณ์ที่ระบบดำเนินการ', 'ช่วงวันที่นับจากเวลาที่เกิดเหตุการณ์', route('admin.admin-actions.export')],
    ];
@endphp

<x-app-layout>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <h1 class="text-h1 text-fg">ส่งออก CSV</h1>
        <p class="mt-1 text-fg-2">ข้อมูลครอบคลุมทุกลานในระบบ เลือกตัวกรองแล้วดาวน์โหลด · ไฟล์ใช้หัวคอลัมน์ภาษาอังกฤษ เปิดด้วย Excel หรือ Google Sheets ได้</p>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mt-6">{{ $errors->first() }}</x-ui.alert>
        @endif

        <div class="mt-6 flex flex-col gap-4">
            @foreach ($sets as $key => [$title, $description, $dateNote, $action])
                <form method="GET" action="{{ $action }}" aria-labelledby="export-{{ $key }}" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 max-w-xl">
                            <h2 id="export-{{ $key }}" class="text-h3 text-fg">{{ $title }}</h2>
                            <p class="mt-0.5 text-label text-fg-2">{{ $description }}</p>
                        </div>
                        <x-ui.button type="submit" variant="secondary" class="shrink-0">
                            <x-ui.icon name="export" class="h-4 w-4" /> ดาวน์โหลด
                        </x-ui.button>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @if (in_array($key, ['reservations', 'parking-logs'], true))
                            <x-ui.field label="ค้นหา" :for="$key.'-q'">
                                <x-ui.input :id="$key.'-q'" name="q" type="search" :placeholder="$key === 'reservations' ? 'ทะเบียน ชื่อ หรืออีเมล' : 'ทะเบียน'" />
                            </x-ui.field>
                        @endif

                        @if ($key !== 'audit')
                            <x-ui.field label="ลานจอด" :for="$key.'-lot'">
                                <x-ui.select :id="$key.'-lot'" name="lot_id" placeholder="ทุกลาน">
                                    @foreach ($lots as $lot)
                                        <option value="{{ $lot->id }}">{{ $lot->name }} — {{ $lot->owner ? 'เจ้าของ '.$lot->owner->name : 'ลานของผู้ดูแลระบบ' }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @endif

                        @if ($key === 'reservations')
                            <x-ui.field label="สถานะ" for="reservations-status">
                                <x-ui.select id="reservations-status" name="status" placeholder="ทุกสถานะ">
                                    @foreach ($statuses as $st)
                                        <option value="{{ $st }}">{{ StatusCatalog::label('reservation', $st, 'staff') }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @elseif ($key === 'parking-logs')
                            <x-ui.field label="สถานะรถ" for="parking-logs-state">
                                <x-ui.select id="parking-logs-state" name="state" placeholder="ทั้งหมด">
                                    <option value="parked">ยังจอดอยู่</option>
                                    <option value="completed">Check-out แล้ว</option>
                                </x-ui.select>
                            </x-ui.field>
                        @elseif ($key === 'audit')
                            <x-ui.field label="ผู้กระทำ" for="audit-role">
                                <x-ui.select id="audit-role" name="actor_role" placeholder="ทุกบทบาท">
                                    @foreach (Navigation::ROLE_LABELS + ['system' => 'ระบบ'] as $role => $label)
                                        <option value="{{ $role }}">{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        @endif

                        <x-ui.field label="ตั้งแต่วันที่" :for="$key.'-from'">
                            <x-ui.input :id="$key.'-from'" name="from" data-flatpickr="date" placeholder="วันที่" />
                        </x-ui.field>
                        <x-ui.field label="ถึงวันที่" :for="$key.'-to'">
                            <x-ui.input :id="$key.'-to'" name="to" data-flatpickr="date" placeholder="วันที่" />
                        </x-ui.field>
                    </div>
                    <p class="mt-3 text-caption text-fg-3">{{ $dateNote }}</p>
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
