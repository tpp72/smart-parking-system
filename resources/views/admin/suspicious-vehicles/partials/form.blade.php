{{--
    ฟอร์มบัญชีดำ (เพิ่ม / แก้ไข) — ระบุรถด้วย ทะเบียน + จังหวัด
    ต้องการ: $entry (null = เพิ่มใหม่)
--}}
@php
    $levels = [
        'low' => ['ต่ำ', 'เฝ้าดูไว้ เช่น เคยจอดเกินเวลาบ่อย'],
        'medium' => ['กลาง', 'เคยมีปัญหากับลาน เช่น ค้างชำระ'],
        'high' => ['สูง', 'เกี่ยวข้องกับเหตุร้ายแรง แจ้งเจ้าหน้าที่ทันที'],
    ];
    $level = old('level', $entry?->level ?? 'medium');
    $active = (bool) old('is_active', $entry ? $entry->is_active : true);
@endphp

<form method="POST" action="{{ $entry ? route('admin.suspicious-vehicles.update', $entry) : route('admin.suspicious-vehicles.store') }}" class="mt-6 flex flex-col gap-6">
    @csrf
    @if ($entry)
        @method('PATCH')
    @endif

    <section aria-labelledby="vehicle-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
        <h2 id="vehicle-title" class="text-h3 text-fg">รถ</h2>
        <p class="mt-1 text-label text-fg-2">กล้องเทียบกับทะเบียนและจังหวัดที่ AI อ่านได้ ต้องกรอกให้ตรงกับป้ายจริง</p>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <x-ui.field label="เลขทะเบียน" for="license_plate" required hint="หมวดอักษรและเลข เช่น กข 1234">
                <x-ui.input id="license_plate" name="license_plate" :value="old('license_plate', $entry?->license_plate)" maxlength="20" autocomplete="off" required :autofocus="! $entry" />
            </x-ui.field>
            <x-ui.field label="จังหวัดของป้ายทะเบียน" for="plate_province" required>
                <x-ui.select id="plate_province" name="plate_province" required placeholder="เลือกจังหวัด">
                    @foreach (config('thai_provinces') as $province)
                        <option value="{{ $province }}" @selected(old('plate_province', $entry?->plate_province) === $province)>{{ $province }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </div>
    </section>

    <section aria-labelledby="detail-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
        <h2 id="detail-title" class="text-h3 text-fg">รายละเอียด</h2>
        <div class="mt-5 flex flex-col gap-5">
            <x-ui.field label="เหตุผล / บันทึก" for="reason" hint="เจ้าหน้าที่จะเห็นข้อความนี้ในประวัติ ไม่เกิน 500 ตัวอักษร">
                <x-ui.textarea id="reason" name="reason" rows="3" maxlength="500">{{ old('reason', $entry?->reason) }}</x-ui.textarea>
            </x-ui.field>

            <fieldset>
                <legend class="text-label font-semibold text-fg">ระดับความเสี่ยง <span class="text-danger" aria-hidden="true">*</span></legend>
                <p class="mt-0.5 text-caption text-fg-3">ใช้บันทึกให้เจ้าหน้าที่ประเมิน — กล้องแจ้งเตือนเมื่อพบรถในบัญชีดำที่ใช้งานทุกระดับเหมือนกัน</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    @foreach ($levels as $key => [$label, $hint])
                        <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-2 has-[:checked]:border-primary-ink has-[:checked]:bg-primary/5">
                            <input type="radio" name="level" value="{{ $key }}" required class="mt-0.5 h-5 w-5 border-field text-primary focus:ring-primary-ink" @checked($level === $key)>
                            <span>
                                <span class="block font-semibold text-fg">{{ $label }}</span>
                                <span class="block text-caption text-fg-3">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-ui.error id="level-error" :messages="$errors->get('level')" class="mt-1.5" />
            </fieldset>

            <div>
                <input type="hidden" name="is_active" value="0">
                <x-ui.checkbox name="is_active" value="1" :checked="$active"
                    label="ใช้งาน — แจ้งเตือนเมื่อกล้องพบรถคันนี้"
                    description="ยกเลิกการเลือกเพื่อระงับไว้ชั่วคราวโดยไม่ลบประวัติ" />
            </div>
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <x-ui.button type="submit">{{ $entry ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มเข้าบัญชีดำ' }}</x-ui.button>
        <x-ui.button variant="ghost" :href="route('admin.suspicious-vehicles.index')">ยกเลิก</x-ui.button>
    </div>
</form>
