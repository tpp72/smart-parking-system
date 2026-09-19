<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="ค้นหาลานจอดรถที่มีช่องว่าง เทียบค่าจอดรายชั่วโมง แล้วจองผ่าน Smart Parking">
    <title>ตลาดที่จอดรถ — Smart Parking</title>

    @include('partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    ตลาดที่จอดรถ (หน้าสาธารณะ ยังไม่อยู่ในเมนู) — ค้นหาลาน · เรียงตามช่องว่าง / ค่าจอด · สถานะช่องจอด ณ ตอนนี้
    ผู้ใช้จองได้จากการ์ด (ไปหน้าจองพร้อมเลือกลาน) · ผู้ที่ยังไม่เข้าสู่ระบบไปหน้าเข้าสู่ระบบ
    ต้องการ: $lots (paginator: available, occupied, slot_count, owner_name), $q, $sort
--}}
@use('App\Support\Format')

<body class="min-h-screen font-sans antialiased">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-3 focus:z-toast focus:rounded-card focus:border focus:border-line focus:bg-surface focus:px-4 focus:py-3 focus:text-label focus:font-semibold focus:text-fg focus:shadow-overlay">
        ข้ามไปเนื้อหาหลัก
    </a>

    <x-ui.page-progress />

    <header class="sticky top-0 z-30 border-b border-line bg-surface">
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

    <main id="main-content" tabindex="-1" class="mx-auto max-w-6xl px-4 py-8 focus:outline-none sm:px-6 lg:py-12">
        <h1 class="text-h1 text-fg">ตลาดที่จอดรถ</h1>
        <p class="mt-1 text-fg-2">ค้นหาลานจอดที่มีช่องว่าง เทียบค่าจอดรายชั่วโมง แล้วจองล่วงหน้า</p>

        <form method="GET" role="search" class="mt-6 flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <x-ui.field label="ค้นหาลาน" for="q" class="min-w-0 flex-1 sm:max-w-md">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="ชื่อลาน ที่อยู่ เขต จังหวัด หรือจุดสังเกต" />
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($q !== '')
                <x-ui.button variant="ghost" :href="route('marketplace.index', array_filter(['sort' => $sort === 'available' ? null : $sort]))">ล้างคำค้น</x-ui.button>
            @endif
        </form>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ $lots->total() }}</span> ลาน</p>
            <nav aria-label="เรียงลำดับ" class="inline-flex rounded-control border border-line bg-surface-2 p-0.5">
                @foreach (['available' => 'ช่องว่างมากสุด', 'rate' => 'ค่าจอดถูกสุด'] as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => null]) }}" @if ($sort === $key) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-touch items-center rounded-control px-4 text-label transition-colors duration-fast',
                            'bg-surface font-semibold text-fg shadow-1' => $sort === $key,
                            'text-fg-2 hover:text-fg' => $sort !== $key,
                        ])>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        @if ($lots->isEmpty())
            <div class="mt-4 rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state :title="$q !== '' ? 'ไม่พบลานที่ตรงกับคำค้น' : 'ยังไม่มีลานจอดในระบบ'" description="ลองค้นหาด้วยชื่อเขต จังหวัด หรือจุดสังเกตอื่น" />
            </div>
        @else
            <ul class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lots as $lot)
                    @php
                        $address = collect([$lot->address, $lot->district, $lot->province])->filter()->implode(' ');
                        $available = (int) $lot->available;
                        $occupied = (int) $lot->occupied;
                        $reserved = max(0, (int) $lot->slot_count - $available - $occupied);
                    @endphp
                    <li class="flex min-w-0 flex-col gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
                        <div class="min-w-0">
                            <h2 class="text-h3 text-fg">{{ $lot->name }}</h2>
                            <p class="mt-0.5 text-label text-fg-2">
                                {{ $address ?: \Illuminate\Support\Str::limit((string) $lot->location, 60) ?: 'ยังไม่ได้ระบุที่อยู่' }}
                                @if ($lot->landmark) · ใกล้ {{ $lot->landmark }} @endif
                            </p>
                        </div>

                        @if ((int) $lot->slot_count > 0)
                            <x-ui.occupancy-bar :available="$available" :reserved="$reserved" :occupied="$occupied" />
                        @else
                            <p class="text-label text-warning">ยังไม่มีช่องจอด</p>
                        @endif

                        <div class="mt-auto flex items-end justify-between gap-3 border-t border-line pt-3">
                            <p>
                                <span class="num text-h2 text-fg">{{ Format::baht($lot->hourly_rate) }}</span>
                                <span class="text-label text-fg-2">/ ชม.</span>
                            </p>
                            <p class="truncate text-caption text-fg-3">{{ $lot->owner_name ? 'เจ้าของ '.$lot->owner_name : 'ลานของผู้ดูแลระบบ' }}</p>
                        </div>

                        @auth
                            @if (auth()->user()->role === 'user')
                                @if ($available > 0)
                                    <x-ui.button :href="route('user.reservations.create', ['lot_id' => $lot->id])" class="w-full">จองลานนี้</x-ui.button>
                                @else
                                    <x-ui.button variant="secondary" class="w-full" disabled>ช่องจอดเต็ม</x-ui.button>
                                @endif
                            @endif
                        @else
                            <x-ui.button :href="route('login')" variant="secondary" class="w-full">เข้าสู่ระบบเพื่อจอง</x-ui.button>
                        @endauth
                    </li>
                @endforeach
            </ul>

            <x-ui.pagination :paginator="$lots" class="mt-6" />
        @endif
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-label text-fg-3 sm:px-6">
            <p>© {{ date('Y') }} Smart Parking</p>
            <p>โครงงานวิทยาการคอมพิวเตอร์</p>
        </div>
    </footer>

</body>

</html>
