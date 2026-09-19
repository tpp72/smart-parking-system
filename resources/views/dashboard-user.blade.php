{{--
    หน้าหลักของ User — บัตรทุกใบที่ยังไม่จบก่อน (กำลังจอด → ใกล้หมดเวลาเช็คอิน) · ลานที่จองได้ตอนนี้ · ประวัติล่าสุด
--}}
@use('App\Support\Format')

<x-app-layout flash-toast>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">สวัสดี {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-fg-2">
                    @if ($tickets->isEmpty())
                        ยังไม่มีการจองหรือรถที่จอดอยู่
                    @else
                        การจองที่ยังไม่จบ <span class="num font-semibold text-fg">{{ $tickets->count() }}</span> รายการ
                    @endif
                </p>
            </div>
            @if ($tickets->isNotEmpty())
                <x-ui.button :href="route('user.reservations.create')">
                    <x-ui.icon name="plus" class="h-4 w-4" /> จองที่จอด
                </x-ui.button>
            @endif
        </div>

        {{-- ── บัตรที่ยังไม่จบ ──────────────────────────────────────── --}}
        <section aria-labelledby="tickets-title" class="mt-6">
            <h2 id="tickets-title" class="sr-only">การจองและรถที่จอดอยู่</h2>

            @forelse ($tickets as $reservation)
                @include('user.partials.reservation-ticket', ['reservation' => $reservation, 'estimate' => $estimates[$reservation->id] ?? null])
                @unless ($loop->last)<div class="h-4"></div>@endunless
            @empty
                <div class="rounded-card border border-line bg-surface shadow-1">
                    <x-ui.empty-state title="ยังไม่มีการจอง"
                        description="เลือกลานและเวลาเริ่มจอดล่วงหน้าได้ไม่เกิน 1 วัน มัดจำเท่ากับค่าจอด 1 ชั่วโมงของลาน">
                        <x-ui.button :href="route('user.reservations.create')">
                            <x-ui.icon name="plus" class="h-4 w-4" /> จองที่จอด
                        </x-ui.button>
                    </x-ui.empty-state>
                </div>
            @endforelse
        </section>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1.15fr_0.85fr]">
            {{-- ── ลานที่จองได้ตอนนี้ ───────────────────────────────────── --}}
            <section aria-labelledby="lots-title" class="min-w-0">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="lots-title" class="text-h2 text-fg">ลานที่จองได้ตอนนี้</h2>
                    <a href="{{ route('user.reservations.create') }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">เลือกจากทุกลาน</a>
                </div>
                <p class="mt-1 text-label text-fg-3">เปิดรับจองและยังมีช่องว่าง เรียงจากช่องว่างมากสุด</p>

                @if ($lotsAvailable->isEmpty())
                    <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ขณะนี้ไม่มีลานที่เปิดรับจองและมีช่องว่าง</p>
                @else
                    <ul class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        @foreach ($lotsAvailable as $lot)
                            <li>
                                <a href="{{ route('user.reservations.create', ['lot_id' => $lot->id]) }}"
                                    class="group flex min-h-touch items-center gap-4 px-4 py-3 transition-colors duration-fast hover:bg-surface-2 sm:px-5">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold text-fg">{{ $lot->name }}</p>
                                        <p class="text-label text-fg-2">
                                            <span class="num">{{ Format::baht($lot->hourly_rate) }}</span> / ชม.
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-end text-label text-fg-2">
                                        ว่าง <span class="num text-body font-semibold text-success">{{ (int) $lot->available }}</span> ช่อง
                                    </p>
                                    <span class="hidden shrink-0 text-label font-semibold text-primary-ink sm:inline">จองลานนี้</span>
                                    <x-ui.icon name="chevron-right" class="h-5 w-5 text-fg-3 transition-transform duration-fast group-hover:translate-x-0.5" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ── ประวัติล่าสุด ───────────────────────────────────────── --}}
            <section aria-labelledby="history-title" class="min-w-0">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="history-title" class="text-h2 text-fg">จอดล่าสุด</h2>
                    <a href="{{ route('user.parking-logs.index') }}" class="inline-flex min-h-touch items-center text-label font-semibold text-primary-ink underline-offset-4 hover:underline">ประวัติทั้งหมด</a>
                </div>
                <p class="mt-1 text-label text-fg-3">3 ครั้งล่าสุดที่ออกจากลานแล้ว</p>

                @if ($recentHistory->isEmpty())
                    <p class="mt-4 rounded-card border border-line bg-surface px-4 py-6 text-center text-fg-2">ยังไม่มีประวัติการจอด</p>
                @else
                    <ol class="mt-4 divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        @foreach ($recentHistory as $h)
                            <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                <x-ui.plate :plate="$h->license_plate" :province="$h->plate_province" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-label font-semibold text-fg">{{ $h->lot_name }}</p>
                                    <p class="text-caption text-fg-3">{{ Format::short($h->check_out_time) }}</p>
                                </div>
                                @if ($h->total_amount !== null)
                                    <div class="shrink-0 text-end">
                                        <p class="num text-label font-semibold text-fg">{{ Format::baht($h->total_amount) }}</p>
                                        <x-ui.status type="payment" :value="$h->payment_status" audience="user" />
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
