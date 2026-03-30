<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">รถของฉัน</h1>
                    <p class="text-gray-400 text-sm mt-0.5">My Vehicles</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('user.vehicles.create') }}" class="sp-btn sp-btn-primary sp-glow-btn">+ เพิ่มรถ</a>
                    <a href="{{ route('user.dashboard') }}" class="sp-btn sp-btn-outline text-sm">← Dashboard</a>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-xl bg-green-900/30 border border-green-700/40 text-green-300 px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($vehicles->isEmpty())
                <div class="sp-card rounded-2xl p-10 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/></svg>
                    <p class="text-gray-400 mb-4">ยังไม่มีรถในระบบ — เพิ่มรถก่อนเพื่อจองที่จอด</p>
                    <a href="{{ route('user.vehicles.create') }}" class="sp-btn sp-btn-primary sp-glow-btn">+ เพิ่มรถคันแรก</a>
                </div>
            @else
                <div class="sp-card rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/10">
                                    <th class="px-5 py-4 text-left font-medium">ทะเบียน</th>
                                    <th class="px-5 py-4 text-left font-medium">ยี่ห้อ</th>
                                    <th class="px-5 py-4 text-left font-medium">สี</th>
                                    <th class="px-5 py-4 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($vehicles as $v)
                                    <tr class="hover:bg-white/[0.03] transition">
                                        <td class="px-5 py-4 font-extrabold tracking-wider">{{ $v->license_plate }}</td>
                                        <td class="px-5 py-4 text-gray-300">{{ $v->brand ?? '—' }}</td>
                                        <td class="px-5 py-4 text-gray-300">{{ $v->color ?? '—' }}</td>
                                        <td class="px-5 py-4 text-right">
                                            <form method="POST" action="{{ route('user.vehicles.destroy', $v->id) }}"
                                                onsubmit="return confirm('ลบรถ {{ $v->license_plate }} ออกจากระบบ?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-xs text-red-400 hover:text-red-300 border border-red-600/30 hover:border-red-400/50 rounded-lg px-3 py-1.5 transition">
                                                    ลบ
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($vehicles->hasPages())
                        <div class="px-5 py-4 border-t border-white/10">
                            {{ $vehicles->links() }}
                        </div>
                    @endif
                </div>

                <div class="mt-4 text-center">
                    <a href="{{ route('user.reservations.create') }}" class="sp-btn sp-btn-primary sp-glow-btn">
                        จองที่จอด →
                    </a>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
