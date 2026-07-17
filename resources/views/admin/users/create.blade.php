<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">เพิ่มผู้ใช้ใหม่</h1>
                    <p class="text-gray-300 mt-1">สร้างบัญชี user / owner / admin โดยตรง</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            @if ($errors->any())
                <div class="sp-card rounded-2xl p-4 mt-6 border border-red-600/40">
                    @foreach ($errors->all() as $error)
                        <p class="text-red-300 text-sm">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="sp-card rounded-2xl p-6 mt-6">
                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ชื่อ</label>
                        <input name="name" value="{{ old('name') }}"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                        @error('name')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">อีเมล</label>
                        <input name="email" type="email" value="{{ old('email') }}"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                        @error('email')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">Role</label>
                        <select name="role" class="sp-select">
                            @foreach ($roles as $r)
                                <option value="{{ $r }}" @selected(old('role') === $r)>{{ $r }}</option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-gray-400 text-xs mt-1">เลือก <span class="font-semibold">admin</span> เพื่อสร้างผู้ดูแลระบบเพิ่ม</p>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">รหัสผ่านเริ่มต้น</label>
                        <input name="password" type="text"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600"
                            placeholder="อย่างน้อย 8 ตัว" />
                        @error('password')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ยืนยันรหัสผ่าน</label>
                        <input name="password_confirmation" type="text"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                    </div>

                    <p class="text-gray-400 text-xs">
                        ผู้ใช้ที่สร้างจะถูกบังคับให้เปลี่ยนรหัสผ่านทันทีเมื่อเข้าสู่ระบบครั้งแรก
                    </p>

                    <div class="flex justify-end pt-2">
                        <button class="sp-btn sp-btn-primary" type="submit">สร้างผู้ใช้</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
