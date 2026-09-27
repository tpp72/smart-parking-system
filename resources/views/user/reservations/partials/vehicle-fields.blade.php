{{--
    ข้อมูลรถ 4 ช่องที่ผู้ใช้กรอกเอง (และแก้ได้ก่อน Check-in): ทะเบียน · จังหวัด · ยี่ห้อ · สี
    AI จับคู่ด้วย ทะเบียน + จังหวัด และยี่ห้อหรือสีอย่างน้อย 1 อย่าง จึงต้องกรอกให้ตรงกับรถจริง
    ต้องการ: $plateNumber, $plateProvince, $brand, $color (ค่าเดิม — null สำหรับฟอร์มใหม่)
--}}
@php
    // ข้อผิดพลาดทะเบียนซ้ำ/กำลังจอดอยู่ มาในชื่อ license_plate — แสดงใต้ช่องทะเบียน
    $plateErrors = array_merge($errors->get('plate_number'), $errors->get('license_plate'));
@endphp

<fieldset class="flex flex-col gap-5">
    <legend class="sr-only">ข้อมูลรถ</legend>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui.field label="เลขทะเบียน" for="plate_number" required :error="$plateErrors">
            <x-ui.input id="plate_number" name="plate_number" :value="old('plate_number', $plateNumber)"
                maxlength="20" autocomplete="off" required data-plate-input placeholder="กข 1234" :invalid="$plateErrors !== []" />
        </x-ui.field>

        <x-ui.field label="จังหวัดของป้ายทะเบียน" for="plate_province" required>
            <x-ui.select id="plate_province" name="plate_province" required placeholder="เลือกจังหวัด">
                @foreach (config('thai_provinces') as $province)
                    <option value="{{ $province }}" @selected(old('plate_province', $plateProvince) === $province)>{{ $province }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field label="ยี่ห้อรถ" for="brand" required>
            <x-ui.select id="brand" name="brand" required placeholder="เลือกยี่ห้อ">
                @foreach (config('car_brands') as $b)
                    <option value="{{ $b }}" @selected(old('brand', $brand) === $b)>{{ $b }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>

        <x-ui.field label="สีรถ" for="color" required>
            <x-ui.select id="color" name="color" required placeholder="เลือกสี">
                @foreach (config('car_colors') as $c)
                    <option value="{{ $c }}" @selected(old('color', $color) === $c)>{{ $c }}</option>
                @endforeach
            </x-ui.select>
        </x-ui.field>
    </div>

    <p class="text-label text-fg-3">
        ระบบ Check-in ให้อัตโนมัติเมื่อ AI อ่านทะเบียนและจังหวัดตรง พร้อมยี่ห้อหรือสีตรงอย่างน้อย 1 อย่าง
    </p>
</fieldset>
