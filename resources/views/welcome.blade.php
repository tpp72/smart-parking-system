<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Smart Parking — ระบบจัดการลานจอดรถ ตั้งแต่จองล่วงหน้า ยืนยันมัดจำ อ่านป้ายทะเบียนด้วย AI จนถึงเช็คเอาท์และคิดค่าจอด">
    <title>Smart Parking</title>

    @include('partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    หน้าแรก — เล่าเส้นทางของรถ 1 คัน (1 คัน = 1 การจอง = บัตรจอดรถ 1 ใบ)
    ทุกกฎบนหน้านี้ต้องตรงกับ docs/project-plan.md · ไม่มีตัวเลขสถิติหรือคำรับรองที่ไม่มีจริง
--}}
@php
    $steps = [
        ['title' => 'จองล่วงหน้า', 'text' => 'เลือกลานและเวลาเริ่มจอด ล่วงหน้าได้ไม่เกิน 1 วัน กรอกป้ายทะเบียน จังหวัด ยี่ห้อ และสีรถเอง ส่วนช่องจอดระบบเป็นผู้เลือก'],
        ['title' => 'ยืนยันรับมัดจำ', 'text' => 'มัดจำเท่ากับค่าจอด 1 ชั่วโมงของลาน เมื่อเจ้าหน้าที่ยืนยันรับเงิน การจองจึงยืนยันและได้ช่องจอด'],
        ['title' => 'AI อ่านป้ายทะเบียน', 'text' => 'อัปโหลดภาพรถแทนกล้องหน้าลาน AI อ่านป้ายทะเบียน จังหวัด ยี่ห้อ และสีรถ ผลต้องแม่นยำเกิน 85%'],
        ['title' => 'Check-in อัตโนมัติ', 'text' => 'ป้ายทะเบียนและจังหวัดตรง พร้อมยี่ห้อหรือสีตรง ภายใน 60 นาทีหลังเวลาเริ่ม ระบบ Check-in ให้ · รถที่ไม่มีการจองเข้าแบบ Walk-in'],
        ['title' => 'Check-out', 'text' => 'สแกนขาออกแล้วระบบคิดค่าจอดรายชั่วโมง ปัดเศษขึ้นและขั้นต่ำ 1 ชั่วโมง จากนั้นหักมัดจำและส่วนลดการจอง'],
        ['title' => 'ชำระเงิน', 'text' => 'เจ้าหน้าที่บันทึกการรับเงินในระบบ เป็นการจำลอง ไม่มีการชำระเงินออนไลน์'],
    ];

    $roles = [
        ['title' => 'ผู้ใช้', 'items' => ['จองที่จอดและติดตามสถานะการจอง', 'แก้ข้อมูลรถหรือยกเลิกได้ก่อน Check-in', 'ดูประวัติการจอด ค่าจอด และส่วนลด', 'สมัครเป็นเจ้าของลาน']],
        ['title' => 'เจ้าของลาน', 'items' => ['จัดการลานและช่องจอดของตัวเอง', 'ยืนยันรับมัดจำและค่าจอด', 'Check-in / Check-out เองเมื่อระบบอัตโนมัติทำไม่ได้', 'ดูรายได้และประวัติของลาน']],
        ['title' => 'ผู้ดูแลระบบ', 'items' => ['ดูภาพรวมทุกลานในระบบ', 'พิจารณาคำขอเป็นเจ้าของลานและคำร้องลาออก', 'ดูแลบัญชีดำและ Audit Log', 'ส่งออกข้อมูลเป็น CSV']],
    ];

    // ตำแหน่งของรถตัวอย่างบนบัตร: ผ่าน 3 ขั้นแรกแล้ว กำลังอยู่ที่ Check-in
    $current = 3;
@endphp

<body class="min-h-screen font-sans antialiased">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-3 focus:z-toast focus:rounded-card focus:border focus:border-line focus:bg-surface focus:px-4 focus:py-3 focus:text-label focus:font-semibold focus:text-fg focus:shadow-overlay">
        ข้ามไปเนื้อหาหลัก
    </a>

    <x-ui.page-progress />

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
            @include('layouts.shell.brand', ['nav' => ['homeHref' => url('/')], 'nameFromSm' => true])

            <nav aria-label="บัญชี" class="flex items-center gap-1.5">
                <x-ui.theme-switch popover />
                @auth
                    <x-ui.button :href="route('dashboard')" size="sm">ไปที่หน้าหลัก</x-ui.button>
                @else
                    <x-ui.button :href="route('login')" variant="ghost" size="sm" class="hidden sm:inline-flex">เข้าสู่ระบบ</x-ui.button>
                    <x-ui.button :href="route('register')" size="sm">สมัครสมาชิก</x-ui.button>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="focus:outline-none">
        {{-- ── Hero: ชื่อระบบ + บัตรจอดรถตัวอย่าง ───────────────────────── --}}
        <section class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16 lg:py-24">
            <div>
                <h1 class="text-hero text-fg">Smart Parking</h1>
                <p class="mt-5 max-w-xl text-lead text-fg-2">
                    ระบบจัดการลานจอดรถในที่เดียว ตั้งแต่จองล่วงหน้า ยืนยันมัดจำ อ่านป้ายทะเบียนด้วย AI
                    Check-in และ Check-out อัตโนมัติ จนถึงคิดค่าจอดและบันทึกการรับเงิน
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    @auth
                        <x-ui.button :href="route('dashboard')">ไปที่หน้าหลัก</x-ui.button>
                    @else
                        <x-ui.button :href="route('register')">สมัครสมาชิกเพื่อจองที่จอด</x-ui.button>
                        <x-ui.button :href="route('login')" variant="secondary">เข้าสู่ระบบ</x-ui.button>
                    @endauth
                </div>

                <p class="mt-6 text-label text-fg-3">โครงงานวิทยาการคอมพิวเตอร์ · ต้นแบบสำหรับสาธิต ไม่ได้ใช้งานกับลานจอดจริง</p>
            </div>

            {{-- บัตรจอดรถตัวอย่าง: ต้นขั้วป้ายทะเบียน + รอยปรุ + ลำดับขั้นพร้อมตำแหน่งปัจจุบัน --}}
            <figure class="mx-auto w-full max-w-sm lg:mx-0 lg:justify-self-end">
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <div class="flex items-center justify-between gap-3 px-5 pb-4 pt-5">
                        <p class="text-label font-semibold text-fg">บัตรจอดรถ</p>
                        <p class="rounded-control border border-line px-2 py-0.5 text-caption text-fg-3">ตัวอย่าง</p>
                    </div>

                    <div class="px-5 pb-5">
                        <x-ui.plate plate="กข 1234" province="กรุงเทพมหานคร" size="lg" />
                    </div>

                    {{-- รอยปรุระหว่างต้นขั้วกับตัวบัตร --}}
                    <div class="relative h-0 border-t border-dashed border-field" aria-hidden="true">
                        <span class="absolute -left-2.5 -top-2.5 h-5 w-5 rounded-full border border-line bg-page [clip-path:inset(0_0_0_50%)]"></span>
                        <span class="absolute -right-2.5 -top-2.5 h-5 w-5 rounded-full border border-line bg-page [clip-path:inset(0_50%_0_0)]"></span>
                    </div>

                    <ol class="px-5 py-4" aria-label="สถานะของรถตัวอย่าง">
                        @foreach ($steps as $i => $step)
                            @php($state = $i < $current ? 'done' : ($i === $current ? 'now' : 'next'))
                            <li class="relative flex min-h-10 items-center gap-3 {{ $loop->last ? '' : 'pb-1' }}"
                                @if ($state === 'now') aria-current="step" @endif>
                                @unless ($loop->last)
                                    <span aria-hidden="true" @class([
                                        'absolute left-[0.5625rem] top-[1.75rem] h-[calc(100%-1.25rem)] w-px',
                                        'bg-fg-2' => $state === 'done',
                                        'bg-line' => $state !== 'done',
                                    ])></span>
                                @endunless
                                <span aria-hidden="true" @class([
                                    'relative z-10 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                                    'border-fg-2 bg-fg-2 text-surface' => $state === 'done',
                                    'border-primary-ink bg-surface' => $state === 'now',
                                    'border-line bg-surface' => $state === 'next',
                                ])>
                                    @if ($state === 'done')
                                        <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 6.25 5 8.5l4.5-5" /></svg>
                                    @elseif ($state === 'now')
                                        <span class="h-2 w-2 rounded-full bg-primary-ink"></span>
                                    @endif
                                </span>
                                <span @class([
                                    'text-label',
                                    'text-fg-2' => $state === 'done',
                                    'font-semibold text-fg' => $state === 'now',
                                    'text-fg-3' => $state === 'next',
                                ])>{{ $step['title'] }}</span>
                                <span class="sr-only">— {{ ['done' => 'ผ่านแล้ว', 'now' => 'ขั้นปัจจุบัน', 'next' => 'ยังไม่ถึง'][$state] }}</span>

                                @if ($i === 1)
                                    <span aria-hidden="true"
                                        class="ml-auto -rotate-6 rounded-control border-2 border-primary-ink px-1.5 py-0.5 text-caption font-bold text-primary-ink">
                                        รับมัดจำแล้ว
                                    </span>
                                @elseif ($state === 'now')
                                    <span class="ml-auto text-caption font-semibold text-primary-ink">ตอนนี้</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
                <figcaption class="mt-3 text-center text-caption text-fg-3 lg:text-start">รถ 1 คันคือการจอง 1 รายการ ติดตามได้ทุกขั้นจนจบ</figcaption>
            </figure>
        </section>

        {{-- ── เส้นทางของรถ 1 คัน ───────────────────────────────────────── --}}
        <section aria-labelledby="flow-title" class="border-y border-line bg-surface">
            <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-20">
                <h2 id="flow-title" class="text-h2 text-fg">เส้นทางของรถ 1 คัน</h2>
                <p class="mt-2 max-w-2xl text-fg-2">รถที่จองไว้และรถที่ขับเข้ามาโดยไม่ได้จองใช้ขั้นตอนเดียวกัน ระบบเป็นผู้ตัดสินใจ เจ้าหน้าที่ยืนยันเฉพาะการรับเงินและกรณีที่ระบบทำเองไม่ได้</p>

                <ol class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($steps as $step)
                        <li class="border-t border-fg pt-4">
                            <p class="num text-label text-fg-3">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} / {{ str_pad(count($steps), 2, '0', STR_PAD_LEFT) }}</p>
                            <h3 class="mt-2 text-h3 text-fg">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-fg-2">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- ── บทบาทในระบบ ───────────────────────────────────────────────── --}}
        <section aria-labelledby="roles-title" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-20">
            <h2 id="roles-title" class="text-h2 text-fg">แต่ละบทบาททำอะไรได้</h2>
            <p class="mt-2 max-w-2xl text-fg-2">แต่ละบัญชีเห็นและจัดการเฉพาะขอบเขตของตัวเอง: การจองของตัวเอง ลานของตัวเอง หรือทั้งระบบ</p>

            <div class="mt-10 grid gap-8 md:grid-cols-3 md:gap-0 md:divide-x md:divide-line">
                @foreach ($roles as $role)
                    <div class="md:px-8 md:first:pl-0 md:last:pr-0">
                        <h3 class="text-h3 text-fg">{{ $role['title'] }}</h3>
                        <ul class="mt-4 flex flex-col gap-2.5">
                            @foreach ($role['items'] as $item)
                                <li class="flex gap-2.5 text-fg-2">
                                    <svg class="mt-1 h-4 w-4 shrink-0 text-fg-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.25 6.25 11.5 13 4.75" /></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @guest
                <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-8">
                    <p class="text-h3 text-fg">เริ่มจากสมัครบัญชีผู้ใช้ แล้วจองที่จอดคันแรก</p>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button :href="route('register')">สมัครสมาชิก</x-ui.button>
                        <x-ui.button :href="route('login')" variant="secondary">เข้าสู่ระบบ</x-ui.button>
                    </div>
                </div>
            @endguest
        </section>
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-label text-fg-3 sm:px-6">
            <p>© {{ date('Y') }} Smart Parking</p>
            <p>โครงงานวิทยาการคอมพิวเตอร์</p>
        </div>
    </footer>
</body>

</html>
