<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-extrabold sp-glow-text">แก้ไขป้ายทะเบียน</h1>
                    <p class="text-gray-400 text-sm mt-0.5">การจอง #{{ $reservation->id }} — {{ $reservation->parkingLot?->name }}</p>
                </div>
                <a href="{{ route('user.reservations.index') }}" class="sp-btn sp-btn-outline text-sm">← กลับ</a>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-5 border border-red-600/40">
                    <ul class="text-red-300 text-sm space-y-1">
                        @foreach ($errors->all() as $e) <li>• {{ $e }}</li> @endforeach
                    </ul>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6">

                {{-- ข้อมูลการจอง --}}
                <div class="grid grid-cols-2 gap-3 mb-6 text-sm">
                    <div>
                        <p class="text-gray-500">ลานจอด</p>
                        <p class="font-semibold">{{ $reservation->parkingLot?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">ช่องจอด</p>
                        <p class="font-semibold">{{ $reservation->parkingSlot?->slot_number ?? 'ระบบจัดให้' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">เวลาเริ่ม</p>
                        <p class="font-semibold">{{ \Carbon\Carbon::parse($reservation->reserve_start)->format('d/m/Y H:i') }} น.</p>
                    </div>
                    <div>
                        <p class="text-gray-500">สถานะ</p>
                        <span class="sp-badge {{ $reservation->status === 'confirmed' ? 'sp-badge-ok' : 'sp-badge-warn' }}">
                            {{ $reservation->status }}
                        </span>
                    </div>
                </div>

                <div class="border-t border-white/10 pt-5">
                    <form method="POST" action="{{ route('user.reservations.update-plate', $reservation) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="license_plate" value="ป้ายทะเบียนรถ" />
                            <x-text-input id="license_plate" name="license_plate" type="text"
                                class="mt-1 block w-full uppercase tracking-widest @error('license_plate') border-red-500 @enderror"
                                value="{{ old('license_plate', $reservation->license_plate) }}"
                                placeholder="เช่น กข 1234"
                                maxlength="20"
                                autocomplete="off" />
                            <x-input-error :messages="$errors->get('license_plate')" class="mt-2" />
                        </div>

                        <div class="flex gap-3 pt-1">
                            <button type="submit" class="sp-btn sp-btn-primary flex-1 justify-center">
                                บันทึก
                            </button>
                            <a href="{{ route('user.reservations.index') }}"
                                class="sp-btn sp-btn-outline flex-1 text-center">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
