<section aria-labelledby="profile-information-title" class="rounded-card border border-line bg-surface shadow-1">
    <div class="grid gap-6 p-5 sm:p-6 md:grid-cols-[14rem_1fr] md:gap-10">
        <header>
            <h2 id="profile-information-title" class="text-h3 text-fg">ข้อมูลบัญชี</h2>
            <p class="mt-1 text-label text-fg-3">ชื่อที่แสดงในระบบ และอีเมลที่ใช้เข้าสู่ระบบ</p>
        </header>

        <div>
            <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                @csrf
            </form>

            <form method="post" action="{{ route('profile.update') }}" class="flex flex-col gap-5">
                @csrf
                @method('patch')

                @if (session('status') === 'profile-updated')
                    <x-ui.alert tone="success">บันทึกข้อมูลบัญชีแล้ว</x-ui.alert>
                @endif

                <x-ui.field label="ชื่อ-นามสกุล" for="name" required>
                    <x-ui.input id="name" name="name" type="text" :value="old('name', $user->name)" required autocomplete="name" />
                </x-ui.field>

                <x-ui.field label="อีเมล" for="email" required hint="ถ้าเปลี่ยนอีเมล ต้องยืนยันอีเมลใหม่ก่อนใช้งานต่อ">
                    <x-ui.input id="email" name="email" type="email" :value="old('email', $user->email)" required
                        autocomplete="username" inputmode="email" />
                </x-ui.field>

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <x-ui.alert tone="warning" title="ยังไม่ได้ยืนยันอีเมล">
                        @if (session('status') === 'verification-link-sent')
                            ส่งลิงก์ยืนยันใหม่ไปที่ {{ $user->email }} แล้ว
                        @else
                            ต้องยืนยันอีเมลก่อนจึงจะใช้การจอง การสแกน และการแจ้งเตือนได้
                        @endif
                        <button form="send-verification" type="submit"
                            class="mt-2 inline-flex min-h-touch items-center font-semibold text-fg underline underline-offset-4">
                            ส่งลิงก์ยืนยันอีกครั้ง
                        </button>
                    </x-ui.alert>
                @endif

                <div>
                    <x-ui.button type="submit">บันทึกข้อมูลบัญชี</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</section>
