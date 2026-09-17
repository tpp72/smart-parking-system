<x-guest-layout title="เข้าสู่ระบบ" description="ใช้อีเมลและรหัสผ่านที่สมัครไว้">
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form id="login-form" method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <x-ui.field label="อีเมล" for="email" required>
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus
                autocomplete="username" inputmode="email" placeholder="name@example.com" />
        </x-ui.field>

        <x-ui.field label="รหัสผ่าน" for="password" required>
            <x-password-input id="password" name="password" autocomplete="current-password" required />
        </x-ui.field>

        <div class="flex flex-wrap items-center justify-between gap-x-4">
            <x-ui.checkbox name="remember" id="remember_me" label="จดจำการเข้าสู่ระบบ" />

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                    class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
                    ลืมรหัสผ่าน?
                </a>
            @endif
        </div>

        <x-ui.button type="submit" class="w-full">เข้าสู่ระบบ</x-ui.button>
    </form>

    <p class="mt-6 border-t border-line pt-5 text-center text-fg-2">
        ยังไม่มีบัญชี?
        <a href="{{ route('register') }}" class="font-semibold text-primary-ink underline-offset-4 hover:underline">สมัครสมาชิก</a>
    </p>

    {{-- บัญชีทดลองสำหรับสาธิต — แสดงเฉพาะเครื่องพัฒนา (APP_ENV=local) --}}
    @if (app()->environment('local'))
        <x-slot name="after">
            <section aria-labelledby="demo-accounts-title" class="mt-6 rounded-card border border-dashed border-field px-5 py-4"
                x-data="{
                    use(email) {
                        const form = document.getElementById('login-form');
                        form.querySelector('#email').value = email;
                        form.querySelector('#password').value = 'password';
                        form.querySelector('button[type=submit]').focus();
                    }
                }">
                <h2 id="demo-accounts-title" class="text-label font-semibold text-fg">บัญชีทดลอง</h2>
                <p class="mt-1 text-caption text-fg-3">แสดงเฉพาะเครื่องพัฒนา · รหัสผ่านทุกบัญชีคือ <span class="num text-fg-2">password</span></p>
                <ul class="mt-3 flex flex-col gap-1">
                    @foreach ([['ผู้ดูแลระบบ', 'admin@demo.com'], ['เจ้าของลาน', 'owner@demo.com'], ['ผู้ใช้', 'user@demo.com']] as [$role, $email])
                        <li>
                            <button type="button" x-on:click="use(@js($email))"
                                class="flex min-h-touch w-full items-center justify-between gap-3 rounded-card px-3 text-start transition-colors duration-fast hover:bg-surface-2">
                                <span class="text-label font-semibold text-fg">{{ $role }}</span>
                                <span class="num truncate text-caption text-fg-2">{{ $email }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </section>
        </x-slot>
    @endif
</x-guest-layout>
