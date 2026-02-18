<nav x-data="{ open: false }" class="bg-black/70 border-b border-red-900 backdrop-blur text-white">
    @php
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            {{-- LEFT: Logo + Menu --}}
            <div class="flex">
                {{-- Logo --}}
                <div class="shrink-0 flex items-center">
                    <a href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}"
                        class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-red-900 bg-black/60">
                            <x-application-logo class="block h-6 w-auto fill-current text-red-500" />
                        </span>
                        <span class="hidden sm:inline font-extrabold tracking-wide text-red-500 sp-glow-text">
                            Smart Parking
                        </span>
                    </a>
                </div>

                {{-- Navigation Links --}}
                <div class="hidden sm:flex sm:items-center sm:ms-10 sm:space-x-2">
                    @if ($isAdmin)
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')"
                            class="!border-0 !rounded-xl !px-4 !py-2 !text-sm !font-bold
                                   !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30
                                   {{ request()->routeIs('admin.dashboard') ? '!bg-red-600/20 !text-red-200 !ring-1 !ring-red-900/70 sp-glow-text' : '' }}">
                            Admin Dashboard
                        </x-nav-link>
                        <a href="{{ route('admin.reservation-logs.index') }}"
                            class="!border-0 !rounded-xl !px-4 !py-2 !text-sm !font-bold
                                   !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30
                                   {{ request()->routeIs('admin.reservation-logs.index') ? '!bg-red-600/20 !text-red-200 !ring-1 !ring-red-900/70 sp-glow-text' : '' }}">ประวัติการจอง</a>
                        <a href="{{ route('admin.admin-actions.index') }}"
                            class="!border-0 !rounded-xl !px-4 !py-2 !text-sm !font-bold
                                   !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30
                                   {{ request()->routeIs('admin.admin-actions.index') ? '!bg-red-600/20 !text-red-200 !ring-1 !ring-red-900/70 sp-glow-text' : '' }}">Admin
                            Actions</a>
                    @else
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                            class="!border-0 !rounded-xl !px-4 !py-2 !text-sm !font-bold
                                   !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30
                                   {{ request()->routeIs('dashboard') ? '!bg-red-600/20 !text-red-200 !ring-1 !ring-red-900/70 sp-glow-text' : '' }}">
                            Dashboard
                        </x-nav-link>

                        <a href="/vehicles"
                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold transition text-gray-200 hover:text-red-300 hover:bg-red-900/30">
                            Vehicles
                        </a>

                        <a href="/parking/slots"
                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold transition text-gray-200 hover:text-red-300 hover:bg-red-900/30">
                            Slots
                        </a>

                        <a href="/parking/logs"
                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold transition text-gray-200 hover:text-red-300 hover:bg-red-900/30">
                            Logs
                        </a>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-red-900 bg-black/50
                                   text-gray-200 hover:text-red-300 hover:bg-red-900/30
                                   focus:outline-none focus:bg-red-900/40 focus:text-red-200 transition ease-in-out duration-150">
                            <div class="font-bold">{{ Auth::user()->name }}</div>

                            <span
                                class="text-xs px-2 py-1 rounded-full border border-red-900 text-red-300 bg-red-900/20">
                                {{ Auth::user()->role }}
                            </span>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4 text-red-400" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="p-1">
                            <x-dropdown-link :href="route('profile.edit')"
                                class="!rounded-xl !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <div class="my-1 border-t border-red-900/60"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    class="!rounded-xl !text-gray-200 hover:!text-red-300 hover:!bg-red-900/30"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-xl text-gray-200
                           hover:text-red-300 hover:bg-red-900/30
                           focus:outline-none focus:bg-red-900/40 focus:text-red-200
                           transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>
</nav>
