{{--
    ประวัติการจอด — 1 ครั้งที่จอด = ใบเสร็จ 1 ใบ: เวลาเข้า-ออก · ค่าจอดตามชั่วโมง · หักมัดจำ · ส่วนลดการจอง · ยอดชำระ
    (project-plan.md §5.3 Parking History)
--}}
@use('App\Support\Format')

<x-app-layout>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6">
            <h1 class="text-h1 text-fg">ประวัติการจอด</h1>
            <p class="mt-1 text-fg-2">เวลาเข้า-ออก ค่าจอด และยอดที่ชำระของทุกครั้งที่จอด</p>
        </div>

        @if ($logs->isEmpty())
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state title="ยังไม่มีประวัติการจอด" description="เมื่อรถ Check-in และ Check-out แล้ว ใบเสร็จค่าจอดจะแสดงที่นี่" />
            </div>
        @else
            <ol class="flex flex-col gap-4">
                @foreach ($logs as $log)
                    @php
                        $in = Format::parse($log->check_in_time);
                        $out = Format::parse($log->check_out_time);
                        $parked = $out === null;
                    @endphp
                    <li class="overflow-hidden rounded-card border border-line bg-surface shadow-1">
                        <article aria-labelledby="log-{{ $log->log_id }}" class="grid sm:grid-cols-[1fr_17rem]">
                            <div class="p-4 sm:p-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <x-ui.plate :plate="$log->license_plate" :province="$log->plate_province" size="sm" />
                                    <div class="min-w-0">
                                        <h2 id="log-{{ $log->log_id }}" class="font-semibold text-fg">{{ $log->lot_name }}</h2>
                                        <p class="text-label text-fg-2">
                                            @if ($log->slot_number)
                                                ช่อง <span class="num">{{ $log->slot_number }}</span>
                                            @endif
                                            @if ($log->is_walk_in)
                                                · เข้าแบบ Walk-in
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-label sm:grid-cols-3">
                                    <div>
                                        <dt class="text-caption text-fg-3">เข้าลาน</dt>
                                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::short($in) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-caption text-fg-3">ออกจากลาน</dt>
                                        <dd class="mt-0.5 font-semibold text-fg">{{ $parked ? 'ยังจอดอยู่' : Format::short($out) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-caption text-fg-3">{{ $parked ? 'จอดมาแล้ว' : 'เวลาจอด' }}</dt>
                                        <dd class="mt-0.5 font-semibold text-fg">{{ Format::duration((int) $in->diffInMinutes($out ?? now())) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            {{-- ใบเสร็จ --}}
                            <div class="border-t border-dashed border-field bg-surface-2/60 p-4 text-label sm:border-l sm:border-t-0 sm:p-5">
                                @if ($log->total_amount !== null)
                                    <dl class="flex flex-col gap-1.5">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-fg-2">ค่าจอด <span class="num">{{ (int) $log->total_hours }}</span> ชม.@if ($log->hourly_rate) × <span class="num">{{ Format::baht($log->hourly_rate) }}</span>@endif</dt>
                                            <dd class="num text-fg">{{ Format::baht($log->parking_fee) }}</dd>
                                        </div>
                                        @if ((float) $log->deposit_deduction > 0)
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-fg-2">หักมัดจำ</dt>
                                                <dd class="num text-fg">−{{ Format::baht($log->deposit_deduction) }}</dd>
                                            </div>
                                        @endif
                                        @if ((float) $log->reservation_discount > 0)
                                            <div class="flex justify-between gap-3">
                                                <dt class="text-fg-2">ส่วนลดการจอง</dt>
                                                <dd class="num text-fg">−{{ Format::baht($log->reservation_discount) }}</dd>
                                            </div>
                                        @endif
                                        <div class="mt-1 flex justify-between gap-3 border-t border-line pt-2 font-semibold">
                                            <dt class="text-fg">ยอดชำระ</dt>
                                            <dd class="num text-h3 text-fg">{{ Format::baht($log->total_amount) }}</dd>
                                        </div>
                                    </dl>
                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                        <x-ui.status type="payment" :value="$log->payment_status" audience="user" />
                                        @if ($log->payment_status === 'unpaid')
                                            <span class="text-caption text-fg-3">รอเจ้าหน้าที่ยืนยันรับเงิน</span>
                                        @endif
                                    </div>
                                @else
                                    <x-ui.status type="reservation" value="checked_in" audience="user" />
                                    <p class="mt-2 text-fg-2">ค่าจอดคิดตอน Check-out ตามจำนวนชั่วโมงที่จอดจริง (ปัดขึ้น ขั้นต่ำ 1 ชั่วโมง)</p>
                                @endif
                            </div>
                        </article>
                    </li>
                @endforeach
            </ol>

            <x-ui.pagination :paginator="$logs" class="mt-6" />
        @endif
    </div>
</x-app-layout>
