<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" id="html-root">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $routeName = request()->route()?->getName();
        $pageTitle = $routeName ? config("page_titles.$routeName") : null;
    @endphp

    <title>
        {{ $pageTitle ? $pageTitle . ' | ' . config('app.name') : config('app.name') }}
    </title>

    <!-- Theme init: prevent flash of wrong theme -->
    <script>if(localStorage.getItem('sp-theme')==='light')document.getElementById('html-root').classList.add('light-theme');</script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div id="sp-page-loader">
        <div class="sp-loader-ring"></div>
        <span class="sp-loader-text">กำลังโหลดข้อมูล...</span>
    </div>
    <div class="min-h-screen sp-bg text-white">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @isset($header)
            <header class="sp-header shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6
                lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>
</body>

</html>
