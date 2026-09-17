<!DOCTYPE html>
<html lang="th">
{{-- หน้าทดสอบ Foundation (UI Phase 1) — route /_ui เปิดเฉพาะ APP_ENV=local · ห้ามลิงก์จากเมนูหรือใช้เป็นฟีเจอร์ --}}

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Foundation | Smart Parking System</title>
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $swatches = [
        'page' => 'bg-page', 'surface' => 'bg-surface', 'surface-2' => 'bg-surface-2', 'line' => 'bg-line', 'field' => 'bg-field',
        'fg' => 'bg-fg', 'fg-2' => 'bg-fg-2', 'fg-3' => 'bg-fg-3',
        'primary' => 'bg-primary', 'primary-ink' => 'bg-primary-ink', 'success' => 'bg-success', 'warning' => 'bg-warning', 'danger' => 'bg-danger',
    ];

    $statusSets = [
        'reservation' => 'การจอง',
        'payment' => 'การชำระเงิน',
        'slot' => 'ช่องจอด',
        'scan' => 'ผล AI Scan',
        'review' => 'คำขอ / คำร้อง',
    ];

    $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(1, 10), 128, 10, 4, ['path' => url('/_ui')]);
@endphp

<body class="font-sans antialiased">
    <x-ui.page-progress />

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div>
                <h1 class="text-h2 text-fg">Foundation</h1>
                <p class="text-caption text-fg-3">หน้าทดสอบ Design System เฉพาะเครื่องพัฒนา — ไม่อยู่ในเมนูของระบบ</p>
            </div>
            <x-ui.theme-switch />
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-12 px-4 py-10 sm:px-6">

        {{-- Tokens --}}
        <section aria-labelledby="s-tokens" class="space-y-4">
            <h2 id="s-tokens" class="text-h2">สีจาก token</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                @foreach ($swatches as $name => $class)
                    <div class="overflow-hidden rounded-card border border-line bg-surface">
                        <div class="h-14 {{ $class }}"></div>
                        <p class="px-3 py-2 text-caption text-fg-2 num">{{ $name }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Typography --}}
        <section aria-labelledby="s-type" class="space-y-4">
            <h2 id="s-type" class="text-h2">ตัวอักษร: Anuphan + Martian Mono</h2>
            <div class="grid gap-6 rounded-card border border-line bg-surface p-5 shadow-1 lg:grid-cols-2">
                <div class="space-y-3">
                    <p class="text-caption text-fg-3">H1 · 28px</p>
                    <p class="text-h1">ลานจอดรถ เทศบาลนครระยอง</p>
                    <p class="text-caption text-fg-3">H2 · 22px</p>
                    <p class="text-h2">การจองที่ต้องยืนยันรับมัดจำ</p>
                    <p class="text-caption text-fg-3">H3 · 18px</p>
                    <p class="text-h3">ช่องจอดที่ระบบจัดให้</p>
                    <p class="text-caption text-fg-3">Body · 16px / 1.625</p>
                    <p class="max-w-prose text-body text-fg-2">
                        ระบบจะจัดสรรช่องจอดให้อัตโนมัติเมื่อเจ้าหน้าที่ยืนยันรับเงินมัดจำแล้ว กรุณาเช็คอินภายใน 60 นาทีนับจากเวลาเริ่มจอง
                        มิฉะนั้นการจองจะหมดอายุ และเงินมัดจำที่ชำระแล้วจะไม่ได้รับคืน
                    </p>
                    <p class="text-label text-fg-2">Label · 13px — ป้ายทะเบียนรถ</p>
                    <p class="text-caption text-fg-3">Caption · 12px — แก้ไขได้ภายหลัง ก่อนเช็คอิน</p>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-caption text-fg-3">KPI · 32px (ตัวเลขใช้ Martian Mono)</p>
                        <p class="text-kpi num">฿12,480.00</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 border-t border-line pt-4">
                        <div><dt class="text-caption text-fg-3">ค่าจอด 3 ชม. × ฿35.00/ชม.</dt><dd class="num">฿105.00</dd></div>
                        <div><dt class="text-caption text-fg-3">หักมัดจำ</dt><dd class="num">−฿35.00</dd></div>
                        <div><dt class="text-caption text-fg-3">เวลาเข้า–ออก</dt><dd class="num">14:05 – 16:48</dd></div>
                        <div><dt class="text-caption text-fg-3">ช่องจอด</dt><dd class="num">G023</dd></div>
                        <div><dt class="text-caption text-fg-3">วันที่</dt><dd class="num">16/09/2026</dd></div>
                        <div><dt class="text-caption text-fg-3">ป้ายทะเบียน (ข้อความไทย ไม่ใช้ mono)</dt><dd class="font-semibold">กข 1234 กรุงเทพมหานคร</dd></div>
                    </dl>
                </div>
            </div>
        </section>

        {{-- Buttons --}}
        <section aria-labelledby="s-buttons" class="space-y-4">
            <h2 id="s-buttons" class="text-h2">ปุ่ม</h2>
            <div class="space-y-4 rounded-card border border-line bg-surface p-5 shadow-1">
                @foreach (['primary' => 'ยืนยันรับมัดจำ', 'secondary' => 'ดูรายละเอียด', 'ghost' => 'ล้างตัวกรอง', 'danger' => 'ยกเลิกการจอง'] as $variant => $text)
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="w-24 text-caption text-fg-3 num">{{ $variant }}</span>
                        <x-ui.button :variant="$variant">{{ $text }}</x-ui.button>
                        <x-ui.button :variant="$variant" size="sm">{{ $text }}</x-ui.button>
                        <x-ui.button :variant="$variant" disabled>{{ $text }}</x-ui.button>
                        <x-ui.button :variant="$variant" loading>กำลังบันทึก</x-ui.button>
                    </div>
                @endforeach
                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <x-ui.button variant="secondary" href="#s-forms">ลิงก์แบบปุ่ม</x-ui.button>
                    <x-ui.tooltip text="แสดงการแจ้งเตือน">
                        <x-ui.button variant="ghost" icon-only label="การแจ้งเตือน">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8a5 5 0 0 1 10 0c0 5 2 6 2 6H3s2-1 2-6M8.5 17a1.75 1.75 0 0 0 3 0" /></svg>
                        </x-ui.button>
                    </x-ui.tooltip>
                </div>
            </div>
        </section>

        {{-- Forms --}}
        <section id="s-forms" aria-labelledby="s-forms-title" class="space-y-4">
            <h2 id="s-forms-title" class="text-h2">ฟอร์ม</h2>
            <form class="grid gap-5 rounded-card border border-line bg-surface p-5 shadow-1 md:grid-cols-2" onsubmit="event.preventDefault()">
                <x-ui.field label="ป้ายทะเบียนรถ" for="demo_plate" hint="กรอกเฉพาะเลขทะเบียน ไม่รวมจังหวัด" required>
                    <x-ui.input name="demo_plate" placeholder="เช่น กข 1234" />
                </x-ui.field>
                <x-ui.field label="จังหวัด" for="demo_province" required>
                    <x-ui.select name="demo_province" placeholder="เลือกจังหวัด">
                        <option>กรุงเทพมหานคร</option>
                        <option>ระยอง</option>
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="เหตุผลที่ไม่อนุมัติ" for="demo_reason" :error="['กรุณาระบุเหตุผลอย่างน้อย 10 ตัวอักษร']">
                    <x-ui.input name="demo_reason" invalid value="สั้นไป" />
                </x-ui.field>
                <x-ui.field label="อัตราค่าจอด (฿/ชม.)" for="demo_rate" hint="ปิดการแก้ไข — ตัวอย่างสถานะ disabled">
                    <x-ui.input name="demo_rate" numeric value="35.00" disabled />
                </x-ui.field>
                <x-ui.field label="หมายเหตุถึงเจ้าหน้าที่" for="demo_note" class="md:col-span-2">
                    <x-ui.textarea name="demo_note" rows="3" placeholder="ไม่บังคับ" />
                </x-ui.field>
                <div class="md:col-span-2">
                    <x-ui.checkbox name="demo_reservations" label="เปิดรับการจองล่วงหน้า" description="ปิดแล้ว Walk-in ยังเข้าจอดได้ตามปกติ" checked />
                </div>
            </form>
        </section>

        {{-- Status --}}
        <section aria-labelledby="s-status" class="space-y-4">
            <h2 id="s-status" class="text-h2">ตราสถานะ (ข้อความ + รูปทรง + สี)</h2>
            <div class="divide-y divide-line rounded-card border border-line bg-surface shadow-1">
                @foreach ($statusSets as $type => $title)
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                        <p class="w-32 shrink-0 text-label text-fg-2">{{ $title }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Support\StatusCatalog::values($type) as $value)
                                <x-ui.status :type="$type" :value="$value" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <p class="w-32 shrink-0 text-label text-fg-2">มุมมองลูกค้า</p>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.status type="reservation" value="pending" audience="user" />
                        <x-ui.status type="reservation" value="pending" audience="user" size="sm" />
                        <x-ui.status type="reservation" value="something_new" />
                    </div>
                </div>
            </div>
        </section>

        {{-- Feedback --}}
        <section aria-labelledby="s-feedback" class="space-y-4">
            <h2 id="s-feedback" class="text-h2">ข้อความแจ้ง, Toast และกล่องยืนยัน</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <x-ui.alert tone="info" title="ระบบจัดช่องจอดให้อัตโนมัติ">ผู้ใช้เลือกได้เฉพาะลาน ช่องจอดจะถูกจัดเมื่อยืนยันรับมัดจำแล้ว</x-ui.alert>
                <x-ui.alert tone="success">ยืนยันรับเงินมัดจำ ฿35.00 — การจอง #1024 ได้รับการยืนยัน</x-ui.alert>
                <x-ui.alert tone="warning" title="ใกล้หมดเวลาเช็คอิน">เหลือเวลาอีก 12 นาที ก่อนการจองหมดอายุ</x-ui.alert>
                <x-ui.alert tone="danger" dismissible>ลานจอดเต็ม — ยกเลิกการจองและเปลี่ยนมัดจำเป็นยกเลิกอัตโนมัติ</x-ui.alert>
            </div>
            <div class="flex flex-wrap gap-2 rounded-card border border-line bg-surface p-4 shadow-1">
                <x-ui.button variant="secondary" x-data x-on:click="spToast({ tone: 'success', message: 'บันทึกการชำระเงิน ฿105.00 แล้ว' })">Toast สำเร็จ</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="spToast({ tone: 'info', title: 'เช็คอินอัตโนมัติ', message: 'ทะเบียน กข 1234 เข้าช่อง G023' })">Toast ข้อมูล</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="spToast({ tone: 'warning', message: 'AI Accuracy ไม่ผ่านเกณฑ์ 85%' })">Toast เตือน</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="spToast({ tone: 'error', message: 'บันทึกไม่สำเร็จ กรุณาลองใหม่' })">Toast ผิดพลาด</x-ui.button>
                <x-ui.button variant="danger" x-data
                    x-on:click="spConfirm({ title: 'ยกเลิกการจอง #1024?', message: 'เงินมัดจำที่ชำระแล้วจะไม่ได้รับคืน', confirmLabel: 'ยกเลิกการจอง', tone: 'danger' }).then((ok) => spToast({ tone: ok ? 'success' : 'info', message: ok ? 'ยกเลิกการจองแล้ว' : 'ไม่ได้ยกเลิก' }))">
                    กล่องยืนยัน (JS)
                </x-ui.button>
                <form method="GET" action="{{ url('/_ui') }}" data-confirm="ส่งฟอร์มนี้เพื่อทดสอบ data-confirm" data-confirm-title="ยืนยันการส่งฟอร์ม">
                    <x-ui.button type="submit" variant="secondary">กล่องยืนยัน (ฟอร์ม)</x-ui.button>
                </form>
            </div>
        </section>

        {{-- Overlays & navigation parts --}}
        <section aria-labelledby="s-overlays" class="space-y-4">
            <h2 id="s-overlays" class="text-h2">Modal, Drawer, Dropdown, Tabs และเลขหน้า</h2>
            <div class="flex flex-wrap items-center gap-2 rounded-card border border-line bg-surface p-4 shadow-1">
                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'demo-modal')">เปิด Modal</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('open-drawer', 'demo-drawer')">เปิด Drawer</x-ui.button>
                <x-ui.dropdown align="left" width="56">
                    <x-slot name="trigger">
                        <x-ui.button variant="secondary">เมนูบัญชี</x-ui.button>
                    </x-slot>
                    <x-slot name="content">
                        <x-ui.dropdown-item href="#s-tokens">โปรไฟล์</x-ui.dropdown-item>
                        <x-ui.dropdown-item href="#s-type">การแจ้งเตือน</x-ui.dropdown-item>
                        <div class="my-1 border-t border-line" role="separator"></div>
                        <x-ui.dropdown-item tone="danger">ออกจากระบบ</x-ui.dropdown-item>
                    </x-slot>
                </x-ui.dropdown>
            </div>

            <div class="rounded-card border border-line bg-surface px-4 shadow-1">
                <x-ui.tabs :tabs="['bookings' => 'การจอง', 'payments' => 'การชำระเงิน', 'empty' => 'ว่าง', 'loading' => 'กำลังโหลด', 'error' => 'ผิดพลาด']" label="ตัวอย่างแท็บ">
                    <x-ui.tab-panel name="bookings"><p class="text-fg-2">รายการการจองของลานนี้</p></x-ui.tab-panel>
                    <x-ui.tab-panel name="payments"><x-ui.loading-state variant="skeleton" /></x-ui.tab-panel>
                    <x-ui.tab-panel name="empty">
                        <x-ui.empty-state title="ยังไม่มีการจอง" description="เมื่อมีผู้จองลานนี้ รายการจะแสดงที่นี่">
                            <x-ui.button variant="secondary" size="sm">ดูลานอื่น</x-ui.button>
                        </x-ui.empty-state>
                    </x-ui.tab-panel>
                    <x-ui.tab-panel name="loading"><x-ui.loading-state /></x-ui.tab-panel>
                    <x-ui.tab-panel name="error">
                        <x-ui.error-state><x-ui.button variant="secondary" size="sm">ลองใหม่</x-ui.button></x-ui.error-state>
                    </x-ui.tab-panel>
                </x-ui.tabs>
            </div>

            <div class="rounded-card border border-line bg-surface p-4 shadow-1">
                <x-ui.pagination :paginator="$paginator" />
            </div>
        </section>
    </main>

    <x-ui.modal name="demo-modal" title="แก้ไขข้อมูลรถ" description="แก้ได้เฉพาะทะเบียน จังหวัด ยี่ห้อ และสี ก่อนเช็คอิน">
        <div class="space-y-4 px-5 py-4">
            <x-ui.field label="ยี่ห้อรถ" for="modal_brand"><x-ui.input name="modal_brand" value="Toyota" /></x-ui.field>
        </div>
        <x-slot name="footer">
            <x-ui.button variant="secondary" x-on:click="$dispatch('close')">ยกเลิก</x-ui.button>
            <x-ui.button x-on:click="$dispatch('close')">บันทึก</x-ui.button>
        </x-slot>
    </x-ui.modal>

    <x-ui.drawer name="demo-drawer" title="เมนู">
        <nav aria-label="ตัวอย่างเมนู" class="flex flex-col p-2">
            @foreach (['หน้าหลัก', 'จองที่จอด', 'การจองของฉัน', 'แจ้งเตือน', 'บัญชี'] as $item)
                <a href="#s-tokens" class="flex min-h-touch items-center rounded-card px-3 text-fg hover:bg-surface-2">{{ $item }}</a>
            @endforeach
        </nav>
    </x-ui.drawer>

    <x-ui.toast-region />
    <x-ui.confirm-dialog />
</body>

</html>
