<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            {{-- Header --}}
            <div>
                <a href="{{ route('admin.suspicious-vehicles.index') }}" class="text-xs text-gray-500 hover:text-gray-300 transition mb-2 inline-flex items-center gap-1">
                    ← กลับ
                </a>
                <h1 class="text-xl font-extrabold tracking-tight sp-glow-text mt-1">เพิ่มทะเบียนบัญชีดำ</h1>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.suspicious-vehicles.store') }}" class="space-y-5">
                    @csrf

                    {{-- License Plate --}}
                    <div>
                        <x-input-label for="license_plate" value="ทะเบียนรถ *" />
                        <x-text-input id="license_plate" name="license_plate" type="text"
                            class="mt-1 block w-full uppercase font-mono tracking-wider"
                            value="{{ old('license_plate') }}"
                            placeholder="กข-1234"
                            required autofocus />
                        <x-input-error :messages="$errors->get('license_plate')" class="mt-1" />
                    </div>

                    {{-- Reason --}}
                    <div>
                        <x-input-label for="reason" value="เหตุผล / บันทึก" />
                        <textarea id="reason" name="reason" rows="3"
                            class="mt-1 block w-full rounded-xl border border-white/10 bg-white/5 text-white placeholder-gray-500 px-4 py-2 text-sm focus:outline-none focus:border-red-500/60 resize-none"
                            placeholder="ระบุเหตุผลที่เพิ่มเข้าบัญชีดำ…">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>

                    {{-- Level --}}
                    <div>
                        <x-input-label for="level" value="ระดับความเสี่ยง *" />
                        <select id="level" name="level"
                            class="sp-select mt-1 block w-full rounded-xl border border-white/10 bg-white/5 text-white px-4 py-2 text-sm focus:outline-none focus:border-red-500/60">
                            <option value="low"    {{ old('level', 'medium') === 'low'    ? 'selected' : '' }}>ต่ำ (Low)</option>
                            <option value="medium" {{ old('level', 'medium') === 'medium' ? 'selected' : '' }}>กลาง (Medium)</option>
                            <option value="high"   {{ old('level', 'medium') === 'high'   ? 'selected' : '' }}>สูง (High)</option>
                        </select>
                        <x-input-error :messages="$errors->get('level')" class="mt-1" />
                    </div>

                    {{-- Is Active --}}
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                            class="w-4 h-4 rounded border-white/20 bg-white/5 text-red-500 focus:ring-red-500/40"
                            {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm text-gray-300 cursor-pointer">เปิดใช้งานทันที (Active)</label>
                    </div>

                    <div class="sp-divider my-1"></div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>เพิ่มทะเบียน</x-primary-button>
                        <a href="{{ route('admin.suspicious-vehicles.index') }}" class="text-sm text-gray-400 hover:text-white transition">ยกเลิก</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
