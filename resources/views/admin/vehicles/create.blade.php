<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มรถใหม่</h1>
                <p class="text-gray-300 mt-1">กรอกข้อมูลรถและเลือกเจ้าของ</p>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.vehicles.store') }}" class="space-y-5">
                    @csrf

                    {{-- เจ้าของ --}}
                    <div>
                        <x-input-label for="user_id" value="เจ้าของรถ" />
                        <select id="user_id" name="user_id"
                            class="sp-select mt-1 w-full @error('user_id') border-red-500 @enderror">
                            <option value="">-- เลือกเจ้าของ --</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
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
                            value="{{ old('license_plate') }}"
                            placeholder="เช่น กข 1234 กรุงเทพมหานคร" />
                        <x-input-error :messages="$errors->get('license_plate')" class="mt-2" />
                    </div>

                    {{-- ยี่ห้อ --}}
                    <div>
                        <x-input-label for="brand" value="ยี่ห้อ / รุ่น" />
                        <x-text-input id="brand" name="brand" type="text"
                            class="mt-1 block w-full @error('brand') border-red-500 @enderror"
                            value="{{ old('brand') }}"
                            placeholder="เช่น Toyota Yaris" />
                        <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                    </div>

                    {{-- สี --}}
                    <div>
                        <x-input-label for="color" value="สี" />
                        <x-text-input id="color" name="color" type="text"
                            class="mt-1 block w-full @error('color') border-red-500 @enderror"
                            value="{{ old('color') }}"
                            placeholder="เช่น ขาว" />
                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button class="flex-1 justify-center py-3">บันทึก</x-primary-button>
                        <a href="{{ route('admin.vehicles.index') }}"
                            class="sp-btn sp-btn-outline flex-1 text-center py-3">ยกเลิก</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
