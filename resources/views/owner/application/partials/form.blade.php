{{--
    แบบฟอร์มคำขอเป็นเจ้าของลาน (ใช้ทั้งสมัครใหม่และแก้ไขส่งใหม่) — $application = null สำหรับสมัครใหม่
    ประเภทผู้สมัครเป็นช่องที่ต้องส่งเสมอ (บริษัทต้องกรอกชื่อธุรกิจ)
--}}
@php
    $a = $application ?? null;
    $value = fn (string $field, $default = null) => old($field, $a?->{$field} ?? $default);
@endphp

<div class="flex flex-col gap-6" x-data="{ type: @js($value('applicant_type', 'individual')) }">
    <section aria-labelledby="applicant-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
        <h2 id="applicant-title" class="text-h3 text-fg">ผู้สมัคร</h2>

        <fieldset class="mt-5">
            <legend class="text-label font-semibold text-fg">ประเภทผู้สมัคร <span class="text-danger" aria-hidden="true">*</span></legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach (['individual' => ['บุคคลธรรมดา', 'สมัครในนามตัวเอง'], 'company' => ['บริษัท / นิติบุคคล', 'ต้องกรอกชื่อธุรกิจ']] as $key => [$label, $hint])
                    <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-2 has-[:checked]:border-primary-ink has-[:checked]:bg-primary/5">
                        <input type="radio" name="applicant_type" value="{{ $key }}" x-model="type"
                            class="mt-0.5 h-5 w-5 border-field text-primary focus:ring-primary-ink" @checked($value('applicant_type', 'individual') === $key)>
                        <span>
                            <span class="block font-semibold text-fg">{{ $label }}</span>
                            <span class="block text-caption text-fg-3">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2" x-show="type === 'company'" x-cloak>
                <x-ui.field label="ชื่อธุรกิจ / บริษัท" for="business_name" required>
                    <x-ui.input id="business_name" name="business_name" :value="$value('business_name')" maxlength="255"
                        placeholder="เช่น บริษัท ตัวอย่าง จำกัด" x-bind:required="type === 'company'" />
                </x-ui.field>
            </div>
            <x-ui.field label="ชื่อผู้ติดต่อ" for="contact_name" required>
                <x-ui.input id="contact_name" name="contact_name" :value="$value('contact_name', auth()->user()->name)" required maxlength="255" autocomplete="name" />
            </x-ui.field>
            <x-ui.field label="เบอร์โทรศัพท์" for="phone" required>
                <x-ui.input id="phone" name="phone" type="tel" :value="$value('phone')" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="0812345678" />
            </x-ui.field>
            <x-ui.field label="อีเมลติดต่อ" for="email" required class="sm:col-span-2">
                <x-ui.input id="email" name="email" type="email" :value="$value('email', auth()->user()->email)" required maxlength="255" autocomplete="email" />
            </x-ui.field>
        </div>
    </section>

    <section aria-labelledby="lot-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
        <h2 id="lot-title" class="text-h3 text-fg">ลานจอดที่จะเปิด</h2>
        <p class="mt-1 text-label text-fg-3">หลังอนุมัติ คุณสร้างลานและช่องจอดจริงได้เองในเมนูลานจอด</p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <x-ui.field label="ชื่อลานจอด" for="parking_lot_name" required class="sm:col-span-2">
                <x-ui.input id="parking_lot_name" name="parking_lot_name" :value="$value('parking_lot_name')" required maxlength="255" placeholder="เช่น ลานจอดรถ ตัวอย่าง สาขาสยาม" />
            </x-ui.field>
            <x-ui.field label="ที่อยู่ (เลขที่ / ถนน / อาคาร)" for="address" class="sm:col-span-2">
                <x-ui.input id="address" name="address" :value="$value('address')" maxlength="500" autocomplete="street-address" />
            </x-ui.field>
            <x-ui.field label="แขวง / ตำบล / เขต / อำเภอ" for="district" required>
                <x-ui.input id="district" name="district" :value="$value('district')" required maxlength="100" />
            </x-ui.field>
            <x-ui.field label="จังหวัด" for="province" required>
                <x-ui.select id="province" name="province" required placeholder="เลือกจังหวัด">
                    @foreach (config('thai_provinces') as $province)
                        <option value="{{ $province }}" @selected($value('province') === $province)>{{ $province }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field label="จำนวนช่องจอดโดยประมาณ" for="estimated_slots" required>
                <x-ui.input id="estimated_slots" name="estimated_slots" type="number" :value="$value('estimated_slots')" required min="1" max="10000" inputmode="numeric" numeric />
            </x-ui.field>
            <x-ui.field label="รายละเอียดเพิ่มเติม" for="description" hint="สิ่งอำนวยความสะดวก จุดสังเกต เวลาเปิด-ปิด" class="sm:col-span-2">
                <x-ui.textarea id="description" name="description" rows="3">{{ $value('description') }}</x-ui.textarea>
            </x-ui.field>
        </div>
    </section>

    <section aria-labelledby="doc-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
        <h2 id="doc-title" class="text-h3 text-fg">เอกสารประกอบ <span class="text-label font-normal text-fg-3">(ไม่บังคับ)</span></h2>
        <x-ui.field label="ไฟล์เอกสาร" for="document" class="mt-4"
            hint="เอกสารสิทธิ์ รูปถ่ายลานจอด หรือหนังสือรับรองบริษัท · JPG, PNG หรือ PDF ไม่เกิน 5 MB · เห็นเฉพาะคุณและผู้ดูแลระบบ">
            <input id="document" name="document" type="file" accept=".jpg,.jpeg,.png,.pdf" aria-describedby="document-hint"
                @if ($errors->has('document')) aria-invalid="true" @endif
                class="block w-full text-label text-fg-2 file:mr-3 file:min-h-touch file:cursor-pointer file:rounded-card file:border file:border-solid file:border-field file:bg-surface file:px-4 file:font-semibold file:text-fg hover:file:bg-surface-2">
        </x-ui.field>
        @if ($a?->document_path)
            <p class="mt-3 text-label text-fg-2">
                ไฟล์เดิม: <a href="{{ route('owner-applications.document', $a) }}" target="_blank" rel="noopener" class="font-semibold text-primary-ink underline-offset-4 hover:underline">เปิดดูเอกสาร</a>
                · อัปโหลดไฟล์ใหม่เพื่อแทนที่
            </p>
        @endif
    </section>
</div>
