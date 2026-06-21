<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            {{-- Header --}}
            <div>
                <a href="{{ route('admin.suspicious-vehicles.index') }}" class="text-xs text-gray-500 hover:text-gray-300 transition mb-2 inline-flex items-center gap-1">
                    ← กลับ
                </a>
                <h1 class="text-xl font-extrabold tracking-tight sp-glow-text mt-1">แก้ไขบัญชีดำ</h1>
                <p class="text-gray-400 text-sm mt-0.5 font-mono">{{ $suspiciousVehicle->license_plate }}</p>
            </div>

            <div class="sp-card rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.suspicious-vehicles.update', $suspiciousVehicle) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    {{-- License Plate --}}
                    <div>
                        <x-input-label for="license_plate" value="ทะเบียนรถ *" />
                        <x-text-input id="license_plate" name="license_plate" type="text"
                            class="mt-1 block w-full uppercase font-mono tracking-wider"
                            value="{{ old('license_plate', $suspiciousVehicle->license_plate) }}"
                            required autofocus />
                        <x-input-error :messages="$errors->get('license_plate')" class="mt-1" />
                    </div>

                    {{-- Reason --}}
                    <div>
                        <x-input-label for="reason" value="เหตุผล / บันทึก" />
                        <textarea id="reason" name="reason" rows="3"
                            class="mt-1 block w-full rounded-xl border border-white/10 bg-white/5 text-white placeholder-gray-500 px-4 py-2 text-sm focus:outline-none focus:border-red-500/60 resize-none">{{ old('reason', $suspiciousVehicle->reason) }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>

                    {{-- Level --}}
                    <div>
                        <x-input-label for="level" value="ระดับความเสี่ยง *" />
                        <select id="level" name="level"
                            class="sp-select mt-1 block w-full rounded-xl border border-white/10 bg-white/5 text-white px-4 py-2 text-sm focus:outline-none focus:border-red-500/60">
                            <option value="low"    {{ old('level', $suspiciousVehicle->level) === 'low'    ? 'selected' : '' }}>ต่ำ (Low)</option>
                            <option value="medium" {{ old('level', $suspiciousVehicle->level) === 'medium' ? 'selected' : '' }}>กลาง (Medium)</option>
                            <option value="high"   {{ old('level', $suspiciousVehicle->level) === 'high'   ? 'selected' : '' }}>สูง (High)</option>
                        </select>
                        <x-input-error :messages="$errors->get('level')" class="mt-1" />
                    </div>

                    {{-- Is Active --}}
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                            class="w-4 h-4 rounded border-white/20 bg-white/5 text-red-500 focus:ring-red-500/40"
                            {{ old('is_active', $suspiciousVehicle->is_active ? '1' : '0') ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm text-gray-300 cursor-pointer">เปิดใช้งาน (Active)</label>
                    </div>

                    <div class="sp-divider my-1"></div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>บันทึกการเปลี่ยนแปลง</x-primary-button>
                        <a href="{{ route('admin.suspicious-vehicles.index') }}" class="text-sm text-gray-400 hover:text-white transition">ยกเลิก</a>
                    </div>
                </form>
            </div>

            {{-- Danger Zone --}}
            <div class="sp-card rounded-2xl p-6 border border-red-900/40">
                <h3 class="font-bold text-red-400 mb-3 text-sm">Danger Zone</h3>
                <p class="text-xs text-gray-500 mb-4">การลบจะเอาทะเบียนนี้ออกจากบัญชีดำถาวร ไม่สามารถกู้คืนได้</p>
                <form method="POST" action="{{ route('admin.suspicious-vehicles.destroy', $suspiciousVehicle) }}"
                    onsubmit="return confirm('ยืนยันลบทะเบียน {{ $suspiciousVehicle->license_plate }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sp-btn sp-btn-danger text-sm px-4">
                        ลบทะเบียนนี้ถาวร
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
