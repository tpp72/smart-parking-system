<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Smart Parking System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-animated text-white min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="flex justify-between items-center px-10 py-5 border-b border-red-800 backdrop-blur-sm">
        <h1 class="text-2xl font-bold text-red-600 glow-text">
            SMART PARKING
        </h1>

        <div class="space-x-4">
            @auth
                <a href="{{ route('dashboard') }}"
                    class="bg-red-600 hover:bg-red-700 px-5 py-2 rounded-lg font-semibold transition glow-btn">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="border border-red-600 text-red-500 hover:bg-red-600 hover:text-white px-5 py-2 rounded-lg transition">
                    Login
                </a>
                <a href="{{ route('register') }}"
                    class="bg-red-600 hover:bg-red-700 px-5 py-2 rounded-lg font-semibold transition glow-btn">
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
    <section class="py-16 px-8 border-t border-red-900">
        <div class="max-w-6xl mx-auto grid md:grid-cols-3 gap-8 text-center">

            <div class="bg-gray-900/70 p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Real-time Status</h3>
                <p class="text-gray-400">แสดงสถานะช่องจอดว่าง/ไม่ว่างแบบทันที</p>
            </div>

            <div class="bg-gray-900/70 p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Vehicle Tracking</h3>
                <p class="text-gray-400">บันทึกข้อมูลรถและประวัติการเข้า-ออก</p>
            </div>

            <div class="bg-gray-900/70 p-6 rounded-xl border border-red-900 hover:border-red-600 transition">
                <h3 class="text-xl font-bold text-red-500 mb-3">Admin Control</h3>
                <p class="text-gray-400">จัดการช่องจอดและดูรายงานได้ครบถ้วน</p>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-6 border-t border-red-900 text-gray-500 text-sm">
        © {{ date('Y') }} Smart Parking System | Computer Science Project
    </footer>

</body>

</html>
