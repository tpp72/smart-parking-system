{{--
    คำร้องลาออกของเจ้าของลาน — แท็บสถานะพร้อมจำนวน (เริ่มที่รอพิจารณา)
    รอพิจารณา: แสดงผลกระทบก่อนอนุมัติ (ลานที่จะถูกลบ / การจองที่จะถูกยกเลิก / รถที่จะถูก Check-out)
    · อนุมัติ (ยืนยันแบบอันตราย) · ไม่อนุมัติ (ต้องระบุเหตุผล ≥ 10 ตัวอักษร)
    ต้องการ: $resignations, $impact, $status, $counts
--}}
@use('App\Support\Format')

@php
    $tabs = ['pending' => 'รอพิจารณา', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ'];
@endphp

<x-app-layout flash-toast>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="mb-6">
            <h1 class="text-h1 text-fg">คำร้องลาออก</h1>
            <p class="mt-1 text-fg-2">
                เมื่ออนุมัติ: การจองที่ยังไม่ Check-in ถูกยกเลิก · รถที่จอดอยู่ถูก Check-out · ผู้จองได้รับแจ้งให้ติดต่อผู้ดูแลระบบ ·
                ลานจอดของเจ้าของถูกลบพร้อมข้อมูล · บัญชีกลับเป็นผู้ใช้
            </p>
        </div>

        @if ($errors->has('error'))
            <x-ui.alert tone="danger" class="mb-4">{{ $errors->first('error') }}</x-ui.alert>
        @endif

        <nav aria-label="กรองตามสถานะ" class="mb-4 flex gap-1 overflow-x-auto border-b border-line">
            @foreach ($tabs as $key => $label)
                @php $count = (int) ($counts[$key] ?? 0); @endphp
                <a href="{{ route('admin.owner-resignations.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-touch shrink-0 items-center gap-1.5 px-3 text-label transition-colors duration-fast',
                        'font-semibold text-fg shadow-[inset_0_-2px_0_rgb(var(--color-primary-ink))]' => $status === $key,
                        'text-fg-2 hover:text-fg' => $status !== $key,
                    ])>
                    {{ $label }}
                    <span @class(['tabular rounded-control px-1.5 text-caption', 'bg-warning/15 font-semibold text-fg' => $key === 'pending' && $count > 0, 'bg-surface-2 text-fg-2' => ! ($key === 'pending' && $count > 0)])>{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @if ($resignations->isEmpty())
            <div class="rounded-card border border-line bg-surface shadow-1">
                <x-ui.empty-state :title="$status === 'pending' ? 'ไม่มีคำร้องที่รอพิจารณา' : 'ไม่มีคำร้องในสถานะนี้'"
                    description="เจ้าของลานยื่นคำร้องลาออกได้จากหน้าภาพรวมของตัวเอง" />
            </div>
        @else
            <ol class="flex flex-col gap-4">
                @foreach ($resignations as $resignation)
                    <li class="rounded-card border border-line bg-surface p-5 shadow-1 sm:p-6"
                        @if ($resignation->isPending()) x-data="{ rejecting: {{ $errors->has('rejection_reason') && (int) old('resignation_id') === $resignation->id ? 'true' : 'false' }} }" @endif>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-h3 text-fg">{{ $resignation->user?->name ?? 'บัญชีถูกลบ' }}</h2>
                                <p class="text-label text-fg-2">{{ $resignation->user?->email }} · ยื่นเมื่อ {{ Format::short($resignation->created_at) }}</p>
                            </div>
                            <x-ui.status type="review" :value="$resignation->status" />
                        </div>

                        <div class="mt-4">
                            <p class="text-caption text-fg-3">เหตุผลในการลาออก</p>
                            <p class="mt-0.5 whitespace-pre-line text-fg">{{ $resignation->reason }}</p>
                        </div>

                        @if ($resignation->isPending())
                            @php $effect = $impact[$resignation->id]; @endphp
                            <div class="mt-4">
                                <p class="text-label font-semibold text-fg">ถ้าอนุมัติตอนนี้</p>
                                <dl class="mt-2 grid gap-px overflow-hidden rounded-control border border-line bg-line sm:grid-cols-3">
                                    @foreach ([['ลานจอดที่จะถูกลบ', $effect['lots'], 'แห่ง'], ['การจองที่จะถูกยกเลิก', $effect['bookings'], 'รายการ'], ['รถที่จะถูก Check-out', $effect['parked'], 'คัน']] as [$label, $value, $unit])
                                        <div @class(['px-4 py-3', 'bg-surface-2' => $value === 0, 'bg-danger/5' => $value > 0])>
                                            <dt class="text-caption text-fg-2">{{ $label }}</dt>
                                            <dd class="flex items-baseline gap-1"><span class="num text-h2 text-fg">{{ $value }}</span><span class="text-label text-fg-2">{{ $unit }}</span></dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3" x-show="! rejecting">
                                <form method="POST" action="{{ route('admin.owner-resignations.approve', $resignation) }}"
                                    data-confirm="อนุมัติคำร้องของ {{ $resignation->user?->name }} — ยกเลิกการจอง {{ $effect['bookings'] }} รายการ · Check-out รถ {{ $effect['parked'] }} คัน · ลบลานจอด {{ $effect['lots'] }} แห่ง และบัญชีกลับเป็นผู้ใช้ ย้อนกลับไม่ได้"
                                    data-confirm-title="อนุมัติคำร้องลาออก?" data-confirm-label="อนุมัติและลบลาน" data-confirm-tone="danger" data-confirm-cancel="ยังไม่อนุมัติ">
                                    @csrf
                                    <x-ui.button type="submit" variant="danger">อนุมัติคำร้อง</x-ui.button>
                                </form>
                                <x-ui.button variant="secondary" x-on:click="rejecting = true; $nextTick(() => $refs.reason.focus())">ไม่อนุมัติ…</x-ui.button>
                            </div>

                            <form method="POST" action="{{ route('admin.owner-resignations.reject', $resignation) }}" x-show="rejecting" x-cloak class="mt-5 flex flex-col gap-4">
                                @csrf
                                <input type="hidden" name="resignation_id" value="{{ $resignation->id }}">
                                <x-ui.field label="เหตุผลที่ไม่อนุมัติ" :for="'rejection_reason_'.$resignation->id" required hint="อย่างน้อย 10 ตัวอักษร เจ้าของลานจะเห็นเหตุผลนี้"
                                    :error="(int) old('resignation_id') === $resignation->id ? $errors->get('rejection_reason') : []">
                                    <x-ui.textarea :id="'rejection_reason_'.$resignation->id" name="rejection_reason" rows="3" minlength="10" maxlength="1000" required x-ref="reason">{{ (int) old('resignation_id') === $resignation->id ? old('rejection_reason') : '' }}</x-ui.textarea>
                                </x-ui.field>
                                <div class="flex flex-wrap gap-3">
                                    <x-ui.button type="submit">ยืนยันไม่อนุมัติ</x-ui.button>
                                    <x-ui.button variant="ghost" x-on:click="rejecting = false">กลับ</x-ui.button>
                                </div>
                            </form>
                        @elseif ($resignation->status === 'rejected')
                            <x-ui.alert tone="warning" class="mt-4" :title="'ไม่อนุมัติโดย '.($resignation->reviewer?->name ?? 'บัญชีถูกลบ').' · '.Format::short($resignation->reviewed_at)">
                                {{ $resignation->rejection_reason }}
                            </x-ui.alert>
                        @else
                            <p class="mt-4 rounded-control bg-surface-2 px-4 py-3 text-label text-fg-2">
                                อนุมัติโดย {{ $resignation->reviewer?->name ?? 'บัญชีถูกลบ' }} · {{ Format::short($resignation->reviewed_at) }} —
                                ยกเลิกการจอง <span class="tabular font-semibold text-fg">{{ $resignation->result['reservations_cancelled'] ?? 0 }}</span> รายการ ·
                                Check-out รถ <span class="tabular font-semibold text-fg">{{ $resignation->result['cars_checked_out'] ?? 0 }}</span> คัน ·
                                ลบลานจอด <span class="tabular font-semibold text-fg">{{ $resignation->result['lots_deleted'] ?? 0 }}</span> แห่ง
                            </p>
                        @endif
                    </li>
                @endforeach
            </ol>

            <x-ui.pagination :paginator="$resignations" class="mt-6" />
        @endif
    </div>
</x-app-layout>
