<!DOCTYPE html>
<html lang="th" id="html-root">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Parking System</title>

    <!-- Theme init: ป้องกัน flash of wrong theme -->
    <script>if(localStorage.getItem('sp-theme')==='light')document.getElementById('html-root').classList.add('light-theme');</script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-animated text-white min-h-screen flex flex-col transition-colors duration-300">

    <!-- Navbar -->
    <nav class="flex justify-between items-center px-6 sm:px-10 py-5 border-b border-red-800 backdrop-blur-sm transition-colors duration-300">
        <h1 class="text-2xl font-bold text-red-600 glow-text tracking-wide">
            SMART PARKING
        </h1>

        <div class="flex items-center gap-3">

            {{-- ปุ่ม Dark / Light Theme --}}
            <div x-data="{
                isLight: document.getElementById('html-root').classList.contains('light-theme'),
                toggle() {
                    this.isLight = !this.isLight;
                    document.getElementById('html-root').classList.toggle('light-theme', this.isLight);
                    localStorage.setItem('sp-theme', this.isLight ? 'light' : 'dark');
                }
            }">
                <button @click="toggle()"
                    :title="isLight ? 'เปลี่ยนเป็น Dark Mode' : 'เปลี่ยนเป็น Light Mode'"
                    class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-red-800/60 bg-black/30 text-gray-300 hover:border-red-600 hover:text-white transition-all duration-200"
                    style="backdrop-filter:blur(6px)">

                    {{-- Sun icon (แสดงเมื่ออยู่ใน Light mode) --}}
                    <svg x-show="isLight" x-cloak xmlns="http://www.w3.org/2000/svg"
                        class="w-4 h-4 text-yellow-500" fill="none" viewBox="0 0 24 24"
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

                    {{-- Moon icon (แสดงเมื่ออยู่ใน Dark mode) --}}
                    <svg x-show="!isLight" x-cloak xmlns="http://www.w3.org/2000/svg"
                        class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
                    </svg>
                </button>
            </div>

            {{-- Login / Register / Dashboard --}}
            @auth
                <a href="{{ route('dashboard') }}"
                    class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg font-semibold transition glow-btn">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="border border-red-600 text-red-500 hover:bg-red-600 hover:text-white px-5 py-2 rounded-lg transition font-semibold">
                    Login
                </a>
                <a href="{{ route('register') }}"
                    class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg font-semibold transition glow-btn">
                    Register
                </a>
            @endauth
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="flex flex-1 items-center justify-center text-center px-6">
        <div class="fade-in">

            <h2 class="text-6xl font-extrabold mb-6 glow-text">
                Smart Parking
                <span class="text-red-600">System</span>
            </h2>

            <p class="text-gray-400 text-lg mb-10 max-w-2xl mx-auto">
                ระบบจัดการที่จอดรถอัจฉริยะสำหรับควบคุมสถานะช่องจอด
                ตรวจสอบการเข้า-ออก และจัดการข้อมูลอย่างเป็นระบบ
                ด้วยเทคโนโลยี Web Application
            </p>

            @auth
                <a href="{{ route('dashboard') }}"
                    class="bg-red-600 hover:bg-red-700 text-white px-10 py-4 rounded-xl text-lg font-semibold transition glow-btn">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="bg-red-600 hover:bg-red-700 text-white px-10 py-4 rounded-xl text-lg font-semibold transition glow-btn">
                    Get Started
                </a>
            @endauth

        </div>
    </div>

    <!-- Feature Section -->
    <section class="py-16 px-8 border-t border-red-900 transition-colors duration-300">
        <div class="max-w-6xl mx-auto grid md:grid-cols-3 gap-8 text-center">

            <div class="welcome-card p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Real-time Status</h3>
                <p class="text-gray-400">แสดงสถานะช่องจอดว่าง/ไม่ว่างแบบทันที</p>
            </div>

            <div class="welcome-card p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Vehicle Tracking</h3>
                <p class="text-gray-400">บันทึกข้อมูลรถและประวัติการเข้า-ออก</p>
            </div>

            <div class="welcome-card p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Admin Control</h3>
                <p class="text-gray-400">จัดการช่องจอดและดูรายงานได้ครบถ้วน</p>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-6 border-t border-red-900 text-gray-500 text-sm transition-colors duration-300">
        © {{ date('Y') }} Smart Parking System | Computer Science Project
    </footer>

</body>

</html>
