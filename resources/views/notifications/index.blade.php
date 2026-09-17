{{-- การแจ้งเตือนในเว็บ — สถานะ ยังไม่อ่าน / อ่านแล้ว (project-plan.md §15) · เรียงยังไม่อ่านก่อน แล้วใหม่สุด --}}
<x-app-layout flash-toast>
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">การแจ้งเตือน</h1>
                <p class="mt-1 text-fg-2">
                    @if ($unreadCount > 0)
                        ยังไม่อ่าน <span class="num font-semibold text-fg">{{ $unreadCount }}</span> รายการ
                    @else
                        อ่านครบทุกรายการแล้ว
                    @endif
                </p>
            </div>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm">ทำเครื่องหมายว่าอ่านทั้งหมด</x-ui.button>
                </form>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state title="ยังไม่มีการแจ้งเตือน"
                    description="ระบบจะแจ้งที่นี่เมื่อการจองได้รับการยืนยัน ถูกยกเลิก หมดอายุ หรือรถ Check-in / Check-out" />
            </div>
        @else
            <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                @foreach ($notifications as $n)
                    <li @class(['relative flex gap-3 px-4 py-4 sm:gap-4 sm:px-5', 'bg-primary/5' => ! $n->is_read])>
                        {{-- สถานะ: รูปทรง + ข้อความ (ไม่ใช้สีอย่างเดียว) --}}
                        <span aria-hidden="true" @class([
                            'mt-2 h-2.5 w-2.5 shrink-0 rounded-full',
                            'bg-primary-ink' => ! $n->is_read,
                            'border border-field' => $n->is_read,
                        ])></span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <h2 @class(['text-body text-fg', 'font-semibold' => ! $n->is_read])>
                                    <span class="sr-only">{{ $n->is_read ? 'อ่านแล้ว:' : 'ยังไม่อ่าน:' }}</span>
                                    {{ $n->title }}
                                </h2>
                                <time datetime="{{ $n->created_at->toIso8601String() }}"
                                    title="{{ $n->created_at->format('d/m/Y H:i') }} น."
                                    class="shrink-0 text-caption text-fg-3">
                                    {{ $n->created_at->diffForHumans() }}
                                </time>
                            </div>
                            <p class="mt-1 text-fg-2">{{ $n->message }}</p>

                            {{-- มือถือ: ปุ่มอยู่ใต้ข้อความ ไม่เบียดเนื้อหา --}}
                            @unless ($n->is_read)
                                <form method="POST" action="{{ route('notifications.read', $n) }}" class="-ml-3 mt-1 sm:hidden">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost" size="sm">อ่านแล้ว</x-ui.button>
                                </form>
                            @endunless
                        </div>

                        @unless ($n->is_read)
                            <form method="POST" action="{{ route('notifications.read', $n) }}" class="hidden shrink-0 self-center sm:block">
                                @csrf
                                <x-ui.button type="submit" variant="ghost" size="sm">อ่านแล้ว</x-ui.button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ol>

            <div class="mt-6">
                <x-ui.pagination :paginator="$notifications" />
            </div>
        @endif
    </div>
</x-app-layout>
