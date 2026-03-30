<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            {{-- ══════════════════════════════════════════════════════
                 Header + Quick Actions
            ══════════════════════════════════════════════════════ --}}
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">Dashboard</h1>
                        <p class="text-gray-400 text-sm mt-0.5">Smart Parking System — ภาพรวมระบบ</p>
                    </div>
                    <span class="text-xs text-gray-500 hidden sm:block">{{ now()->format('d M Y, H:i') }}</span>
                </div>

                {{-- Quick action buttons --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <a href="{{ route('admin.check-in.create') }}"
                        class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
                        <span class="text-sm font-semibold">รถเข้า</span>
                        <span class="text-xs opacity-50">Check-In</span>
                    </a>
                    <a href="{{ route('admin.check-out.index') }}"
                        class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l4 4m0 0l-4 4m4-4H3"/></svg>
                        <span class="text-sm font-semibold">รถออก</span>
                        <span class="text-xs opacity-50">Check-Out</span>
                    </a>
                    <a href="{{ route('admin.reservations.create') }}"
                        class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-sm font-semibold">สร้างการจอง</span>
                        <span class="text-xs opacity-50">Reserve</span>
                    </a>
                    <a href="{{ route('admin.vehicles.index') }}"
                        class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10h4l3 6H13v-6z"/></svg>
                        <span class="text-sm font-semibold">จัดการรถ</span>
                        <span class="text-xs opacity-50">Vehicles</span>
                    </a>
                    <a href="{{ route('admin.parking-lots.index') }}"
                        class="sp-btn sp-btn-outline flex-col items-center justify-center py-3 gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="text-sm font-semibold">ลานจอด</span>
                        <span class="text-xs opacity-50">Parking Lots</span>
                    </a>
                </div>

                {{-- Secondary quick links --}}
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.parking-slots.index') }}" class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition">ช่องจอด · Slots</a>
                    <a href="{{ route('admin.reservations.index') }}" class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition">รายการจอง · Reservations</a>
                    <a href="{{ route('admin.parking-logs.index') }}" class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition">ประวัติจอด · Parking Logs</a>
                    <a href="{{ route('admin.users.index') }}" class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition">ผู้ใช้งาน · Users</a>
                    <a href="{{ route('admin.devices.index') }}" class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/30 rounded-lg px-3 py-1.5 transition">อุปกรณ์ · Devices</a>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
                 KPI Cards
            ══════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- รถในลาน --}}
                <div class="sp-card rounded-2xl p-5 border border-yellow-600/25 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-900/20 to-transparent pointer-events-none rounded-2xl"></div>
                    <div class="flex items-start justify-between relative z-10">
                        <div>
                            <p class="text-gray-400 text-xs font-medium uppercase tracking-wider">รถในลานตอนนี้</p>
                            <p class="text-4xl font-extrabold mt-2 text-yellow-300">{{ $stats['active_now'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Active Vehicles</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg>
                        </div>
                    </div>
                </div>

                {{-- ช่องจอดว่าง --}}
                @php
                    $total    = $stats['slots_total'] ?? 0;
                    $avail    = $stats['slots_available'] ?? 0;
                    $pct      = $total > 0 ? round(($avail / $total) * 100) : 0;
                    $barColor = $pct > 50 ? 'bg-green-400' : ($pct > 20 ? 'bg-yellow-400' : 'bg-red-400');
                @endphp
                <div class="sp-card rounded-2xl p-5 border border-sky-600/25 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-sky-900/20 to-transparent pointer-events-none rounded-2xl"></div>
                    <div class="relative z-10">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium uppercase tracking-wider">ช่องจอดว่าง</p>
                                <p class="text-4xl font-extrabold mt-2 text-sky-300">{{ $avail }}</p>
                                <p class="text-xs text-gray-500 mt-1">Available Slots</p>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-sky-500/20 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs text-gray-400 mb-1">
                                <span>{{ $pct }}% ว่าง</span>
                                <span>{{ $total }} ทั้งหมด</span>
                            </div>
                            <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                <div class="{{ $barColor }} h-full rounded-full transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- รายได้วันนี้ --}}
                <div class="sp-card rounded-2xl p-5 border border-green-600/25 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-green-900/20 to-transparent pointer-events-none rounded-2xl"></div>
                    <div class="flex items-start justify-between relative z-10">
                        <div>
                            <p class="text-gray-400 text-xs font-medium uppercase tracking-wider">รายได้วันนี้</p>
                            <p class="text-3xl font-extrabold mt-2 text-green-300">
                                ฿{{ number_format((float)($stats['revenue_paid'] ?? 0), 2) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Revenue (Paid)</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                    </div>
                </div>

                {{-- ค้างชำระ --}}
                <div class="sp-card rounded-2xl p-5 border border-red-600/25 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-900/20 to-transparent pointer-events-none rounded-2xl"></div>
                    <div class="flex items-start justify-between relative z-10">
                        <div>
                            <p class="text-gray-400 text-xs font-medium uppercase tracking-wider">ค้างชำระวันนี้</p>
                            <p class="text-4xl font-extrabold mt-2 text-red-300">{{ $stats['unpaid_count'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 mt-1">Unpaid Bills</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-red-500/20 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════════════════
                 Mid Row: Unpaid + Penalties + Scans
            ══════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Unpaid payments --}}
                <div class="sp-card rounded-2xl p-6 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-red-400 animate-pulse"></span>
                            <h2 class="font-extrabold text-base">ค้างชำระล่าสุด</h2>
                        </div>
                        <a href="{{ route('admin.parking-logs.index') }}" class="text-xs text-gray-400 hover:text-white transition">ดูทั้งหมด →</a>
                    </div>

                    <div class="space-y-2">
                        @forelse($unpaidPayments as $p)
                            <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-xs font-bold text-gray-300">
                                        {{ $loop->iteration }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-sm">{{ $p->license_plate }}</p>
                                        <p class="text-xs text-gray-500">{{ $p->total_hours }} ชั่วโมง</p>
                                    </div>
                                </div>
                                <span class="text-red-300 font-extrabold text-sm">฿{{ number_format((float)$p->total_amount, 2) }}</span>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mx-auto text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-gray-500 text-sm">ไม่มีรายการค้างชำระ</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Recent penalties --}}
                <div class="sp-card rounded-2xl p-6 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                            <h2 class="font-extrabold text-base">ค่าปรับล่าสุด</h2>
                        </div>
                    </div>

                    <div class="space-y-2">
                        @forelse($recentPenalties as $x)
                            <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                                <div>
                                    <p class="font-bold text-sm">{{ $x->license_plate }}</p>
                                    <p class="text-xs text-gray-500 truncate max-w-[140px]">{{ $x->reason }}</p>
                                </div>
                                <span class="text-orange-300 font-extrabold text-sm">฿{{ number_format((float)$x->amount, 2) }}</span>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mx-auto text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-gray-500 text-sm">ไม่มีค่าปรับล่าสุด</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Latest scans --}}
                <div class="sp-card rounded-2xl p-6 flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                            <h2 class="font-extrabold text-base">สแกนล่าสุด</h2>
                        </div>
                        <span class="text-xs text-gray-500">License Plates</span>
                    </div>

                    <div class="space-y-2">
                        @forelse($latestScans as $s)
                            <div class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0">
                                <div class="flex-shrink-0 w-2 h-2 rounded-full mt-1 {{ $s->is_suspicious ? 'bg-red-400' : 'bg-green-400' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <p class="font-bold text-sm {{ $s->is_suspicious ? 'text-red-300' : '' }}">{{ $s->license_plate }}</p>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-white/5 text-gray-300">{{ $s->device_type }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate">{{ $s->location }} · {{ $s->scan_time }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-sm text-center py-6">ยังไม่มีข้อมูลการสแกน</p>
                        @endforelse
                    </div>
                </div>

            </div>

            {{-- ══════════════════════════════════════════════════════
                 Lots Overview
            ══════════════════════════════════════════════════════ --}}
            <div class="sp-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="font-extrabold text-lg">ภาพรวมลานจอด</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Parking Lots Overview</p>
                    </div>
                    <a href="{{ route('admin.parking-lots.index') }}" class="text-xs text-gray-400 hover:text-white transition">จัดการ →</a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse($lotsOverview as $lot)
                        @php
                            $lotTotal    = (int)$lot->total_slots ?: 1;
                            $lotOccPct   = round(($lot->occupied / $lotTotal) * 100);
                            $lotAvailPct = round(($lot->available / $lotTotal) * 100);
                        @endphp
                        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4 hover:bg-white/[0.06] transition">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="font-extrabold text-sm">{{ $lot->name }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ number_format((float)$lot->hourly_rate, 2) }} ฿/hr</p>
                                </div>
                                @if($lotOccPct >= 90)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30">เต็ม</span>
                                @elseif($lotOccPct >= 60)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30">ใกล้เต็ม</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30">ว่าง</span>
                                @endif
                            </div>

                            {{-- Slot bar --}}
                            <div class="flex gap-1 h-2 rounded-full overflow-hidden mb-2">
                                <div class="bg-red-500/70 rounded-l-full transition-all" style="width: {{ $lotOccPct }}%"></div>
                                @if($lot->reserved > 0)
                                <div class="bg-yellow-400/70 transition-all" style="width: {{ round(($lot->reserved / $lotTotal) * 100) }}%"></div>
                                @endif
                                <div class="bg-green-500/50 rounded-r-full flex-1"></div>
                            </div>

                            <div class="flex justify-between text-xs mt-2">
                                <span class="text-green-400">ว่าง {{ $lot->available }}</span>
                                @if($lot->reserved > 0)
                                    <span class="text-yellow-400">จอง {{ $lot->reserved }}</span>
                                @endif
                                <span class="text-red-400">ไม่ว่าง {{ $lot->occupied }}</span>
                                <span class="text-gray-500">รวม {{ $lot->total_slots }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">ยังไม่มีข้อมูลลานจอด</p>
                    @endforelse
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
                 Workspace Tabs
            ══════════════════════════════════════════════════════ --}}
            <div x-data="{ tab: 'active' }" class="sp-card rounded-2xl p-6">
                <div class="flex flex-wrap gap-2 mb-5 border-b border-white/10 pb-4">
                    <button @click="tab='active'"
                        :class="tab === 'active' ? 'bg-white/10 text-white border-white/30' : 'text-gray-400 border-white/10 hover:text-white hover:border-white/20'"
                        class="flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-xl border transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
                        Live Parking
                        <span class="ml-1 text-xs bg-yellow-500/20 text-yellow-300 rounded-full px-1.5">{{ count($activeNow) }}</span>
                    </button>
                    <button @click="tab='reserve'"
                        :class="tab === 'reserve' ? 'bg-white/10 text-white border-white/30' : 'text-gray-400 border-white/10 hover:text-white hover:border-white/20'"
                        class="flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-xl border transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                        Reservations
                        <span class="ml-1 text-xs bg-sky-500/20 text-sky-300 rounded-full px-1.5">{{ count($reservations) }}</span>
                    </button>
                    <button @click="tab='history'"
                        :class="tab === 'history' ? 'bg-white/10 text-white border-white/30' : 'text-gray-400 border-white/10 hover:text-white hover:border-white/20'"
                        class="flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-xl border transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Recent History
                    </button>
                    <button @click="tab='slots'"
                        :class="tab === 'slots' ? 'bg-white/10 text-white border-white/30' : 'text-gray-400 border-white/10 hover:text-white hover:border-white/20'"
                        class="flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-xl border transition">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                        Slots Preview
                    </button>
                </div>

                {{-- Active Now --}}
                <div x-show="tab==='active'" x-cloak>
                    @if(count($activeNow) === 0)
                        <div class="text-center py-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto text-gray-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg>
                            <p class="text-gray-500">ยังไม่มีรายการที่กำลังจอด</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                        <th class="pb-3 pr-4 text-left font-medium">ทะเบียน</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ผู้ใช้</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ลาน / ช่อง</th>
                                        <th class="pb-3 text-left font-medium">เวลาเข้า</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($activeNow as $row)
                                        <tr class="hover:bg-white/[0.03] transition">
                                            <td class="py-3 pr-4 font-bold">{{ $row->license_plate }}</td>
                                            <td class="py-3 pr-4 text-gray-300">{{ $row->user_name ?? '—' }}</td>
                                            <td class="py-3 pr-4 text-gray-300">
                                                {{ $row->lot_name }}
                                                @if($row->slot_number) <span class="text-gray-500">· {{ $row->slot_number }}</span> @endif
                                            </td>
                                            <td class="py-3 text-gray-400 text-xs">{{ $row->check_in_time }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Reservations --}}
                <div x-show="tab==='reserve'" x-cloak>
                    @if(count($reservations) === 0)
                        <p class="text-center text-gray-500 py-10">ยังไม่มีการจอง</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                        <th class="pb-3 pr-4 text-left font-medium">ทะเบียน</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ผู้ใช้</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ลาน</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ช่วงเวลา</th>
                                        <th class="pb-3 text-left font-medium">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($reservations as $r)
                                        <tr class="hover:bg-white/[0.03] transition">
                                            <td class="py-3 pr-4 font-bold">{{ $r->license_plate }}</td>
                                            <td class="py-3 pr-4 text-gray-300">{{ $r->user_name ?? '—' }}</td>
                                            <td class="py-3 pr-4 text-gray-300">{{ $r->lot_name }}</td>
                                            <td class="py-3 pr-4 text-gray-400 text-xs">{{ $r->reserve_start }} → {{ $r->reserve_end }}</td>
                                            <td class="py-3">
                                                @if($r->status === 'confirmed')
                                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-500/20 text-green-300 border border-green-500/30">confirmed</span>
                                                @elseif($r->status === 'pending')
                                                    <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300 border border-yellow-500/30">pending</span>
                                                @else
                                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-500/20 text-gray-300 border border-gray-500/30">{{ $r->status }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- History --}}
                <div x-show="tab==='history'" x-cloak>
                    @if(count($recentHistory) === 0)
                        <p class="text-center text-gray-500 py-10">ยังไม่มีประวัติ</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                        <th class="pb-3 pr-4 text-left font-medium">ทะเบียน</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ผู้ใช้</th>
                                        <th class="pb-3 pr-4 text-left font-medium">ลาน</th>
                                        <th class="pb-3 pr-4 text-left font-medium">เข้า</th>
                                        <th class="pb-3 text-left font-medium">ออก</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($recentHistory as $h)
                                        <tr class="hover:bg-white/[0.03] transition">
                                            <td class="py-3 pr-4 font-bold">{{ $h->license_plate }}</td>
                                            <td class="py-3 pr-4 text-gray-300">{{ $h->user_name ?? '—' }}</td>
                                            <td class="py-3 pr-4 text-gray-300">{{ $h->lot_name }}</td>
                                            <td class="py-3 pr-4 text-gray-400 text-xs">{{ $h->check_in_time }}</td>
                                            <td class="py-3 text-xs">
                                                @if($h->check_out_time)
                                                    <span class="text-green-400">{{ $h->check_out_time }}</span>
                                                @else
                                                    <span class="text-yellow-400 animate-pulse">กำลังจอด…</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Slots preview --}}
                <div x-show="tab==='slots'" x-cloak>
                    @if(count($slotsPreview) === 0)
                        <p class="text-center text-gray-500 py-10">ยังไม่มีข้อมูล</p>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach($slotsPreview as $s)
                                @php
                                    $slotCardClass = match($s->status) {
                                        'available' => 'border-green-600/30 bg-green-900/10',
                                        'reserved'  => 'border-yellow-600/30 bg-yellow-900/10',
                                        default     => 'border-red-600/30 bg-red-900/10',
                                    };
                                @endphp
                                <div class="rounded-xl border p-3 text-center {{ $slotCardClass }}">
                                    <p class="font-extrabold text-base">{{ $s->slot_number }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $s->lot_name }}</p>
                                    <div class="mt-2">
                                        @if($s->status === 'available')
                                            <span class="text-xs text-green-400">● ว่าง</span>
                                        @elseif($s->status === 'reserved')
                                            <span class="text-xs text-yellow-400">● จอง</span>
                                        @else
                                            <span class="text-xs text-red-400">● ไม่ว่าง</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
