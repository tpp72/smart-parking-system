{{--
    AI สแกน — จำลองกล้องหน้าลาน: เลือกลาน (ตำแหน่งกล้อง) + อัปโหลดภาพรถ
    ระบบตรวจทิศทางเอง: รถจอดอยู่ในลานนี้ → Check-out · ไม่ได้จอด → จับคู่การจอง / Walk-in (ใช้ร่วมกันทุกบทบาท)
    ยังไม่มีผล = การ์ดกล้องอยู่กลางหน้า · มีผลแล้ว = กล้องซ้าย ผลขวา
--}}
@php
    $role = auth()->user()->role;
    $scanStoreRoute = route("{$role}.scan.store");
    $scanHistoryRoute = in_array($role, ['admin', 'owner'], true) ? route("{$role}.scan.history") : null;
    $threshold = config('carscan.accuracy_threshold', 85);
    $hasResult = session()->has('scan_result') || session()->has('scan_lot_full');
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">AI สแกน</h1>
                <p class="mt-1 text-fg-2">จำลองกล้องหน้าลาน — อัปโหลดภาพรถ ระบบอ่านป้ายทะเบียนแล้ว Check-in หรือ Check-out ให้เอง</p>
            </div>
            @if ($scanHistoryRoute)
                <x-ui.button variant="secondary" :href="$scanHistoryRoute">
                    <x-ui.icon name="scan-history" class="h-4 w-4" /> ประวัติสแกน
                </x-ui.button>
            @endif
        </div>

        <div @class([
            'grid items-start gap-6',
            'lg:grid-cols-[22rem_1fr]' => $hasResult,
            'mx-auto max-w-md' => ! $hasResult,
        ])>
            {{-- ── กล้อง (ฟอร์ม) ────────────────────────────────────── --}}
            <section aria-labelledby="camera-title" @class(['order-2 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6', 'lg:order-1' => $hasResult])>
                <div class="flex items-center justify-between gap-2">
                    <h2 id="camera-title" class="text-h3 text-fg">{{ $hasResult ? 'สแกนคันถัดไป' : 'กล้องหน้าลาน' }}</h2>

                    {{-- กติกาที่ประตูลาน — ปุ่ม (i) เปิดแผ่นลอย ใช้ <details> จึงกดดูได้แม้ปิด JavaScript (Alpine เพิ่มปิดเมื่อคลิกนอกกรอบ/Escape) --}}
                    <details class="group relative -me-1.5" x-data
                        x-on:click.outside="$el.open = false"
                        x-on:keydown.escape="$el.open = false">
                        <summary
                            class="flex min-h-touch min-w-touch cursor-pointer list-none items-center justify-center rounded-control text-fg-3 transition-colors duration-fast hover:text-fg group-open:text-primary-ink [&::-webkit-details-marker]:hidden">
                            <x-ui.icon name="info" class="h-5 w-5" />
                            <span class="sr-only">ดูกติกาที่ประตูลาน</span>
                        </summary>

                        <div
                            class="absolute end-0 z-dropdown mt-1 w-[min(20rem,calc(100vw-3rem))] rounded-card border border-line bg-surface p-4 text-start shadow-overlay">
                            <h3 class="text-label text-fg">ระบบตัดสินใจที่ประตูลานอย่างไร</h3>
                            <ol class="mt-3 flex flex-col gap-3">
                                @foreach ([
                                    ['AI อ่านภาพ', "อ่านป้ายทะเบียน จังหวัด ยี่ห้อ สี และความแม่นยำ — ต้องแม่นยำเกิน {$threshold}% ถ้าไม่ผ่านจะบันทึกผลและแจ้งเจ้าหน้าที่ แต่ไม่ Check-in / Check-out ให้"],
                                    ['ตรวจทิศทาง', 'ถ้ารถคันนี้กำลังจอดอยู่ในลานที่เลือก ระบบ Check-out และคิดค่าจอดทันที'],
                                    ['จับคู่การจอง', 'ทะเบียนและจังหวัดตรง พร้อมยี่ห้อหรือสีตรง และอยู่ภายใน 60 นาทีหลังเวลาเริ่มจอง → Check-in ด้วยการจองนั้น'],
                                    ['Walk-in', 'ไม่มีการจองที่ใช้ได้ → เข้าแบบ Walk-in ถ้าลานเต็มจะแจ้ง "ลานเต็ม" และไม่บันทึกผล · รถในบัญชีดำแจ้งเตือนแต่ยังให้เข้า'],
                                ] as [$title, $text])
                                    <li class="grid grid-cols-[1.5rem_1fr] gap-2.5">
                                        <span class="num flex h-6 w-6 items-center justify-center rounded-control border border-line text-mini text-fg-2">{{ $loop->iteration }}</span>
                                        <div>
                                            <p class="text-label text-fg">{{ $title }}</p>
                                            <p class="mt-0.5 text-caption text-fg-2">{{ $text }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </details>
                </div>

                @if ($lots->isEmpty())
                    <p class="mt-3 text-fg-2">ยังไม่มีลานจอดในระบบ</p>
                @else
                    <form method="POST" action="{{ $scanStoreRoute }}" enctype="multipart/form-data" class="mt-5 flex flex-col gap-5"
                        x-data="{
                            preview: null, fileName: '',
                            handleFile(event) {
                                const file = event.target.files[0];
                                if (!file) { this.preview = null; this.fileName = ''; return; }
                                this.fileName = file.name;
                                const reader = new FileReader();
                                reader.onload = (e) => this.preview = e.target.result;
                                reader.readAsDataURL(file);
                            },
                        }">
                        @csrf

                        <x-ui.field label="ลานจอด (ตำแหน่งกล้อง)" for="parking_lot_id" required hint="ระบบจริงกล้องติดอยู่ที่ลานนี้และส่งภาพมาเอง">
                            <x-ui.select id="parking_lot_id" name="parking_lot_id" required placeholder="เลือกลานจอด">
                                @foreach ($lots as $lot)
                                    <option value="{{ $lot->id }}" @selected(old('parking_lot_id') == $lot->id)>{{ $lot->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="ภาพรถ" for="car_image" required hint="JPG หรือ PNG ไม่เกิน 5 MB · เห็นป้ายทะเบียนชัดเจน">
                            <label for="car_image" @class([
                                'relative flex min-h-44 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-card border-2 border-dashed bg-surface-2/60 text-center transition-colors duration-fast hover:border-fg-2',
                                'border-danger' => $errors->has('car_image'),
                                'border-field' => ! $errors->has('car_image'),
                            ])>
                                <img x-show="preview" x-cloak x-bind:src="preview" alt="ภาพรถที่เลือก" class="absolute inset-0 h-full w-full object-contain p-1">
                                <span x-show="!preview" class="flex flex-col items-center gap-2 px-6 py-8">
                                    <x-ui.icon name="scan" class="h-8 w-8 text-fg-3" />
                                    <span class="font-semibold text-fg">เลือกภาพรถ</span>
                                    <span class="text-caption text-fg-3">แตะเพื่อเลือกไฟล์ หรือลากภาพมาวาง</span>
                                </span>
                                <input id="car_image" name="car_image" type="file" required accept="image/jpeg,image/png"
                                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                    @if ($errors->has('car_image')) aria-invalid="true" aria-describedby="car_image-error car_image-hint" @else aria-describedby="car_image-hint" @endif
                                    x-on:change="handleFile($event)">
                            </label>
                            <p x-show="fileName" x-cloak class="truncate text-caption text-fg-2" x-text="'ไฟล์: ' + fileName"></p>
                        </x-ui.field>

                        <x-ui.button type="submit" class="w-full">วิเคราะห์รูปรถ</x-ui.button>
                    </form>
                @endif
            </section>

            {{-- ── ผลล่าสุด ─────────────────────────────────────────── --}}
            @if ($hasResult || $errors->any())
                <div @class(['order-1 flex flex-col gap-4', 'lg:order-2' => $hasResult])>
                    @if ($errors->any())
                        <x-ui.alert tone="danger" title="สแกนไม่สำเร็จ">{{ $errors->first() }}</x-ui.alert>
                    @endif

                    @if ($hasResult)
                        @include('scan.partials.result')
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
