<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">รถเข้า (Manual Check-In)</h1>
                <p class="text-gray-300 mt-1">เลือกการจองที่พร้อมเช็คอินในลานของคุณ ระบบจะจัดช่องจอดให้อัตโนมัติ</p>
            </div>

            {{-- Flash success --}}
            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Error --}}
            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-6 border border-red-600/40">
                    <p class="text-red-300 font-semibold">{{ $errors->first() }}</p>
                </div>
            @endif

            @if ($lots->isEmpty())
                <div class="sp-card rounded-2xl p-6 text-center text-gray-300">
                    คุณยังไม่มีลานจอด — <a href="{{ route('owner.parking-lots.create') }}" class="text-red-300 underline">เพิ่มลานจอด</a> ก่อน
                </div>
            @else
                <div class="sp-card rounded-2xl p-6">
                    @if ($reservations->isEmpty())
                        <p class="text-gray-400 text-center py-8">ไม่มีการจองที่พร้อมเช็คอินในลานของคุณขณะนี้</p>
                    @else
                        <form method="POST" action="{{ route('owner.check-in.store') }}" class="space-y-6">
                            @csrf

                            <div>
                                <x-input-label for="reservation_id" value="เลือกการจอง" />
                                <select id="reservation_id" name="reservation_id"
                                    class="sp-select mt-1 w-full @error('reservation_id') border-red-500 @enderror">
                                    <option value="">-- เลือกการจอง --</option>
                                    @foreach ($reservations as $r)
                                        <option value="{{ $r->id }}" @selected(old('reservation_id') == $r->id)>
                                            {{ $r->license_plate }}
                                            ({{ $r->brand }}, {{ $r->color }})
                                            — {{ $r->user?->name }}
                                            — {{ $r->parkingLot?->name }}
                                            @if ($r->parkingSlot)
                                                / {{ $r->parkingSlot->slot_number }}
                                            @endif
                                            — {{ \Carbon\Carbon::parse($r->reserve_start)->format('d/m/Y H:i') }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('reservation_id')" class="mt-2" />
                            </div>

                            <div class="pt-2">
                                <x-primary-button class="w-full justify-center py-3 text-base">
                                    ยืนยัน Check-In
                                </x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
