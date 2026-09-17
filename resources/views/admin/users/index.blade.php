{{--
    ผู้ใช้ — ค้นหาชื่อ/อีเมล · กรองบทบาท · สถานะบัญชี (ต้องเปลี่ยนรหัสผ่าน / ยังไม่ยืนยันอีเมล) · ไปหน้าแก้ไข
    บัญชีระบบ (Walk-in) ไม่แสดง · การเป็นเจ้าของลานต้องผ่านคำขอสมัครเท่านั้น
--}}
@use('App\Support\Format')
@use('App\Support\Navigation')

@php
    $hasFilter = $q !== '' || $role;
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">ผู้ใช้</h1>
                <p class="mt-1 text-fg-2">บัญชีทุกบทบาทในระบบ · เป็นเจ้าของลานได้ผ่านคำขอเป็นเจ้าของลานเท่านั้น</p>
            </div>
            <x-ui.button :href="route('admin.users.create')">
                <x-ui.icon name="plus" class="h-4 w-4" /> เพิ่มผู้ใช้
            </x-ui.button>
        </div>

        <form method="GET" role="search" class="flex flex-wrap items-end gap-3 rounded-card border border-line bg-surface p-4 shadow-1 sm:p-5">
            <x-ui.field label="ค้นหา" for="q" class="min-w-0 flex-1 sm:max-w-sm">
                <x-ui.input id="q" name="q" type="search" :value="$q" placeholder="ชื่อ หรืออีเมล" />
            </x-ui.field>
            <x-ui.field label="บทบาท" for="role" class="w-full sm:w-48">
                <x-ui.select id="role" name="role" placeholder="ทุกบทบาท">
                    @foreach (Navigation::ROLE_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($role === $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.button type="submit" variant="secondary">ค้นหา</x-ui.button>
            @if ($hasFilter)
                <x-ui.button variant="ghost" :href="route('admin.users.index')">ล้างตัวกรอง</x-ui.button>
            @endif
            <p class="ml-auto self-center text-label text-fg-2">พบ <span class="tabular font-semibold text-fg">{{ $users->total() }}</span> บัญชี</p>
        </form>

        <div class="mt-4">
            @if ($users->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state :title="$hasFilter ? 'ไม่พบผู้ใช้ที่ตรงกับตัวกรอง' : 'ยังไม่มีผู้ใช้'" description="ลองค้นหาด้วยชื่อหรืออีเมลอื่น" />
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($users as $u)
                        <li class="grid gap-3 px-4 py-4 sm:px-5 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] md:items-center md:gap-6">
                            <div class="flex min-w-0 items-center gap-3">
                                <span aria-hidden="true" class="grid h-10 w-10 shrink-0 place-items-center rounded-control border border-line bg-surface-2 text-label font-semibold text-fg-2">
                                    {{ mb_substr($u->name, 0, 1) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-fg">
                                        {{ $u->name }}
                                        @if ($u->id === auth()->id())
                                            <span class="font-normal text-fg-3">(คุณ)</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-label text-fg-2">{{ $u->email }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-label">
                                <span @class([
                                    'inline-flex items-center rounded-control border px-2 py-0.5 font-semibold',
                                    'border-primary-ink text-primary-ink' => $u->role === 'admin',
                                    'border-fg text-fg' => $u->role === 'owner',
                                    'border-line text-fg-2' => $u->role === 'user',
                                ])>{{ Navigation::ROLE_LABELS[$u->role] ?? $u->role }}</span>
                                @if ($u->role === 'owner')
                                    <span class="text-fg-2">ลาน <span class="tabular">{{ $u->owned_parking_lots_count }}</span> แห่ง</span>
                                @endif
                                @if ($u->force_password_reset)
                                    <span class="text-warning">ต้องเปลี่ยนรหัสผ่าน</span>
                                @endif
                                @unless ($u->email_verified_at)
                                    <span class="text-warning">ยังไม่ยืนยันอีเมล</span>
                                @endunless
                            </div>

                            <div class="flex items-center gap-3 md:justify-end">
                                <span class="text-caption text-fg-3">แก้ไขล่าสุด {{ Format::short($u->updated_at) }}</span>
                                <x-ui.button variant="secondary" size="sm" :href="route('admin.users.edit', $u)">จัดการ</x-ui.button>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <x-ui.pagination :paginator="$users" class="mt-6" />
            @endif
        </div>
    </div>
</x-app-layout>
