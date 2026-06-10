<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-6">
                <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">สมัครเป็นเจ้าของลานจอด</h1>
                <p class="text-gray-400 text-sm mt-1">กรอกข้อมูลธุรกิจและลานจอดของคุณ Admin จะพิจารณาและแจ้งผลให้ทราบ</p>
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

            <form action="{{ route('owner.application.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลธุรกิจ</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อธุรกิจ / บริษัท <span class="text-red-400">*</span></label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}"
                            class="sp-input w-full" placeholder="เช่น บริษัท ABC จำกัด" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อผู้ติดต่อ <span class="text-red-400">*</span></label>
                            <input type="text" name="contact_name" value="{{ old('contact_name', auth()->user()->name) }}"
                                class="sp-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">เบอร์โทรศัพท์ <span class="text-red-400">*</span></label>
                            <input type="text" name="phone" value="{{ old('phone') }}"
                                class="sp-input w-full" placeholder="0812345678" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">อีเมลติดต่อธุรกิจ <span class="text-red-400">*</span></label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                            class="sp-input w-full" required>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลลานจอด</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อลานจอด <span class="text-red-400">*</span></label>
                        <input type="text" name="parking_lot_name" value="{{ old('parking_lot_name') }}"
                            class="sp-input w-full" placeholder="เช่น ลานจอดรถ ABC สาขาสยาม" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ที่อยู่ / สถานที่ <span class="text-red-400">*</span></label>
                        <textarea name="address" rows="3" class="sp-input w-full resize-none"
                            placeholder="ที่อยู่เต็มของลานจอด" required>{{ old('address') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" rows="3" class="sp-input w-full resize-none"
                            placeholder="ข้อมูลเพิ่มเติมเกี่ยวกับลานจอด เช่น สิ่งอำนวยความสะดวก ฯลฯ">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">จำนวนช่องจอดโดยประมาณ <span class="text-red-400">*</span></label>
                        <input type="number" name="estimated_slots" value="{{ old('estimated_slots') }}"
                            class="sp-input w-full" min="1" max="10000" placeholder="50" required>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">เอกสารประกอบ (ไม่บังคับ)</h2>
                    <p class="text-xs text-gray-400">เช่น หนังสือรับรองบริษัท, เอกสารสิทธิ์, รูปถ่ายลานจอด (JPG, PNG, PDF — ไม่เกิน 5MB)</p>
                    <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-600/20 file:text-red-300 hover:file:bg-red-600/30 cursor-pointer">
                    @error('document')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="sp-btn sp-btn-primary flex-1">ส่งคำขอ</button>
                    <a href="{{ auth()->user()->role === 'user' ? route('user.dashboard') : route('owner.dashboard') }}"
                        class="sp-btn sp-btn-outline">ยกเลิก</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
