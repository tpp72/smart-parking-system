<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Smart Parking') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-200 antialiased bg-black relative overflow-hidden">

    {{-- Red Glow Background --}}
    <div
        class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,0.25),transparent_40%),radial-gradient(circle_at_80%_80%,rgba(220,38,38,0.15),transparent_40%)]">
    </div>

    <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

        {{-- Logo --}}
        <div class="mb-6">
            <a href="/" class="flex flex-col items-center gap-3">
                <span
                    class="inline-flex items-center justify-center w-20 h-20 rounded-2xl border border-red-900 bg-black/70 shadow-[0_0_18px_rgba(220,38,38,0.5)]">
                    <x-application-logo class="w-10 h-10 text-red-500" />
                </span>
                <span class="text-xl font-extrabold text-red-500 tracking-wide sp-glow-text">
                    Smart Parking
                </span>
            </a>
        </div>

        {{-- Auth Card --}}
        <div
            class="w-full sm:max-w-md px-8 py-8
                    bg-black/80 backdrop-blur
                    border border-red-900
                    rounded-2xl
                    shadow-[0_0_28px_rgba(220,38,38,0.35)]">

            {{ $slot }}

        </div>

    </div>
</body>

</html>
