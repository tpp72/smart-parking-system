<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $routeName = request()->route()?->getName();
        // คีย์ใน config/page_titles.php เป็นชื่อ route ที่มีจุด (admin.dashboard) — ต้องอ่านทั้งอาเรย์ ไม่ใช่ config('page_titles.admin.dashboard') ที่ Laravel จะตีความจุดเป็นระดับชั้น
        $pageTitle = $routeName ? (config('page_titles')[$routeName] ?? null) : null;
        // เมนูตามบทบาท (App\Support\Navigation) — Admin/Owner: sidebar · User: แถบบน + แท็บล่าง
        $nav = auth()->check() ? \App\Support\Navigation::for(auth()->user()) : null;
    @endphp

    {{-- ชื่อระบบบนแท็บใช้ชื่อเดียวกับตราบนแถบเมนู (APP_NAME ใน .env เป็นชื่อสำหรับระบบภายใน) --}}
    <title>{{ $pageTitle ? $pageTitle.' | Smart Parking' : 'Smart Parking' }}</title>

    @include('partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body @class(['font-sans antialiased', 'sp-has-bottom-bar' => filled($nav['bottom'] ?? null)])>
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-3 focus:z-toast focus:rounded-card focus:border focus:border-line focus:bg-surface focus:px-4 focus:py-3 focus:text-label focus:font-semibold focus:text-fg focus:shadow-overlay">
        ข้ามไปเนื้อหาหลัก
    </a>

    <x-ui.page-progress />

    @if ($nav)
        <div @class(['min-h-screen', 'lg:pl-[4.5rem] xl:pl-64' => $nav['staff']])>
            @if ($nav['staff'])
                @include('layouts.shell.staff-sidebar')
            @endif

            @include('layouts.shell.topbar')

            <main id="main-content" tabindex="-1"
                @class(['focus:outline-none', 'pb-[calc(3.75rem+env(safe-area-inset-bottom))] lg:pb-0' => filled($nav['bottom'])])>
                @isset($header)
                    <div class="border-b border-line bg-surface">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                {{ $slot }}
            </main>
        </div>

        @unless ($nav['limited'])
            @include('layouts.shell.bottom-bar')
            @include('layouts.shell.nav-drawer')
        @endunless
    @else
        {{-- ยังไม่เข้าสู่ระบบ: แถบบนมีเฉพาะชื่อระบบและธีม --}}
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                @include('layouts.shell.brand')
                <x-ui.theme-switch popover />
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="focus:outline-none">
            {{ $slot }}
        </main>
    @endif

    {{-- หน้าที่ rebuild แล้วเปิด flash → toast ด้วย <x-app-layout flash-toast> (หน้าเก่ายังแสดง flash ในหน้าเอง) --}}
    <x-ui.toast-region :flash="$attributes->has('flash-toast')" />
    <x-ui.confirm-dialog />
</body>

</html>
