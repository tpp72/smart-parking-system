<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">การจอง</h1>
                    <p class="text-gray-400 mt-1">การจองทั้งหมดในลานของคุณ</p>
                </div>
            </div>

            @if(session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif
            @if($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-6 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            {{-- Filters --}}
            <div class="sp-card rounded-2xl p-5 mt-2 mb-6">
                <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <input name="q" value="{{ $q }}" placeholder="ค้นหาทะเบียน/ชื่อ..." class="sp-select col-span-2 md:col-span-1" />

                    <select name="lot_id" class="sp-select">
                        <option value="">ทุกลาน</option>
                        @foreach($ownedLots as $lot)
                            <option value="{{ $lot->id }}" @selected((string)$lotId === (string)$lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="sp-select">
                        <option value="">ทุกสถานะ</option>
                        @foreach($statuses as $st)
                            <option value="{{ $st }}" @selected($status === $st)>{{ $st }}</option>
                        @endforeach
                    </select>

                    <div class="flex gap-2">
                        <input type="date" name="from" value="{{ $from }}" class="sp-select flex-1" />
                        <input type="date" name="to" value="{{ $to }}" class="sp-select flex-1" />
                    </div>

                    <div class="flex gap-2 col-span-2 md:col-span-1">
                        <button type="submit" class="sp-btn sp-btn-outline flex-1">ค้นหา</button>
                        <a href="{{ route('owner.reservations.index') }}" class="sp-btn sp-btn-outline flex-1">ล้าง</a>
                    </div>
                </form>
            </div>

            <div class="sp-card rounded-2xl p-6 overflow-x-auto">
                <table class="w-full sp-table">
                    <thead>
                        <tr class="border-b sp-divider text-sm">
                            <th class="py-3 pr-4 text-left">#</th>
                            <th class="py-3 pr-4 text-left">ทะเบียน</th>
                            <th class="py-3 pr-4 text-left">ลูกค้า</th>
                            <th class="py-3 pr-4 text-left">ลาน</th>
                            <th class="py-3 pr-4 text-left">เวลาจอง</th>
                            <th class="py-3 pr-4 text-center">สถานะ</th>
                            <th class="py-3 pr-4 text-center">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reservations as $r)
                            <tr class="border-b sp-divider text-sm">
                                <td class="py-3 pr-4 text-gray-400">{{ $r->id }}</td>
                                <td class="py-3 pr-4 font-bold text-red-300">{{ $r->vehicle?->license_plate ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-200">{{ $r->user?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-gray-300">
                                    {{ $r->parkingLot?->name ?? '-' }}
                                    @if($r->parkingSlot)
                                        <span class="text-gray-500">· {{ $r->parkingSlot->slot_number }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-300">{{ \Carbon\Carbon::parse($r->reserve_start)->format('d/m/Y H:i') }}</td>
                                <td class="py-3 pr-4 text-center">
                                    @php
                                        $bc = match($r->status) {
                                            'confirmed','checked_in','completed' => 'sp-badge-ok',
                                            'pending' => 'sp-badge-warn',
                                            default   => 'sp-badge-danger',
                                        };
                                    @endphp
                                    <span class="sp-badge {{ $bc }}">{{ $r->status }}</span>
                                </td>
                                <td class="py-3 pr-4 text-center">
                                    @if($r->status === 'pending')
                                        <form method="POST" action="{{ route('owner.reservations.confirm', $r) }}">
                                            @csrf
                                            <button type="submit" class="sp-btn sp-btn-primary text-xs px-3 py-1">ยืนยัน</button>
                                        </form>
                                    @else
                                        <span class="text-gray-600 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-400">ไม่พบการจอง</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $reservations->links() }}</div>
            </div>

        </div>
    </div>
</x-app-layout>
