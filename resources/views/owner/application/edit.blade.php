<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="mb-2">
                <h1 class="text-2xl font-extrabold tracking-tight sp-glow-text">แก้ไขคำขอและส่งใหม่</h1>
                <p class="text-gray-400 text-sm mt-1">แก้ไขข้อมูลตามเหตุผลที่ Admin แจ้ง แล้วส่งคำขอใหม่</p>
            </div>

            @if($application->rejection_reason)
            <div class="sp-card rounded-xl p-4 border border-red-500/30 mb-6">
                <p class="text-xs text-red-300 font-semibold uppercase tracking-wide mb-1">เหตุผลที่ไม่อนุมัติก่อนหน้า</p>
                <p class="text-sm text-gray-300">{{ $application->rejection_reason }}</p>
            </div>
            @endif

            @if($errors->any())
            <div class="sp-card rounded-xl p-4 border border-red-500/40 mb-6">
                <ul class="list-disc list-inside space-y-1 text-sm text-red-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('owner.application.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลธุรกิจ</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อธุรกิจ / บริษัท <span class="text-red-400">*</span></label>
                        <input type="text" name="business_name" value="{{ old('business_name', $application->business_name) }}"
                            class="sp-input w-full" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อผู้ติดต่อ <span class="text-red-400">*</span></label>
                            <input type="text" name="contact_name" value="{{ old('contact_name', $application->contact_name) }}"
                                class="sp-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">เบอร์โทรศัพท์ <span class="text-red-400">*</span></label>
                            <input type="text" name="phone" value="{{ old('phone', $application->phone) }}"
                                class="sp-input w-full" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">อีเมลติดต่อธุรกิจ <span class="text-red-400">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $application->email) }}"
                            class="sp-input w-full" required>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">ข้อมูลลานจอด</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ชื่อลานจอด <span class="text-red-400">*</span></label>
                        <input type="text" name="parking_lot_name" value="{{ old('parking_lot_name', $application->parking_lot_name) }}"
                            class="sp-input w-full" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">ที่อยู่ / สถานที่ <span class="text-red-400">*</span></label>
                        <textarea name="address" rows="3" class="sp-input w-full resize-none" required>{{ old('address', $application->address) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">รายละเอียดเพิ่มเติม</label>
                        <textarea name="description" rows="3" class="sp-input w-full resize-none">{{ old('description', $application->description) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">จำนวนช่องจอดโดยประมาณ <span class="text-red-400">*</span></label>
                        <input type="number" name="estimated_slots" value="{{ old('estimated_slots', $application->estimated_slots) }}"
                            class="sp-input w-full" min="1" max="10000" required>
                    </div>
                </div>

                <div class="sp-card rounded-2xl p-6 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wide border-b sp-divider pb-2">เอกสารประกอบ</h2>
                    @if($application->document_path)
                    <p class="text-sm text-gray-400">เอกสารปัจจุบัน:
                        <a href="{{ Storage::url($application->document_path) }}" target="_blank" class="text-red-400 hover:text-red-300 underline">ดูไฟล์เดิม</a>
                    </p>
                    @endif
                    <p class="text-xs text-gray-400">อัปโหลดไฟล์ใหม่เพื่อแทนที่ (JPG, PNG, PDF — ไม่เกิน 5MB)</p>
                    <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf"
                        class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-600/20 file:text-red-300 hover:file:bg-red-600/30 cursor-pointer">
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="sp-btn sp-btn-primary flex-1">ส่งคำขอใหม่</button>
                    <a href="{{ route('owner.application.show') }}" class="sp-btn sp-btn-outline">ยกเลิก</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
