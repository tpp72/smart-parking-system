<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' | Smart Parking System' : 'Smart Parking System' }}</title>

    @include('partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- หน้าบัญชีก่อนเข้าระบบ: เข้าสู่ระบบ / สมัคร / ลืมรหัสผ่าน / ตั้งรหัสผ่านใหม่ / ยืนยันอีเมล / ยืนยันรหัสผ่าน --}}
<body class="min-h-screen font-sans antialiased">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-3 focus:z-toast focus:rounded-card focus:border focus:border-line focus:bg-surface focus:px-4 focus:py-3 focus:text-label focus:font-semibold focus:text-fg focus:shadow-overlay">
        ข้ามไปเนื้อหาหลัก
    </a>

    <x-ui.page-progress />

    <div class="flex min-h-screen flex-col">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                @include('layouts.shell.brand', ['nav' => ['homeHref' => url('/')]])
                <x-ui.theme-switch popover />
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="flex flex-1 justify-center px-4 py-10 focus:outline-none sm:items-center sm:py-16">
            <div class="w-full max-w-md">
                @if ($title)
                    <div class="mb-6">
                        <h1 class="text-h1 text-fg">{{ $title }}</h1>
                        @if ($description)
                            <p class="mt-2 text-fg-2">{{ $description }}</p>
                        @endif
                    </div>
                @endif

                <div class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-8">
                    {{ $slot }}
                </div>

                {{ $after ?? '' }}
            </div>
        </main>
    </div>

    <x-ui.toast-region />
    <x-ui.confirm-dialog />
</body>

</html>
