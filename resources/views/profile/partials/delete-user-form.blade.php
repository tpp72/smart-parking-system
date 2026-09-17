@php
    $deletionErrors = $errors->userDeletion;
    $activeBookings = $deletionBlocker === null
        ? \App\Models\Reservation::where('user_id', $user->id)->whereIn('status', ['pending', 'confirmed'])->count()
        : 0;
@endphp

<section aria-labelledby="delete-account-title" class="rounded-card border border-line bg-surface shadow-1">
    <div class="grid gap-6 p-5 sm:p-6 md:grid-cols-[14rem_1fr] md:gap-10">
        <header>
            <h2 id="delete-account-title" class="text-h3 text-fg">ลบบัญชี</h2>
            <p class="mt-1 text-label text-fg-3">ลบบัญชีและข้อมูลการจองของบัญชีนี้ออกจากระบบถาวร</p>
        </header>

        <div class="flex flex-col items-start gap-4">
            @if ($deletionErrors->has('account'))
                <x-ui.alert tone="danger" class="w-full">{{ $deletionErrors->first('account') }}</x-ui.alert>
            @endif

            @if ($deletionBlocker)
                <p class="text-fg-2">{{ $deletionBlocker }}</p>
                @if ($user->role === 'owner')
                    <x-ui.button variant="secondary" :href="route('owner.dashboard').'#owner-resignation'">ไปที่คำร้องลาออก</x-ui.button>
                @endif
            @else
                <ul class="flex list-disc flex-col gap-1 pl-5 text-fg-2 marker:text-fg-3">
                    <li>
                        ระบบยกเลิกการจองที่ยังไม่ Check-in ทั้งหมด
                        <span class="num font-semibold text-fg">{{ $activeBookings }}</span> รายการ
                    </li>
                    <li>มัดจำที่เจ้าหน้าที่ยืนยันรับเงินแล้วจะไม่ได้รับคืน</li>
                    <li>กู้คืนบัญชีไม่ได้ ต้องสมัครใหม่หากต้องการใช้งานอีกครั้ง</li>
                </ul>

                <x-ui.button variant="danger" x-data x-on:click="$dispatch('open-modal', 'confirm-user-deletion')">
                    ลบบัญชี
                </x-ui.button>

                <x-ui.modal name="confirm-user-deletion" :show="$deletionErrors->has('password')" maxWidth="md"
                    title="ลบบัญชีถาวร?"
                    description="กรอกรหัสผ่านเพื่อยืนยัน ระบบจะยกเลิกการจองที่ยังไม่ Check-in แล้วลบบัญชีทันที">
                    <form method="post" action="{{ route('profile.destroy') }}" id="delete-account-form" class="px-5 py-4" data-no-busy>
                        @csrf
                        @method('delete')

                        <x-ui.field label="รหัสผ่าน" for="delete_account_password" required :error="$deletionErrors->get('password')">
                            <x-password-input id="delete_account_password" name="password" autocomplete="current-password"
                                :invalid="$deletionErrors->has('password')" required />
                        </x-ui.field>
                    </form>

                    <x-slot name="footer">
                        <x-ui.button variant="secondary" x-on:click="$dispatch('close')">ยกเลิก</x-ui.button>
                        <x-ui.button type="submit" form="delete-account-form" variant="danger">ลบบัญชี</x-ui.button>
                    </x-slot>
                </x-ui.modal>
            @endif
        </div>
    </div>
</section>
