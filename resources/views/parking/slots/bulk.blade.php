{{--
    เพิ่มช่องจอดหลายช่องพร้อมกัน (ใช้ร่วม Owner และ Admin)
    แบบช่วง: อักษรนำหน้า + เลขเริ่ม–สิ้นสุด + จำนวนหลัก (เช่น A + 1–40 + 3 หลัก → A001…A040) · แบบรายการ: พิมพ์เลขช่องเอง
    แสดงตัวอย่างเลขช่องก่อนบันทึก (คำนวณแบบเดียวกับ bulkStore) · เตือนและห้ามบันทึกถ้าเลขซ้ำกับช่องที่มีอยู่แล้วในลาน
    ต้องการ: $lots, $existing (lot_id => [slot_number]), $scope, $selectedLotId
--}}
@php
    $lotValue = old('parking_lot_id', $selectedLotId ?: $lots->first()?->id);
@endphp

<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route("{$scope}.parking-slots.index", array_filter(['lot_id' => $selectedLotId ?: null])) }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> ช่องจอด
        </a>
        <h1 class="mt-2 text-h1 text-fg">เพิ่มหลายช่อง</h1>
        <p class="mt-1 text-fg-2">สร้างช่องจอดหลายช่องในลานเดียวพร้อมกัน ทุกช่องเริ่มด้วยสถานะ "ว่าง"</p>

        @if ($errors->any())
            <x-ui.alert tone="danger" class="mt-6">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route("{$scope}.parking-slots.bulk.store") }}" class="mt-6 flex flex-col gap-6"
            x-data="{
                lotId: @js((string) $lotValue),
                existing: @js($existing),
                mode: @js(old('mode', 'range')),
                prefix: @js(old('prefix', 'A')),
                start: @js((int) old('start', 1)),
                end: @js((int) old('end', 20)),
                pad: @js((int) old('pad', 3)),
                list: @js(old('slot_numbers', '')),
                get numbers() {
                    if (this.mode === 'list') {
                        return [...new Set(this.list.split(/\r\n|\n|\r|,/).map((x) => x.trim()).filter(Boolean))];
                    }
                    const s = Number(this.start), e = Number(this.end), p = Number(this.pad) || 0;
                    if (!Number.isInteger(s) || !Number.isInteger(e) || e < s) return [];
                    const out = [];
                    for (let i = s; i <= e && out.length <= 5000; i++) out.push(this.prefix + String(i).padStart(p, '0'));
                    return out;
                },
                get duplicates() {
                    const taken = new Set(this.existing[this.lotId] ?? []);
                    return this.numbers.filter((n) => taken.has(n));
                },
            }">
            @csrf

            <section aria-labelledby="bulk-lot" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="bulk-lot" class="text-h3 text-fg">ลานและวิธีสร้าง</h2>
                <div class="mt-5 flex flex-col gap-5">
                    <x-ui.field label="ลานจอด" for="parking_lot_id" required>
                        <x-ui.select id="parking_lot_id" name="parking_lot_id" required x-model="lotId">
                            @foreach ($lots as $lot)
                                <option value="{{ $lot->id }}" @selected((string) $lotValue === (string) $lot->id)>{{ $lot->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <fieldset>
                        <legend class="text-label font-semibold text-fg">วิธีสร้าง</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach (['range' => ['เป็นช่วงเลข', 'เช่น A001 ถึง A040'], 'list' => ['พิมพ์เลขช่องเอง', 'เลขไม่ต่อเนื่อง เช่น VIP1, VIP2']] as $key => [$label, $hint])
                                <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-2 has-[:checked]:border-primary-ink has-[:checked]:bg-primary/5">
                                    <input type="radio" name="mode" value="{{ $key }}" x-model="mode" class="mt-1 h-4 w-4 border-field text-primary focus:ring-primary-ink" @checked(old('mode', 'range') === $key)>
                                    <span>
                                        <span class="block font-semibold text-fg">{{ $label }}</span>
                                        <span class="block text-caption text-fg-3">{{ $hint }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </section>

            <section x-show="mode === 'range'" aria-labelledby="bulk-range" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="bulk-range" class="text-h3 text-fg">ช่วงเลขช่อง</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-4">
                    <x-ui.field label="อักษรนำหน้า" for="prefix" hint="เว้นว่างได้">
                        <x-ui.input id="prefix" name="prefix" x-model="prefix" :value="old('prefix', 'A')" maxlength="50" numeric />
                    </x-ui.field>
                    <x-ui.field label="เลขเริ่ม" for="start" required>
                        <x-ui.input id="start" name="start" type="number" min="0" x-model.number="start" :value="old('start', 1)" x-bind:required="mode === 'range'" numeric />
                    </x-ui.field>
                    <x-ui.field label="ถึงเลข" for="end" required>
                        <x-ui.input id="end" name="end" type="number" min="0" x-model.number="end" :value="old('end', 20)" x-bind:required="mode === 'range'" numeric />
                    </x-ui.field>
                    <x-ui.field label="จำนวนหลัก" for="pad" hint="3 → 001">
                        <x-ui.input id="pad" name="pad" type="number" min="0" max="8" x-model.number="pad" :value="old('pad', 3)" numeric />
                    </x-ui.field>
                </div>
            </section>

            <section x-show="mode === 'list'" x-cloak aria-labelledby="bulk-list" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="bulk-list" class="text-h3 text-fg">รายการเลขช่อง</h2>
                <x-ui.field label="เลขช่อง" for="slot_numbers" hint="คั่นด้วยการขึ้นบรรทัดใหม่หรือเครื่องหมายจุลภาค · เลขซ้ำจะถูกรวมเป็นช่องเดียว" class="mt-4">
                    <x-ui.textarea id="slot_numbers" name="slot_numbers" rows="6" x-model="list" x-bind:required="mode === 'list'">{{ old('slot_numbers') }}</x-ui.textarea>
                </x-ui.field>
            </section>

            {{-- ตัวอย่างก่อนบันทึก --}}
            <section aria-labelledby="bulk-preview" aria-live="polite" class="rounded-card border border-dashed border-field bg-surface-2/60 p-5 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 id="bulk-preview" class="text-h3 text-fg">จะสร้าง <span class="num" x-text="numbers.length"></span> ช่อง</h2>
                    <p class="text-caption text-fg-3">ลานนี้มีอยู่แล้ว <span class="num" x-text="(existing[lotId] ?? []).length"></span> ช่อง</p>
                    <p class="text-caption text-fg-3" x-show="numbers.length > 40" x-text="'แสดง 40 ช่องแรก'"></p>
                </div>
                <div x-show="duplicates.length > 0" x-cloak role="alert" class="mt-3 rounded-control border border-danger/60 bg-surface px-4 py-3 text-label text-fg">
                    <p class="font-semibold">
                        มีเลขช่องซ้ำกับช่องที่มีอยู่แล้วในลานนี้ <span class="num" x-text="duplicates.length"></span> ช่อง — บันทึกไม่ได้
                    </p>
                    <p class="mt-1 text-fg-2">
                        <span class="num" x-text="duplicates.slice(0, 15).join(', ')"></span><span x-show="duplicates.length > 15"> และอื่น ๆ</span>
                        · เปลี่ยนอักษรนำหน้าหรือช่วงเลข หรือลบเลขเหล่านี้ออกจากรายการ
                    </p>
                </div>
                <p class="mt-2 text-label text-fg-3" x-show="numbers.length === 0">ยังไม่มีเลขช่อง — ตรวจช่วงเลข (ถึงเลขต้องไม่น้อยกว่าเลขเริ่ม) หรือรายการที่พิมพ์</p>
                <ul class="mt-3 flex flex-wrap gap-1.5" x-show="numbers.length > 0">
                    <template x-for="n in numbers.slice(0, 40)" :key="n">
                        <li class="num rounded-control border px-2 py-1 text-caption"
                            x-bind:class="duplicates.includes(n) ? 'border-danger bg-danger/10 text-danger line-through' : 'border-line bg-surface text-fg'"
                            x-bind:title="duplicates.includes(n) ? 'มีช่องนี้อยู่แล้ว' : null">
                            <span x-text="n"></span><span class="sr-only" x-show="duplicates.includes(n)"> (ซ้ำ มีอยู่แล้ว)</span>
                        </li>
                    </template>
                </ul>
            </section>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" x-bind:disabled="numbers.length === 0 || duplicates.length > 0">สร้างช่องจอด</x-ui.button>
                <x-ui.button variant="ghost" :href="route($scope.'.parking-slots.index', array_filter(['lot_id' => $selectedLotId ?: null]))">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
