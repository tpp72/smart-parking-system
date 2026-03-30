<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <a href="{{ route('user.vehicles.index') }}" class="text-gray-400 hover:text-white text-sm transition">← รถของฉัน</a>
                <h1 class="text-2xl font-extrabold sp-glow-text mt-2">เพิ่มรถ</h1>
                <p class="text-gray-400 text-sm mt-0.5">Add My Vehicle</p>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mb-5 border border-red-600/40">
                    <ul class="text-red-300 text-sm space-y-1">
                        @foreach ($errors->all() as $e)
                            <li>• {{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('user.vehicles.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="license_plate" value="ป้ายทะเบียน *" />
                        <x-text-input id="license_plate" name="license_plate" type="text"
                            class="mt-1 block w-full @error('license_plate') border-red-500 @enderror"
                            value="{{ old('license_plate') }}"
                            placeholder="เช่น กข 1234 หรือ ABC-1234"
                            autofocus />
                        <p class="text-xs text-gray-500 mt-1">ตัวอย่าง: กข 1234 / 5กท 999 / ABC-1234</p>
                        <x-input-error :messages="$errors->get('license_plate')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="brand" value="ยี่ห้อรถ" />
                        <x-text-input id="brand" name="brand" type="text"
                            class="mt-1 block w-full @error('brand') border-red-500 @enderror"
                            value="{{ old('brand') }}"
                            placeholder="เช่น Toyota, Honda, Isuzu" />
                        <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="color" value="สีรถ" />
                        <x-text-input id="color" name="color" type="text"
                            class="mt-1 block w-full @error('color') border-red-500 @enderror"
                            value="{{ old('color') }}"
                            placeholder="เช่น ขาว, ดำ, เงิน" />
                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="sp-btn sp-btn-primary sp-glow-btn flex-1 justify-center py-3">
                            บันทึกรถ
                        </button>
                        <a href="{{ route('user.vehicles.index') }}"
                            class="sp-btn sp-btn-outline flex-1 text-center py-3">ยกเลิก</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
