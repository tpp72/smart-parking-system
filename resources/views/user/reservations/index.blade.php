{{--
    การจองของฉัน — แท็บ "กำลังดำเนินการ" (บัตรเต็มพร้อมแถบเวลาเช็คอินและปุ่มแก้ไข/ยกเลิก)
    และ "จบแล้ว" (รายการย่อ: เสร็จสิ้น / ยกเลิก / หมดอายุ)
--}}
@use('App\Support\Format')

<x-app-layout flash-toast>
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-h1 text-fg">การจองของฉัน</h1>
                <p class="mt-1 text-fg-2">แก้ไขข้อมูลรถหรือยกเลิกได้จนกว่ารถจะ Check-in</p>
            </div>
            <x-ui.button :href="route('user.reservations.create')">
                <x-ui.icon name="plus" class="h-4 w-4" /> จองที่จอด
            </x-ui.button>
        </div>

        @if ($errors->has('error'))
            <x-ui.alert tone="danger" class="mt-6">{{ $errors->first('error') }}</x-ui.alert>
        @endif

        <nav aria-label="กลุ่มการจอง" class="mt-6 flex gap-1 border-b border-line">
            @foreach (['active' => 'กำลังดำเนินการ', 'done' => 'จบแล้ว'] as $key => $label)
                <a href="{{ route('user.reservations.index', $key === 'active' ? [] : ['tab' => $key]) }}"
                    @if ($tab === $key) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-touch items-center gap-2 px-3 text-label transition-colors duration-fast',
                        'font-semibold text-fg shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => $tab === $key,
                        'text-fg-2 hover:text-fg' => $tab !== $key,
                    ])>
                    {{ $label }}
                    <span class="num rounded-control bg-surface-2 px-1.5 text-caption text-fg-2">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-6">
            <h2 class="sr-only">{{ $tab === 'active' ? 'การจองที่กำลังดำเนินการ' : 'การจองที่จบแล้ว' }}</h2>
            @if ($reservations->isEmpty())
                <div class="rounded-card border border-line bg-surface shadow-1">
                    @if ($tab === 'active')
                        <x-ui.empty-state title="ไม่มีการจองที่กำลังดำเนินการ" description="การจองที่รอยืนยันรับมัดจำ ยืนยันแล้ว หรือรถที่จอดอยู่จะแสดงที่นี่">
                            <x-ui.button :href="route('user.reservations.create')">จองที่จอด</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state title="ยังไม่มีการจองที่จบแล้ว" description="การจองที่เสร็จสิ้น ถูกยกเลิก หรือหมดอายุจะย้ายมาอยู่ที่นี่" />
                    @endif
                </div>
            @elseif ($tab === 'active')
                <div class="flex flex-col gap-4">
                    @foreach ($reservations as $reservation)
                        @include('user.partials.reservation-ticket', ['reservation' => $reservation, 'estimate' => $estimates[$reservation->id] ?? null])
                    @endforeach
                </div>
            @else
                <ol class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface shadow-1">
                    @foreach ($reservations as $r)
                        @php
                            $checkout = $r->parkingLog?->payment;
                            $depositState = $r->depositPayment?->payment_status;
                        @endphp
                        <li class="grid gap-3 px-4 py-4 sm:grid-cols-[auto_1fr_auto] sm:items-center sm:gap-5 sm:px-5">
                            <x-ui.plate :plate="$r->license_plate" :province="$r->plate_province" size="sm" />

                            <div class="min-w-0">
                                <p class="font-semibold text-fg">
                                    {{ $r->parkingLot?->name ?? 'ลานจอด' }}
                                    @if ($r->parkingSlot)
                                        <span class="font-normal text-fg-2">· ช่อง <span class="tabular">{{ $r->parkingSlot->slot_number }}</span></span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-label text-fg-2">
                                    <span class="tabular text-fg-3">#{{ $r->id }}</span> · เริ่มจอง {{ Format::short($r->reserve_start) }}
                                    @if ((float) $r->deposit_amount > 0)
                                        · มัดจำ <span class="tabular">{{ Format::baht($r->deposit_amount) }}</span>
                                        @if ($depositState === 'paid')
                                            ({{ $r->status === 'completed' ? 'หักจากค่าจอดแล้ว' : 'ชำระแล้ว ไม่คืน' }})
                                        @elseif ($depositState === 'void')
                                            (ยกเลิก ไม่ต้องชำระ)
                                        @endif
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                                <x-ui.status type="reservation" :value="$r->status" audience="user" />
                                @if ($checkout)
                                    <p class="text-label text-fg-2">
                                        ค่าจอด <span class="tabular font-semibold text-fg">{{ Format::baht($checkout->total_amount) }}</span>
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            <x-ui.pagination :paginator="$reservations" class="mt-6" />
        </div>
    </div>
</x-app-layout>
