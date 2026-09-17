@php($passwordErrors = $errors->updatePassword)

<section id="update-password" aria-labelledby="update-password-title" class="rounded-card border border-line bg-surface shadow-1">
    <div class="grid gap-6 p-5 sm:p-6 md:grid-cols-[14rem_1fr] md:gap-10">
        <header>
            <h2 id="update-password-title" class="text-h3 text-fg">เปลี่ยนรหัสผ่าน</h2>
            <p class="mt-1 text-label text-fg-3">ใช้รหัสผ่านอย่างน้อย 8 ตัวอักษร และไม่ซ้ำกับบริการอื่น</p>
        </header>

        <form method="post" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf
            @method('put')

            @if (session('status') === 'password-updated')
                <x-ui.alert tone="success">เปลี่ยนรหัสผ่านแล้ว</x-ui.alert>
            @endif

            <x-ui.field label="รหัสผ่านปัจจุบัน" for="update_password_current_password" required
                :error="$passwordErrors->get('current_password')"
                :hint="$user->force_password_reset ? 'ใช้รหัสผ่านชั่วคราวที่ได้รับจากผู้ดูแลระบบ' : null">
                <x-password-input id="update_password_current_password" name="current_password" autocomplete="current-password"
                    :invalid="$passwordErrors->has('current_password')" required />
            </x-ui.field>

            <x-ui.field label="รหัสผ่านใหม่" for="update_password_password" required :error="$passwordErrors->get('password')">
                <x-password-input id="update_password_password" name="password" autocomplete="new-password"
                    :invalid="$passwordErrors->has('password')" required />
            </x-ui.field>

            <x-ui.field label="ยืนยันรหัสผ่านใหม่" for="update_password_password_confirmation" required
                :error="$passwordErrors->get('password_confirmation')">
                <x-password-input id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password"
                    :invalid="$passwordErrors->has('password_confirmation')" required />
            </x-ui.field>

            <div>
                <x-ui.button type="submit">เปลี่ยนรหัสผ่าน</x-ui.button>
            </div>
        </form>
    </div>
</section>
