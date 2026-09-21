{{--
    จัดการผู้ใช้ — ข้อมูลบัญชีและบทบาท (ปลดเจ้าของลานต้องระบุเหตุผล) · ตั้งรหัสผ่านชั่วคราว · ลบบัญชี
    ผลกระทบของการปลด/ลบแสดงก่อนยืนยันเสมอ · ต้องการ: $user, $roles, $ownedLotsCount, $impact (bookings, parked)
--}}
@use('App\Support\Format')
@use('App\Support\Navigation')

@php
    $isSelf = $user->id === auth()->id();
    $roleLabel = Navigation::ROLE_LABELS[$user->role] ?? $user->role;
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-touch items-center gap-1 text-label font-semibold text-primary-ink underline-offset-4 hover:underline">
            <x-ui.icon name="chevron-right" class="h-4 w-4 rotate-180" /> ผู้ใช้
        </a>
        <h1 class="mt-2 text-h1 text-fg">{{ $user->name }} @if ($isSelf)<span class="text-h3 font-normal text-fg-3">(คุณ)</span>@endif</h1>
        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-fg-2">
            <span>{{ $user->email }}</span>
            <span class="rounded-control border border-line px-2 py-0.5 text-label font-semibold text-fg">{{ $roleLabel }}</span>
            @if ($user->force_password_reset)
                <span class="text-label text-warning">ต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งถัดไป</span>
            @endif
            @unless ($user->email_verified_at)
                <span class="text-label text-warning">ยังไม่ยืนยันอีเมล</span>
            @endunless
        </p>
        <p class="mt-1 text-caption text-fg-3">สมัครเมื่อ {{ Format::short($user->created_at) }} · แก้ไขล่าสุด {{ Format::short($user->updated_at) }}</p>

        @error('error')
            <x-ui.alert tone="danger" class="mt-6">{{ $message }}</x-ui.alert>
        @enderror

        {{-- ── ข้อมูลบัญชีและบทบาท ─────────────────────────────────────── --}}
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6"
            x-data="{ role: @js(old('role', $user->role)) }"
            x-bind:data-confirm="role === 'user' && @js($user->role === 'owner') ? @js('ปลด '.$user->name.' กลับเป็นผู้ใช้ — การจองในลานของเขาที่ยังไม่ Check-in จะถูกยกเลิก รถที่จอดอยู่จะถูก Check-out ผู้จองจะได้รับแจ้งเตือน และลานจอด '.$ownedLotsCount.' แห่งจะถูกลบถาวร') : null"
            data-confirm-title="ปลดเจ้าของลาน?" data-confirm-label="ปลดและลบลาน" data-confirm-tone="danger" data-confirm-cancel="ยังไม่ปลด">
            @csrf
            @method('PATCH')

            <h2 class="text-h3 text-fg">ข้อมูลบัญชี</h2>
            <div class="mt-5 flex flex-col gap-5">
                <x-ui.field label="ชื่อ" for="name" required>
                    <x-ui.input id="name" name="name" :value="old('name', $user->name)" required maxlength="255" />
                </x-ui.field>
                <x-ui.field label="อีเมล" for="email" required>
                    <x-ui.input id="email" name="email" type="email" :value="old('email', $user->email)" required />
                </x-ui.field>

                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <div>
                        <p class="text-label font-semibold text-fg">บทบาท</p>
                        <p class="mt-1 text-fg">{{ $roleLabel }}</p>
                        <p class="text-caption text-fg-3">เปลี่ยนบทบาทของบัญชีตัวเองไม่ได้ เพื่อไม่ให้ระบบไม่มีผู้ดูแล</p>
                    </div>
                @else
                    <fieldset>
                        <legend class="text-label font-semibold text-fg">บทบาท</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($roles as $r)
                                <label class="flex min-h-touch cursor-pointer items-start gap-3 rounded-card border border-line px-4 py-3 transition-colors duration-fast hover:bg-surface-2 has-[:checked]:border-primary-ink has-[:checked]:bg-primary/5">
                                    <input type="radio" name="role" value="{{ $r }}" x-model="role" class="mt-0.5 h-5 w-5 border-field text-primary focus:ring-primary-ink" @checked(old('role', $user->role) === $r)>
                                    <span>
                                        <span class="block font-semibold text-fg">{{ Navigation::ROLE_LABELS[$r] ?? $r }}</span>
                                        <span class="block text-caption text-fg-3">
                                            @if ($user->role === 'owner')
                                                {{ $r === 'owner' ? 'คงเป็นเจ้าของลาน' : 'ปลดกลับเป็นผู้ใช้ — ลานของเขาจะถูกลบ' }}
                                            @else
                                                {{ $r === 'admin' ? 'จัดการทั้งระบบ' : 'จองที่จอดและดูประวัติของตัวเอง' }}
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-ui.error id="role-error" :messages="$errors->get('role')" class="mt-1.5" />
                        @if ($user->role !== 'owner')
                            <p class="mt-2 text-caption text-fg-3">เป็นเจ้าของลานได้ผ่านคำขอเป็นเจ้าของลานเท่านั้น</p>
                        @endif
                    </fieldset>

                    @if ($user->role === 'owner')
                        <div x-show="role === 'user'" x-cloak class="flex flex-col gap-4">
                            <x-ui.alert tone="danger" title="การปลดเจ้าของลานย้อนกลับไม่ได้">
                                การจองในลานของเขาที่ยังไม่ Check-in ถูกยกเลิก · รถที่จอดอยู่ถูก Check-out · ผู้จองได้รับแจ้งให้ติดต่อผู้ดูแลระบบ ·
                                ลานจอด <span class="tabular font-semibold">{{ $ownedLotsCount }}</span> แห่งและข้อมูลของลานถูกลบ
                            </x-ui.alert>
                            <x-ui.field label="เหตุผลในการปลด" for="demotion_reason" required hint="เจ้าของลานจะเห็นเหตุผลนี้ในการแจ้งเตือน">
                                <x-ui.textarea id="demotion_reason" name="demotion_reason" rows="3" maxlength="1000" x-bind:required="role === 'user'">{{ old('demotion_reason') }}</x-ui.textarea>
                            </x-ui.field>
                        </div>
                    @endif
                @endif
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button type="submit" x-show="role !== 'user' || {{ $user->role === 'owner' ? 'false' : 'true' }}">บันทึก</x-ui.button>
                @if ($user->role === 'owner' && ! $isSelf)
                    <x-ui.button type="submit" variant="danger" x-show="role === 'user'" x-cloak>ปลดเจ้าของลาน</x-ui.button>
                @endif
            </div>
        </form>

        {{-- ── รหัสผ่านชั่วคราว ───────────────────────────────────────── --}}
        <form method="POST" action="{{ route('admin.users.force-reset', $user) }}" class="mt-6 rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6"
            x-data="{
                password: @js(old('temporary_password', '')),
                generate() {
                    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
                    const bytes = crypto.getRandomValues(new Uint32Array(10));
                    this.password = Array.from(bytes, (b) => chars[b % chars.length]).join('');
                    this.$nextTick(() => this.$refs.password.select());
                },
            }"
            data-confirm="ตั้งรหัสผ่านชั่วคราวให้ {{ $user->name }} — รหัสเดิมใช้ไม่ได้ทันที และต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งถัดไป"
            data-confirm-title="ตั้งรหัสผ่านชั่วคราว?" data-confirm-label="ตั้งรหัสชั่วคราว" data-confirm-cancel="ยังไม่ตั้ง">
            @csrf
            @method('PATCH')

            <h2 class="text-h3 text-fg">ตั้งรหัสผ่านชั่วคราว</h2>
            <p class="mt-1 text-label text-fg-2">ใช้เมื่อผู้ใช้ลืมรหัสผ่านและรีเซ็ตทางอีเมลไม่ได้ แจ้งรหัสนี้ให้เจ้าของบัญชีโดยตรง · อย่างน้อย 8 ตัวอักษร</p>
            <div class="mt-5 flex flex-wrap items-end gap-3">
                <x-ui.field label="รหัสผ่านชั่วคราว" for="temporary_password" required class="min-w-0 flex-1 sm:max-w-xs">
                    <x-ui.input id="temporary_password" name="temporary_password" x-ref="password" x-model="password" required minlength="8" maxlength="255" autocomplete="off" spellcheck="false" numeric />
                </x-ui.field>
                <x-ui.button variant="secondary" x-on:click="generate()">สุ่มรหัส</x-ui.button>
            </div>
            <x-ui.button type="submit" class="mt-5">ตั้งรหัสชั่วคราว</x-ui.button>
        </form>

        {{-- ── ลบบัญชี ────────────────────────────────────────────────── --}}
        <section aria-labelledby="delete-title" class="mt-6 rounded-card border border-danger/40 bg-surface p-5 shadow-1 sm:p-6">
            <h2 id="delete-title" class="text-h3 text-fg">ลบบัญชี</h2>
            @if ($isSelf)
                <p class="mt-1 text-label text-fg-2">ลบบัญชีของตัวเองไม่ได้ ให้ผู้ดูแลระบบคนอื่นเป็นผู้ลบ</p>
            @else
                <p class="mt-1 text-label text-fg-2">ระบบเคลียร์ข้อมูลที่ยังค้างก่อน แล้วลบบัญชีและประวัติของผู้ใช้นี้ถาวร กู้คืนไม่ได้</p>
                <ul class="mt-3 flex flex-col gap-1 text-label text-fg">
                    <li>การจองที่ยังไม่ Check-in ถูกยกเลิก <span class="tabular font-semibold">{{ $impact['bookings'] }}</span> รายการ</li>
                    <li>รถที่จอดอยู่ถูก Check-out <span class="tabular font-semibold">{{ $impact['parked'] }}</span> คัน</li>
                    @if ($user->role === 'owner')
                        <li>ลานจอดของเจ้าของลานถูกปิดและลบ <span class="tabular font-semibold">{{ $ownedLotsCount }}</span> แห่ง (การจองในลานถูกยกเลิก รถในลานถูก Check-out)</li>
                    @endif
                </ul>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4"
                    data-confirm="ลบบัญชี {{ $user->name }} ({{ $user->email }}) ถาวร — ยกเลิกการจอง {{ $impact['bookings'] }} รายการ · Check-out รถ {{ $impact['parked'] }} คัน{{ $user->role === 'owner' ? ' · ลบลานจอด '.$ownedLotsCount.' แห่ง' : '' }} กู้คืนไม่ได้"
                    data-confirm-title="ลบบัญชีถาวร?" data-confirm-label="ลบบัญชี" data-confirm-tone="danger" data-confirm-cancel="เก็บบัญชีไว้">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">ลบบัญชีนี้</x-ui.button>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
