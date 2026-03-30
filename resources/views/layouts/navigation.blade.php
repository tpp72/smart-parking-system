<nav x-data="{ open: false }" class="sticky top-0 z-50 bg-black/80 border-b border-red-900/60 backdrop-blur-md text-white">
    @php
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';

        /* ── Nav link helper ───────────────────────────────────── */
        $navClass = fn(string|array $route) =>
            'inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all duration-150 '
            . (request()->routeIs(is_array($route) ? $route : [$route])
                ? 'bg-red-600/20 text-red-200 ring-1 ring-red-800/60'
                : 'text-gray-400 hover:text-white hover:bg-white/[0.06]');
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14 gap-4">

            {{-- ── Logo ────────────────────────────────────── --}}
            <a href="{{ $isAdmin ? route('admin.dashboard') : route('user.dashboard') }}"
               class="shrink-0 flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-red-900 bg-black/60">
                    <x-application-logo class="h-5 w-auto fill-current text-red-500" />
                </span>
                <span class="hidden sm:inline font-extrabold tracking-wide text-red-500 sp-glow-text text-sm">
                    Smart Parking
                </span>
            </a>

            {{-- ── Quick Access (desktop) ───────────────────── --}}
            <div class="hidden md:flex items-center gap-1 flex-1 px-4">

                @if($isAdmin)
                    {{-- Admin Quick Access --}}
                    <a href="{{ route('admin.dashboard') }}" class="{{ $navClass('admin.dashboard') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Dashboard
                    </a>

                    <div class="w-px h-5 bg-white/10 mx-1"></div>

                    <a href="{{ route('admin.check-in.create') }}" class="{{ $navClass('admin.check-in.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
                        รถเข้า
                    </a>

                    <a href="{{ route('admin.check-out.index') }}" class="{{ $navClass('admin.check-out.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l4 4m0 0l-4 4m4-4H3"/></svg>
                        รถออก
                    </a>

                    <a href="{{ route('admin.reservations.index') }}" class="{{ $navClass(['admin.reservations.*']) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        การจอง
                    </a>

                    <a href="{{ route('admin.parking-logs.index') }}" class="{{ $navClass('admin.parking-logs.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        ประวัติ
                    </a>

                    <a href="{{ route('admin.payments.index') }}" class="{{ $navClass('admin.payments.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ชำระเงิน
                        @php $unpaidCount = \App\Models\Payment::where('payment_status','unpaid')->count(); @endphp
                        @if($unpaidCount > 0)
                            <span class="text-xs bg-red-500/30 text-red-300 rounded-full px-1.5">{{ $unpaidCount }}</span>
                        @endif
                    </a>

                    <div class="w-px h-5 bg-white/10 mx-1"></div>

                    <a href="{{ route('admin.vehicles.index') }}" class="{{ $navClass('admin.vehicles.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg>
                        รถ
                    </a>

                    <a href="{{ route('admin.parking-slots.index') }}" class="{{ $navClass('admin.parking-slots.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                        ช่องจอด
                    </a>

                @else
                    {{-- User Quick Access --}}
                    <a href="{{ route('user.dashboard') }}" class="{{ $navClass('user.dashboard') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        หน้าหลัก
                    </a>

                    <a href="{{ route('user.reservations.create') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-bold transition-all duration-150 bg-red-600 hover:bg-red-500 text-white sp-glow-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        จองที่จอด
                    </a>

                    <div class="w-px h-5 bg-white/10 mx-1"></div>

                    <a href="{{ route('user.reservations.index') }}" class="{{ $navClass('user.reservations.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        การจองของฉัน
                    </a>

                    <a href="{{ route('user.vehicles.index') }}" class="{{ $navClass('user.vehicles.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg>
                        รถของฉัน
                    </a>

                    <a href="{{ route('user.parking-logs.index') }}" class="{{ $navClass('user.parking-logs.*') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        ประวัติ
                    </a>
                @endif
            </div>

            {{-- ── Right: Bell + User Menu ──────────────────── --}}
            <div class="hidden md:flex items-center gap-2 shrink-0">

                {{-- Bell --}}
                @auth
                @php
                    $unreadCount = \App\Models\Notification::where('user_id', auth()->id())
                        ->where('is_read', false)->count();
                @endphp
                <a href="{{ route('notifications.index') }}"
                   class="relative inline-flex items-center justify-center w-9 h-9 rounded-xl border border-red-900/60 bg-black/40 text-gray-400 hover:text-white hover:bg-white/[0.06] transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    @if($unreadCount > 0)
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full bg-red-600 text-white">
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    @endif
                </a>
                @endauth

                {{-- User dropdown --}}
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-red-900/60 bg-black/40
                                       text-sm text-gray-300 hover:text-white hover:bg-white/[0.06] transition">
                            <span class="w-6 h-6 rounded-lg bg-red-900/40 border border-red-800/60 flex items-center justify-center text-xs font-black text-red-300">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden lg:inline font-semibold max-w-[100px] truncate">{{ Auth::user()->name }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md border border-red-900/60 text-red-400 bg-red-900/20 font-bold uppercase">
                                {{ Auth::user()->role }}
                            </span>
                            <svg class="w-3.5 h-3.5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="p-1">
                            <x-dropdown-link :href="route('profile.edit')"
                                class="!rounded-xl !text-gray-200 hover:!text-white hover:!bg-red-900/30">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline mr-1.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                โปรไฟล์
                            </x-dropdown-link>

                            <div class="my-1 border-t border-red-900/40"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    class="!rounded-xl !text-gray-400 hover:!text-red-300 hover:!bg-red-900/20"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 inline mr-1.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    ออกจากระบบ
                                </x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- ── Hamburger (mobile) ───────────────────────── --}}
            <button @click="open = !open"
                class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-xl border border-red-900/60 bg-black/40 text-gray-300 hover:text-white transition">
                <svg class="w-5 h-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{ 'hidden': open, 'block': !open }" class="block" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{ 'hidden': !open, 'block': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ── Mobile Menu ──────────────────────────────────────── --}}
    <div x-show="open" x-cloak class="md:hidden border-t border-red-900/40 bg-black/90 backdrop-blur-md">
        <div class="px-4 py-4 space-y-1">

            @if($isAdmin)
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-600 px-2 pb-1">Quick Access</p>
                <a href="{{ route('admin.dashboard') }}"    class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Dashboard</a>
                <a href="{{ route('admin.check-in.create') }}"  class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('admin.check-in.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg> รถเข้า</a>
                <a href="{{ route('admin.check-out.index') }}"  class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('admin.check-out.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l4 4m0 0l-4 4m4-4H3"/></svg> รถออก</a>
                <a href="{{ route('admin.reservations.index') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('admin.reservations.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> การจอง</a>
                <a href="{{ route('admin.parking-logs.index') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('admin.parking-logs.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg> ประวัติ</a>
            @else
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-600 px-2 pb-1">Quick Access</p>
                <a href="{{ route('user.dashboard') }}"        class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('user.dashboard') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> หน้าหลัก</a>
                <a href="{{ route('user.reservations.create') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-bold bg-red-600/15 text-red-300 border border-red-800/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> จองที่จอด</a>
                <a href="{{ route('user.reservations.index') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('user.reservations.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> การจองของฉัน</a>
                <a href="{{ route('user.vehicles.index') }}"   class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('user.vehicles.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg> รถของฉัน</a>
                <a href="{{ route('user.parking-logs.index') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold {{ request()->routeIs('user.parking-logs.*') ? 'bg-red-600/20 text-red-200' : 'text-gray-300 hover:bg-white/[0.06]' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> ประวัติ</a>
            @endif

            <div class="border-t border-red-900/30 mt-2 pt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-400 hover:bg-white/[0.06]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    โปรไฟล์
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-500 hover:text-red-300 hover:bg-red-900/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        ออกจากระบบ
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
