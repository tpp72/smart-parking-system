{{--
    โครงหน้าแจ้งข้อผิดพลาด (404 / 403 / 419 / 429 / 500 / 503)
    ใช้โครงเดียวกับหน้าก่อนเข้าระบบ แต่ไม่พึ่ง session/ผู้ใช้ เพราะบางกรณี (419, 503) session ใช้ไม่ได้แล้ว
--}}
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>@yield('title') | Smart Parking</title>

    @include('partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex h-14 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                @include('layouts.shell.brand', ['nav' => ['homeHref' => url('/')]])
                <x-ui.theme-switch popover />
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="flex flex-1 items-center justify-center px-4 py-12 focus:outline-none">
            <div class="w-full max-w-lg rounded-card border border-line bg-surface p-6 text-center shadow-1 sm:p-8">
                <p class="num text-caption font-semibold text-fg-3">@yield('code')</p>
                <h1 class="mt-2 text-h1 text-fg">@yield('title')</h1>
                <p class="mt-3 text-body text-fg-2">@yield('message')</p>

                <div class="mt-6 flex flex-col items-stretch justify-center gap-2 sm:flex-row sm:items-center">
                    @hasSection('action')
                        @yield('action')
                    @endif
                    <x-ui.button href="{{ url('/') }}" variant="secondary">กลับหน้าแรก</x-ui.button>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
