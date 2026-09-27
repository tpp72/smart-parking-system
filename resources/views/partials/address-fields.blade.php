{{--
    ที่อยู่ตามเขตการปกครองไทย — เลือกต่อกันเป็นชั้น จังหวัด → อำเภอ/เขต → ตำบล/แขวง แล้วเติมรหัสไปรษณีย์ให้เอง
    รหัสไปรษณีย์ผูกกับตำบล/แขวง จึงขึ้นต่อเมื่อเลือกครบทั้ง 3 ชั้น · เซิร์ฟเวอร์คำนวณรหัสใหม่เองเสมอ ไม่เชื่อค่าจากเบราว์เซอร์

    ต้องการ: $value (closure อ่านค่าเดิม) · $required (บังคับกรอกหรือไม่)
    ตอนปิด JavaScript ช่องอำเภอ/ตำบลจะว่าง — ฟอร์มนี้ใช้กับผู้ใช้ที่ล็อกอินแล้วและเปิด JS เสมอ
--}}
@php
    $required = $required ?? true;
    $selected = [
        'province' => (string) $value('province'),
        'district' => (string) $value('district'),
        'subdistrict' => (string) $value('subdistrict'),
        'postalCode' => (string) $value('postal_code'),
    ];
@endphp

<div class="contents" x-data="spAddressSelect(@js($selected))" data-geo-url="{{ asset(\App\Support\ThaiGeography::PATH) }}">
    <x-ui.field label="จังหวัด" for="province" :required="$required">
        <x-ui.select id="province" name="province" :required="$required" placeholder="เลือกจังหวัด"
            x-model="province" x-on:change="onProvinceChange()">
            @foreach (\App\Support\ThaiGeography::provinces() as $p)
                <option value="{{ $p }}" @selected($selected['province'] === $p)>{{ $p }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <x-ui.field label="อำเภอ / เขต" for="district" :required="$required">
        <x-ui.select id="district" name="district" :required="$required"
            x-model="district" x-on:change="onDistrictChange()"
            x-bind:disabled="! province || loading"
            x-bind:placeholder="province ? 'เลือกอำเภอ / เขต' : 'เลือกจังหวัดก่อน'"
            placeholder="เลือกจังหวัดก่อน">
            <template x-for="d in districts" :key="d">
                <option :value="d" x-text="d"></option>
            </template>
        </x-ui.select>
    </x-ui.field>

    <x-ui.field label="ตำบล / แขวง" for="subdistrict" :required="$required">
        <x-ui.select id="subdistrict" name="subdistrict" :required="$required"
            x-model="subdistrict" x-on:change="syncPostalCode()"
            x-bind:disabled="! district || loading"
            placeholder="เลือกอำเภอ / เขตก่อน">
            <template x-for="s in subdistricts" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </x-ui.select>
    </x-ui.field>

    <x-ui.field label="รหัสไปรษณีย์" for="postal_code" hint="ระบบเติมให้จากตำบล / แขวงที่เลือก">
        <x-ui.input id="postal_code" name="postal_code" readonly x-model="postalCode"
            placeholder="—" maxlength="5" inputmode="numeric" numeric />
    </x-ui.field>
</div>
