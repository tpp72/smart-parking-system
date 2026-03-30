<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขรถ</h1>
                <p class="text-gray-300 mt-1">ทะเบียน: <span class="font-bold text-white">{{ $vehicle->license_plate }}</span></p>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.vehicles.update', $vehicle) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    {{-- เจ้าของ --}}
                    <div>
                        <x-input-label for="user_id" value="เจ้าของรถ" />
                        <select id="user_id" name="user_id"
                            class="sp-select mt-1 w-full @error('user_id') border-red-500 @enderror">
                            <option value="">-- เลือกเจ้าของ --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}"
                                    @selected(old('user_id', $vehicle->user_id) == $user->id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                    </div>

                    {{-- ทะเบียน --}}
                    <div>
                        <x-input-label for="license_plate" value="ป้ายทะเบียน" />
                        <x-text-input id="license_plate" name="license_plate" type="text"
                            class="mt-1 block w-full @error('license_plate') border-red-500 @enderror"
                            value="{{ old('license_plate', $vehicle->license_plate) }}" />
                        <x-input-error :messages="$errors->get('license_plate')" class="mt-2" />
                    </div>

                    {{-- ยี่ห้อ --}}
                    <div>
                        <x-input-label for="brand" value="ยี่ห้อ / รุ่น" />
                        <x-text-input id="brand" name="brand" type="text"
                            class="mt-1 block w-full @error('brand') border-red-500 @enderror"
                            value="{{ old('brand', $vehicle->brand) }}" />
                        <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                    </div>

                    {{-- สี --}}
                    <div>
                        <x-input-label for="color" value="สี" />
                        <x-text-input id="color" name="color" type="text"
                            class="mt-1 block w-full @error('color') border-red-500 @enderror"
                            value="{{ old('color', $vehicle->color) }}" />
                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button class="flex-1 justify-center py-3">บันทึกการแก้ไข</x-primary-button>
                        <a href="{{ route('admin.vehicles.index') }}"
                            class="sp-btn sp-btn-outline flex-1 text-center py-3">ยกเลิก</a>
                    </div>
                </form>
            </div>

            {{-- Danger Zone --}}
            <div class="sp-card rounded-2xl p-5 mt-4 border border-red-700/40">
                <p class="text-sm text-gray-400 mb-3">ลบรถนี้ออกจากระบบ (ลบถาวร)</p>
                <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}"
                    onsubmit="return confirm('ยืนยันลบรถ {{ $vehicle->license_plate }}? ไม่สามารถกู้คืนได้')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sp-btn sp-btn-danger w-full justify-center">ลบรถนี้</button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
