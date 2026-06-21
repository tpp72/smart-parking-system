<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ตลาดที่จอดรถ — Smart Parking</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sp-bg min-h-screen text-white">

    {{-- Minimal top bar --}}
    <header class="sticky top-0 z-50 bg-black/80 border-b border-red-900/60 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-4">
            <a href="/" class="flex items-center gap-2.5 shrink-0">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-red-900 bg-black/60">
                    <x-application-logo class="h-5 w-auto fill-current text-red-500" />
                </span>
                <span class="hidden sm:inline font-extrabold tracking-wide text-red-500 sp-glow-text text-sm">Smart Parking</span>
            </a>
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="sp-btn sp-btn-outline text-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="sp-btn sp-btn-outline text-sm">เข้าสู่ระบบ</a>
                    <a href="{{ route('register') }}" class="sp-btn sp-btn-primary text-sm">สมัครใช้งาน</a>
                @endauth
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="text-center py-6">
            <h1 class="text-4xl font-extrabold sp-glow-text">ตลาดที่จอดรถ</h1>
            <p class="text-gray-400 mt-2">ค้นหาที่จอดรถว่างในพื้นที่ของคุณ</p>
        </div>

        {{-- Search --}}
        <div class="max-w-2xl mx-auto">
            <form method="GET" class="flex gap-3">
                <input name="q" value="{{ $q }}" placeholder="ค้นหาชื่อลานหรือสถานที่..."
                    class="flex-1 rounded-xl bg-black/40 border border-red-900/60 text-white placeholder-gray-400 focus:ring-0 focus:border-red-600 px-4 py-2.5 text-sm" />
                <input type="hidden" name="sort" value="{{ $sort }}" />
                <button type="submit" class="sp-btn sp-btn-primary">ค้นหา</button>
                @if($q)
                    <a href="{{ route('marketplace.index') }}" class="sp-btn sp-btn-outline">ล้าง</a>
                @endif
            </form>
        </div>

        {{-- Sort + count --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-gray-400 text-sm">{{ $lots->total() }} ลานจอด</p>
            <div class="flex gap-1">
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'available']) }}"
                   class="px-4 py-1.5 rounded-xl text-sm font-semibold transition {{ $sort === 'available' ? 'bg-red-600/30 text-red-200 ring-1 ring-red-700' : 'text-gray-400 hover:text-white hover:bg-white/[0.06]' }}">
                    ว่างมากสุด
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'rate']) }}"
                   class="px-4 py-1.5 rounded-xl text-sm font-semibold transition {{ $sort === 'rate' ? 'bg-red-600/30 text-red-200 ring-1 ring-red-700' : 'text-gray-400 hover:text-white hover:bg-white/[0.06]' }}">
                    ราคาถูกสุด
                </a>
            </div>
        </div>

        {{-- Lot cards --}}
        @if($lots->isEmpty())
            <div class="text-center py-20 text-gray-500">
                <p class="text-2xl mb-2">ไม่พบลานจอด</p>
                <p class="text-sm">ลองค้นหาด้วยคำอื่น</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($lots as $lot)
                <div class="sp-card rounded-2xl p-5 flex flex-col gap-3 hover:border-red-800/60 transition">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-extrabold text-white text-lg leading-tight">{{ $lot->name }}</h2>
                            @php
                                $addressLine = collect([$lot->address, $lot->district, $lot->province])->filter()->implode(', ');
                            @endphp
                            @if($addressLine)
                                <p class="text-gray-300 text-xs mt-0.5">{{ $addressLine }}</p>
                            @endif
                            @if($lot->landmark)
                                <p class="text-gray-500 text-xs">ใกล้ {{ $lot->landmark }}</p>
                            @elseif($lot->location && !$addressLine)
                                <p class="text-gray-400 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($lot->location, 60) }}</p>
                            @endif
                        </div>
                        @if($lot->available > 0)
                            <span class="shrink-0 sp-badge sp-badge-ok">ว่าง {{ $lot->available }}</span>
                        @else
                            <span class="shrink-0 sp-badge sp-badge-danger">เต็ม</span>
                        @endif
                    </div>

                    {{-- Slot bar --}}
                    @php
                        $total = max($lot->slot_count, 1);
                        $availPct = round($lot->available / $total * 100);
                        $occPct   = round($lot->occupied  / $total * 100);
                    @endphp
                    <div>
                        <div class="flex h-2 rounded-full overflow-hidden bg-white/5">
                            @if($availPct > 0)<div class="bg-green-500/60 transition-all" style="width:{{ $availPct }}%"></div>@endif
                            @if($occPct > 0)<div class="bg-red-500/60 transition-all" style="width:{{ $occPct }}%"></div>@endif
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>ว่าง {{ $lot->available }} / {{ $lot->slot_count }} ช่อง</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-2xl font-extrabold text-red-300">฿{{ number_format((float)$lot->hourly_rate, 0) }}</span>
                            <span class="text-gray-500 text-xs ml-1">/ชั่วโมง</span>
                        </div>
                        @if($lot->owner_name)
                            <span class="text-xs text-gray-500">โดย {{ $lot->owner_name }}</span>
                        @endif
                    </div>

                    @auth
                        @if(auth()->user()->role === 'user')
                            @if($lot->available > 0)
                                <a href="{{ route('user.reservations.create') }}"
                                   class="sp-btn sp-btn-primary w-full text-center">จองเลย</a>
                            @else
                                <button disabled class="sp-btn sp-btn-outline w-full opacity-50 cursor-not-allowed">ที่จอดเต็ม</button>
                            @endif
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="sp-btn sp-btn-outline w-full text-center text-sm">เข้าสู่ระบบเพื่อจอง</a>
                    @endauth
                </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $lots->links() }}</div>
        @endif

    </div>

    <footer class="mt-12 border-t border-red-900/20 text-center text-gray-600 text-xs py-6">
        Smart Parking System &copy; {{ date('Y') }}
    </footer>

</body>
</html>
