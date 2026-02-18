<x-app-layout>
    <div class="sp-bg min-h-screen text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-extrabold sp-glow-text">แก้ไขผู้ใช้</h1>
                    <p class="text-gray-300 mt-1">{{ $user->email }}</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="sp-btn sp-btn-outline">ย้อนกลับ</a>
            </div>

            @if (session('success'))
                <div class="sp-card rounded-2xl p-4 mt-6 border border-green-600/40">
                    <p class="text-green-200 font-semibold">{{ session('success') }}</p>
                </div>
            @endif

            {{-- Card: ข้อมูลผู้ใช้ --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                <h2 class="text-xl font-extrabold mb-4">ข้อมูลผู้ใช้</h2>

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">ชื่อ</label>
                        <input name="name" value="{{ old('name', $user->name) }}"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                        @error('name')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">อีเมล</label>
                        <input name="email" value="{{ old('email', $user->email) }}"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600" />
                        @error('email')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">role</label>
                        <select name="role" class="sp-select">
                            @foreach ($roles as $r)
                                <option value="{{ $r }}" @selected(old('role', $user->role) === $r)>{{ $r }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <div class="text-sm text-gray-300">
                            force reset:
                            @if ($user->force_password_reset)
                                <span class="sp-badge sp-badge-bad">required</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </div>
                        <button class="sp-btn sp-btn-primary" type="submit">บันทึก</button>
                    </div>
                </form>
            </div>

            {{-- Card: Force reset (ตั้งรหัสชั่วคราว) --}}
            <div class="sp-card rounded-2xl p-6 mt-6">
                <h2 class="text-xl font-extrabold mb-2">Force reset password</h2>
                <p class="text-gray-300 text-sm">
                    ตั้ง “รหัสชั่วคราว” ให้ผู้ใช้นี้ และบังคับให้เปลี่ยนรหัสเมื่อเข้า /dashboard ครั้งถัดไป
                </p>

                <form method="POST" action="{{ route('admin.users.force-reset', $user) }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-sm text-gray-200 mb-1">Temporary Password (อย่างน้อย 8 ตัว)</label>
                        <input name="temporary_password" type="text" value="{{ old('temporary_password') }}"
                            class="w-full rounded-xl bg-black/40 border border-red-900/60 text-white focus:ring-0 focus:border-red-600"
                            placeholder="เช่น Temp@1234" />
                        @error('temporary_password')
                            <p class="text-red-300 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="sp-btn sp-btn-danger">
                            ตั้งรหัสชั่วคราว + บังคับเปลี่ยนรหัส
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
