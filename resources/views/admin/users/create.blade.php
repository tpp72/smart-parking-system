{{--
    เพิ่มผู้ใช้ — สร้างได้เฉพาะผู้ใช้หรือผู้ดูแลระบบ (เจ้าของลานต้องผ่านคำขอสมัคร)
    บัญชีใหม่ยืนยันอีเมลแล้ว และต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก · ต้องการ: $roles
--}}
@use('App\Support\Navigation')

<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> ผู้ใช้
        </a>
        <h1 class="mt-2 text-h1 text-fg">เพิ่มผู้ใช้</h1>
        <p class="mt-1 text-fg-2">บัญชีใหม่ใช้งานได้ทันที และต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก</p>

        <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 flex flex-col gap-6">
            @csrf

            <section aria-labelledby="account-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="account-title" class="text-h3 text-fg">ข้อมูลบัญชี</h2>
                <div class="mt-5 flex flex-col gap-5">
                    <x-ui.field label="ชื่อ" for="name" required>
                        <x-ui.input id="name" name="name" :value="old('name')" required autocomplete="off" maxlength="255" />
                    </x-ui.field>
                    <x-ui.field label="อีเมล" for="email" required>
                        <x-ui.input id="email" name="email" type="email" :value="old('email')" required autocomplete="off" />
                    </x-ui.field>

                    <fieldset>
                        <legend class="text-label font-semibold text-fg">บทบาท <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($roles as $r)
                                <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-2 has-[:checked]:border-primary-ink has-[:checked]:bg-primary/5">
                                    <input type="radio" name="role" value="{{ $r }}" class="mt-1 h-4 w-4 border-field text-primary focus:ring-primary-ink" @checked(old('role', $roles[0]) === $r)>
                                    <span>
                                        <span class="block font-semibold text-fg">{{ Navigation::ROLE_LABELS[$r] ?? $r }}</span>
                                        <span class="block text-caption text-fg-3">{{ $r === 'admin' ? 'จัดการทั้งระบบ รวมผู้ใช้และลานของผู้ดูแลระบบ' : 'จองที่จอดและดูประวัติของตัวเอง' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-ui.error id="role-error" :messages="$errors->get('role')" class="mt-1.5" />
                        <p class="mt-2 text-caption text-fg-3">ต้องการให้เป็นเจ้าของลาน? ให้ผู้ใช้ยื่นคำขอเป็นเจ้าของลาน แล้วอนุมัติในหน้าคำขอ</p>
                    </fieldset>
                </div>
            </section>

            <section aria-labelledby="password-title" class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6">
                <h2 id="password-title" class="text-h3 text-fg">รหัสผ่านเริ่มต้น</h2>
                <p class="mt-1 text-label text-fg-2">แจ้งรหัสนี้ให้เจ้าของบัญชี ระบบจะให้เปลี่ยนเป็นรหัสของตัวเองเมื่อเข้าสู่ระบบครั้งแรก</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="รหัสผ่าน" for="password" required hint="อย่างน้อย 8 ตัวอักษร">
                        <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
                    </x-ui.field>
                    <x-ui.field label="ยืนยันรหัสผ่าน" for="password_confirmation" required>
                        <x-ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                    </x-ui.field>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit">สร้างบัญชี</x-ui.button>
                <x-ui.button variant="ghost" :href="route('admin.users.index')">ยกเลิก</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
