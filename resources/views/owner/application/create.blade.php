<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
             x-data="{ type: '{{ old('applicant_type', 'individual') }}' }">

            <div class="mb-6">
                <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">สมัครเป็นเจ้าของลานจอด</h1>
                <p class="text-gray-400 text-sm mt-1">กรอกข้อมูลและลานจอดของคุณ Admin จะพิจารณาและแจ้งผลให้ทราบ</p>
            </div>

            @if($errors->any())
            <div class="sp-card rounded-xl p-4 border border-red-500/40 mb-6">
                <ul class="list-disc list-inside space-y-1 text-sm text-red-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Toggle บุคคลธรรมดา / บริษัท --}}
            <div class="sp-card rounded-2xl p-1 flex mb-6">
                <button type="button" @click="type='individual'"
                    :class="type === 'individual'
                        ? 'bg-red-600 text-white shadow-[0_0_10px_rgba(220,38,38,0.5)]'
                        : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all duration-200">
                    บุคคลธรรมดา
                </button>
                <button type="button" @click="type='company'"
                    :class="type === 'company'
                        ? 'bg-red-600 text-white shadow-[0_0_10px_rgba(220,38,38,0.5)]'
                        : 'text-gray-400 hover:text-white'"
                    class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all duration-200">
                    บริษัท / นิติบุคคล
                </button>
            </div>

            <form action="{{ route('owner.application.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="applicant_type" :value="type">

                {{-- ข้อมูลผู้สมัคร --}}
                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2"
                        x-text="type === 'company' ? 'ข้อมูลธุรกิจ' : 'ข้อมูลผู้สมัคร'"></h2>

                    {{-- ชื่อธุรกิจ: แสดงเฉพาะ company --}}
                    <div x-show="type === 'company'" x-cloak>
                        <label class="block text-sm font-medium text-gray-300 mb-1">
                            ชื่อธุรกิจ / บริษัท <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}"
                            :required="type === 'company'"
                            class="sp-input w-full" placeholder="เช่น บริษัท ABC จำกัด">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                ชื่อผู้ติดต่อ <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="contact_name"
                                value="{{ old('contact_name', auth()->user()->name) }}"
                                class="sp-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">
                                เบอร์โทรศัพท์ <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="phone" value="{{ old('phone') }}"
                                class="sp-input w-full" placeholder="0812345678" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">
                            <span x-text="type === 'company' ? 'อีเมลติดต่อธุรกิจ' : 'อีเมล'"></span>
                            <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="email"
                            value="{{ old('email', auth()->user()->email) }}"
                            class="sp-input w-full" required>
                    </div>
                </div>

                {{-- ข้อมูลลานจอด --}}
                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลลานจอด</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">
                            ชื่อลานจอด <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="parking_lot_name" value="{{ old('parking_lot_name') }}"
                            class="sp-input w-full" placeholder="เช่น ลานจอดรถ ABC สาขาสยาม" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ที่อยู่ (เลขที่ / ถนน / อาคาร)</label>
                        <input type="text" name="address" value="{{ old('address') }}"
                            class="sp-input w-full" placeholder="เช่น 123/45 ถนนสุขุมวิท">
                        @error('address')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">แขวง / ตำบล / เขต / อำเภอ <span class="text-red-400">*</span></label>
                            <input type="text" name="district" value="{{ old('district') }}"
                                class="sp-input w-full" placeholder="เช่น คลองเตย" required>
                            @error('district')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">จังหวัด <span class="text-red-400">*</span></label>
                            <input type="text" name="province" value="{{ old('province') }}"
                                class="sp-input w-full" placeholder="เช่น กรุงเทพมหานคร" required>
                            @error('province')<p class="text-red-300 text-sm mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" rows="3" class="sp-input w-full resize-none"
                            placeholder="สิ่งอำนวยความสะดวก, จุดสังเกต ฯลฯ">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">
                            จำนวนช่องจอดโดยประมาณ <span class="text-red-400">*</span>
                        </label>
                        <input type="number" name="estimated_slots" value="{{ old('estimated_slots') }}"
                            class="sp-input w-full" min="1" max="10000" placeholder="50" required>
                    </div>
                </div>

                {{-- เอกสาร --}}
                <div class="sp-card rounded-2xl p-6 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">
                        เอกสารประกอบ <span class="font-normal normal-case text-gray-500">(ไม่บังคับ)</span>
                    </h2>
                    <p class="text-xs text-gray-400">
                        <span x-show="type === 'company'" x-cloak>หนังสือรับรองบริษัท, </span>เอกสารสิทธิ์, รูปถ่ายลานจอด (JPG, PNG, PDF — ไม่เกิน 5MB)
                    </p>
                    <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full text-sm text-gray-400
                               file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-semibold file:bg-red-600/20 file:text-red-300
                               hover:file:bg-red-600/30 cursor-pointer">
                    @error('document')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="sp-btn sp-btn-primary flex-1">ส่งคำขอ</button>
                    <a href="{{ route('user.dashboard') }}" class="sp-btn sp-btn-outline">ยกเลิก</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
