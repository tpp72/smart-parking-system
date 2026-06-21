<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" id="html-root">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Smart Parking') }}</title>

    <!-- Theme init: prevent flash of wrong theme -->
    <script>if(localStorage.getItem('sp-theme')==='light')document.getElementById('html-root').classList.add('light-theme');</script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-200 antialiased sp-bg min-h-screen relative overflow-hidden">

    {{-- Theme Toggle --}}
    <div x-data="{
        isLight: document.getElementById('html-root').classList.contains('light-theme'),
        toggle() {
            this.isLight = !this.isLight;
            document.getElementById('html-root').classList.toggle('light-theme', this.isLight);
            localStorage.setItem('sp-theme', this.isLight ? 'light' : 'dark');
        }
    }" class="fixed top-4 right-4 z-50">
        <button @click="toggle()"
            :title="isLight ? 'เปลี่ยนเป็น Dark Mode' : 'เปลี่ยนเป็น Light Mode'"
            class="sp-guest-toggle inline-flex items-center justify-center w-9 h-9 rounded-xl transition-all duration-200"
            style="backdrop-filter:blur(6px)">
            <svg x-show="isLight" x-cloak xmlns="http://www.w3.org/2000/svg"
                class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5" fill="currentColor" stroke="none"/>
                <line x1="12" y1="2"  x2="12" y2="4"  stroke-linecap="round"/>
                <line x1="12" y1="20" x2="12" y2="22" stroke-linecap="round"/>
                <line x1="4.22" y1="4.22"  x2="5.64" y2="5.64"  stroke-linecap="round"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke-linecap="round"/>
                <line x1="2"  y1="12" x2="4"  y2="12" stroke-linecap="round"/>
                <line x1="20" y1="12" x2="22" y2="12" stroke-linecap="round"/>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke-linecap="round"/>
                <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"  stroke-linecap="round"/>
            </svg>
            <svg x-show="!isLight" x-cloak xmlns="http://www.w3.org/2000/svg"
                class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
            </svg>
        </button>
    </div>

    <div id="sp-page-loader">
        <div class="sp-loader-ring"></div>
        <span class="sp-loader-text">กำลังโหลดข้อมูล...</span>
    </div>

    <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

        {{-- Logo --}}
        <div class="mb-6">
            <a href="/" class="flex flex-col items-center gap-3">
                <span class="sp-guest-icon inline-flex items-center justify-center w-20 h-20 rounded-2xl shadow-[0_0_18px_rgba(220,38,38,0.5)]">
                    <x-application-logo class="w-10 h-10 text-red-500" />
                </span>
                <span class="text-xl font-extrabold text-red-500 tracking-wide sp-glow-text">
                    Smart Parking
                </span>
            </a>
        </div>

        {{-- Auth Card --}}
        <div class="sp-card sp-guest-card w-full sm:max-w-md px-8 py-8 rounded-2xl shadow-[0_0_28px_rgba(220,38,38,0.35)]">
            {{ $slot }}
        </div>

    </div>
</body>

</html>
