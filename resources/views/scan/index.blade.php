<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- ── Header ──────────────────────────────────────────── --}}
            <div class="mb-6">
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.scan.history') }}"
                       class="text-gray-400 hover:text-white text-sm transition">← ประวัติการสแกน</a>
                @else
                    <a href="{{ route('user.dashboard') }}"
                       class="text-gray-400 hover:text-white text-sm transition">← หน้าหลัก</a>
                @endif
                <h1 class="text-2xl font-extrabold sp-glow-text mt-2">สแกนรถด้วย AI</h1>
                <p class="text-gray-400 text-sm mt-0.5">Car Detection — อัปโหลดรูปรถเพื่อตรวจสอบทะเบียน สี และยี่ห้อ</p>
            </div>

            {{-- ── Validation Errors ───────────────────────────────── --}}
            @if($errors->any())
                <x-sp-alert type="error" class="mb-5" :dismissible="true">
                    <ul class="space-y-0.5">
                        @foreach($errors->all() as $e)
                            <li>• {{ $e }}</li>
                        @endforeach
                    </ul>
                </x-sp-alert>
            @endif

            {{-- ── Result Card (shown after successful scan) ────────── --}}
            @if(session('scan_result'))
                @php
                    $scan = \App\Models\LicensePlateScan::with('vehicle.user')->find(session('scan_result'));
                @endphp
                @if($scan)
                    {{-- Blacklist Alert --}}
                    @if($scan->is_suspicious)
                        <div class="mb-5 rounded-2xl border border-red-500/70 bg-red-950/40 p-4 flex items-start gap-3 animate-pulse">
                            <div class="shrink-0 w-8 h-8 rounded-xl bg-red-600/30 border border-red-500/60 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-extrabold text-red-300 text-sm">⚠ รถอยู่ใน Blacklist</p>
                                <p class="text-red-400 text-xs mt-0.5">
                                    ทะเบียน <span class="font-black">{{ $scan->license_plate }}</span>
                                    ถูกระบุว่าเป็นรถต้องสงสัย กรุณาแจ้งเจ้าหน้าที่ทันที
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Result Content --}}
                    <div class="sp-card rounded-2xl p-6 mb-6">
                        <div class="flex items-center gap-2 mb-5">
                            <div class="w-8 h-8 rounded-xl bg-green-500/20 border border-green-500/40 flex items-center justify-center shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-extrabold text-white text-base">ผลการวิเคราะห์</h2>
                                <p class="text-xs text-gray-500">{{ $scan->scan_time->format('d/m/Y H:i:s') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-5">
                            {{-- License Plate --}}
                            <div class="col-span-2 rounded-xl border border-red-800/50 bg-black/40 p-4 text-center">
                                <p class="text-xs text-gray-500 mb-1">ทะเบียนรถ</p>
                                <p class="text-3xl font-extrabold tracking-widest sp-glow-text">
                                    {{ $scan->license_plate ?: '—' }}
                                </p>
                                @if($scan->confidence)
                                    <p class="text-xs text-gray-600 mt-1">
                                        ความมั่นใจ {{ number_format($scan->confidence, 1) }}%
                                    </p>
                                @endif
                            </div>

                            {{-- Color --}}
                            <div class="rounded-xl border border-white/10 bg-black/30 p-4">
                                <p class="text-xs text-gray-500 mb-1">สีรถ</p>
                                <div class="flex items-center gap-2">
                                    @php
                                        $colorMap = [
                                            'white'  => '#f8fafc',
                                            'black'  => '#1e1e1e',
                                            'silver' => '#c0c0c0',
                                            'gray'   => '#6b7280',
                                            'red'    => '#dc2626',
                                            'blue'   => '#2563eb',
                                            'green'  => '#16a34a',
                                            'yellow' => '#eab308',
                                            'orange' => '#f97316',
                                            'brown'  => '#92400e',
                                            'pink'   => '#ec4899',
                                            'purple' => '#9333ea',
                                        ];
                                        $colorKey = strtolower($scan->color ?? '');
                                        $colorHex = $colorMap[$colorKey] ?? '#6b7280';
                                        $colorTh  = [
                                            'white' => 'ขาว', 'black' => 'ดำ', 'silver' => 'เงิน',
                                            'gray' => 'เทา', 'red' => 'แดง', 'blue' => 'น้ำเงิน',
                                            'green' => 'เขียว', 'yellow' => 'เหลือง', 'orange' => 'ส้ม',
                                            'brown' => 'น้ำตาล', 'pink' => 'ชมพู', 'purple' => 'ม่วง',
                                        ][$colorKey] ?? ($scan->color ?? '—');
                                    @endphp
                                    <span class="w-5 h-5 rounded-full border border-white/20 shrink-0"
                                          style="background:{{ $colorHex }}"></span>
                                    <span class="font-extrabold text-white text-lg">{{ $colorTh }}</span>
                                </div>
                            </div>

                            {{-- Brand --}}
                            <div class="rounded-xl border border-white/10 bg-black/30 p-4">
                                <p class="text-xs text-gray-500 mb-1">ยี่ห้อรถ</p>
                                <p class="font-extrabold text-white text-lg">{{ $scan->brand ?: '—' }}</p>
                            </div>
                        </div>

                        {{-- Scanned Image --}}
                        @if($scan->image_path)
                            <div class="rounded-xl overflow-hidden border border-white/10">
                                <img src="{{ Storage::url($scan->image_path) }}"
                                     alt="Scanned car"
                                     class="w-full max-h-56 object-cover">
                            </div>
                        @endif

                        {{-- Matched Vehicle --}}
                        @if($scan->vehicle)
                            <div class="mt-4 rounded-xl border border-green-800/40 bg-green-950/20 p-3 flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h6l2-2zM13 10h4l3 6H13v-6z"/>
                                </svg>
                                <div class="text-sm">
                                    <span class="text-green-300 font-bold">พบในระบบ</span>
                                    <span class="text-gray-400 mx-1">—</span>
                                    <span class="text-white font-semibold">{{ $scan->vehicle->license_plate }}</span>
                                    @if($scan->vehicle->user)
                                        <span class="text-gray-500 text-xs ml-1">({{ $scan->vehicle->user->name }})</span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-xl border border-yellow-800/40 bg-yellow-950/20 p-3 flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-yellow-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-yellow-300 text-sm">ไม่พบทะเบียนนี้ในระบบ</p>
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            {{-- ── Upload Form ─────────────────────────────────────── --}}
            <div class="sp-card rounded-2xl p-6"
                 x-data="{
                     preview: null,
                     loading: false,
                     handleFile(event) {
                         const file = event.target.files[0];
                         if (!file) return;
                         const reader = new FileReader();
                         reader.onload = e => { this.preview = e.target.result; };
                         reader.readAsDataURL(file);
                     }
                 }">

                <form method="POST"
                      action="{{ auth()->user()->role === 'admin' ? route('admin.scan.store') : route('user.scan.store') }}"
                      enctype="multipart/form-data"
                      class="space-y-5"
                      @submit="loading = true">
                    @csrf

                    {{-- Drop Zone --}}
                    <div>
                        <label for="car_image"
                               class="block text-sm font-semibold text-gray-300 mb-2">
                            รูปภาพรถ
                            <span class="text-gray-600 font-normal ml-1">(JPG / PNG ไม่เกิน 5 MB)</span>
                        </label>

                        <label for="car_image"
                               class="relative flex flex-col items-center justify-center w-full min-h-[180px]
                                      rounded-2xl border-2 border-dashed cursor-pointer transition-all
                                      border-red-900/60 hover:border-red-600/80 bg-black/30 hover:bg-black/50"
                               :class="preview ? 'border-red-600/50' : ''">

                            {{-- Preview Image --}}
                            <template x-if="preview">
                                <img :src="preview" alt="Preview"
                                     class="absolute inset-0 w-full h-full object-contain rounded-2xl p-1">
                            </template>

                            {{-- Placeholder --}}
                            <template x-if="!preview">
                                <div class="flex flex-col items-center gap-2 p-8 text-center">
                                    <div class="w-12 h-12 rounded-2xl bg-red-900/20 border border-red-900/40 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-300 font-semibold text-sm">คลิกเพื่อเลือกรูปภาพ</p>
                                    <p class="text-gray-600 text-xs">หรือลากไฟล์มาวางที่นี่</p>
                                </div>
                            </template>

                            <input id="car_image" name="car_image" type="file"
                                   accept="image/jpg,image/jpeg,image/png"
                                   class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                   @change="handleFile($event)">
                        </label>

                        {{-- Change button when preview shown --}}
                        <template x-if="preview">
                            <p class="text-center text-xs text-gray-500 mt-2">
                                คลิกรูปเพื่อเปลี่ยนภาพ
                            </p>
                        </template>

                        <x-input-error :messages="$errors->get('car_image')" class="mt-2" />
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                            class="sp-btn sp-btn-primary sp-glow-btn w-full justify-center py-3 gap-2"
                            :disabled="!preview || loading"
                            :class="(!preview || loading) ? 'opacity-50 cursor-not-allowed' : ''">

                        <template x-if="!loading">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                                </svg>
                                วิเคราะห์รูปรถ
                            </span>
                        </template>

                        <template x-if="loading">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                กำลังวิเคราะห์ AI...
                            </span>
                        </template>
                    </button>

                    <p class="text-center text-xs text-gray-600">
                        ระบบใช้ EasyOCR + OpenCV วิเคราะห์ทะเบียน สี และยี่ห้อรถโดยอัตโนมัติ
                    </p>
                </form>
            </div>

            {{-- Admin: link to history --}}
            @if(auth()->user()->role === 'admin')
                <div class="mt-4 text-center">
                    <a href="{{ route('admin.scan.history') }}"
                       class="text-sm text-gray-500 hover:text-gray-300 transition">
                        ดูประวัติการสแกนทั้งหมด →
                    </a>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
