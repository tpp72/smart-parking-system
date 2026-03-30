<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">รถเข้า (Manual Check-In)</h1>
                <p class="text-gray-300 mt-1">เลือกรถและลานจอด ระบบจะจัดช่องจอดให้อัตโนมัติ</p>
            </div>

            {{-- Flash success --}}
            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mb-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Form --}}
            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.check-in.store') }}" class="space-y-6">
                    @csrf

                    {{-- Vehicle --}}
                    <div>
                        <x-input-label for="vehicle_id" value="เลือกรถ (ทะเบียน)" />
                        <select id="vehicle_id" name="vehicle_id"
                            class="sp-select mt-1 w-full @error('vehicle_id') border-red-500 @enderror">
                            <option value="">-- เลือกรถ --</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                    @selected(old('vehicle_id') == $vehicle->id)>
                                    {{ $vehicle->license_plate }}
                                    ({{ $vehicle->brand }}, {{ $vehicle->color }})
                                    @if ($vehicle->user)
                                        — {{ $vehicle->user->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('vehicle_id')" class="mt-2" />
                    </div>

                    {{-- Parking Lot --}}
                    <div>
                        <x-input-label for="parking_lot_id" value="เลือกลานจอด" />
                        <select id="parking_lot_id" name="parking_lot_id"
                            class="sp-select mt-1 w-full @error('parking_lot_id') border-red-500 @enderror">
                            <option value="">-- เลือกลาน --</option>
                            @foreach ($lots as $lot)
                                <option value="{{ $lot->id }}"
                                    @selected(old('parking_lot_id') == $lot->id)>
                                    {{ $lot->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('parking_lot_id')" class="mt-2" />
                        <p class="text-gray-400 text-sm mt-1">ระบบจะจัดช่องจอดที่ว่างให้อัตโนมัติ</p>
                    </div>

                    <div class="pt-2">
                        <x-primary-button class="w-full justify-center py-3 text-base">
                            ยืนยัน Check-In
                        </x-primary-button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
