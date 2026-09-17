{{--
    แถบช่วงเวลาเช็คอิน (ความยาวคงที่ + ตัวบอก "ตอนนี้" ตัวเดียว)
    เริ่มที่ reserve_start ยาว grace period (60 นาที) — มาก่อนเวลาระบบยังไม่ Check-in ให้ · เลยช่วงนี้การจองหมดอายุ
    อัปเดตตัวบอกตำแหน่งทุก 30 วินาที (ค่าเริ่มต้นคำนวณจากเซิร์ฟเวอร์ ใช้ได้แม้ไม่มี JS)
--}}
@props(['start', 'minutes' => \App\Models\Reservation::gracePeriodMinutes()])

@php
    $startAt = \App\Support\Format::parse($start);
    $endAt = $startAt->copy()->addMinutes($minutes);
    $startMs = $startAt->getTimestampMs();
    $endMs = $endAt->getTimestampMs();
    $id = 'rail-'.\Illuminate\Support\Str::random(6);
@endphp

<div x-data="{
        start: {{ $startMs }}, end: {{ $endMs }}, now: {{ now()->getTimestampMs() }},
        timer: null,
        init() { this.timer = setInterval(() => this.now = Date.now(), 30000); },
        destroy() { clearInterval(this.timer); },
        get phase() { return this.now < this.start ? 'before' : (this.now <= this.end ? 'open' : 'after'); },
        get pct() { return Math.min(100, Math.max(0, (this.now - this.start) / (this.end - this.start) * 100)); },
        get minutesLeft() { return Math.max(0, Math.ceil(((this.phase === 'before' ? this.start : this.end) - this.now) / 60000)); },
        get note() {
            const m = this.minutesLeft, h = Math.floor(m / 60), r = m % 60;
            const span = (h ? h + ' ชม. ' : '') + (r || !h ? r + ' นาที' : '');
            if (this.phase === 'before') return 'อีก ' + span + ' ถึงเวลาเริ่ม';
            if (this.phase === 'open') return 'เหลือเวลาเช็คอิน ' + span;
            return 'เลยช่วงเวลาเช็คอินแล้ว';
        }
    }"
    {{ $attributes->class('w-full') }}>
    <div class="flex items-baseline justify-between gap-3 text-caption text-fg-3">
        <span>เริ่ม <span class="num text-fg-2">{{ $startAt->format('H:i') }}</span></span>
        <span id="{{ $id }}-note" class="font-semibold text-fg" x-text="note">
            @if (now()->lt($startAt))
                อีก {{ \App\Support\Format::duration((int) ceil(now()->diffInMinutes($startAt))) }} ถึงเวลาเริ่ม
            @elseif (now()->lte($endAt))
                เหลือเวลาเช็คอิน {{ \App\Support\Format::duration((int) ceil(now()->diffInMinutes($endAt))) }}
            @else
                เลยช่วงเวลาเช็คอินแล้ว
            @endif
        </span>
        <span>ถึง <span class="num text-fg-2">{{ $endAt->format('H:i') }}</span></span>
    </div>

    @php($pct = now()->lt($startAt) ? 0 : min(100, now()->diffInSeconds($startAt, true) / max(1, $minutes * 60) * 100))
    <div class="relative mt-2 h-5" role="img" aria-labelledby="{{ $id }}-note">
        {{-- เส้นช่วงเวลา + ขีดทุก 15 นาที --}}
        <div class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-line"></div>
        <div class="absolute left-0 top-1/2 h-0.5 -translate-y-1/2 bg-fg-2" style="width: {{ $pct }}%" x-bind:style="`width: ${pct}%`"></div>
        @for ($tick = 0; $tick <= $minutes; $tick += 15)
            <span class="absolute top-1/2 h-2.5 w-px -translate-y-1/2 bg-field" style="left: {{ $tick / $minutes * 100 }}%"></span>
        @endfor
        {{-- ตอนนี้ --}}
        <span class="absolute top-0 flex h-5 -translate-x-1/2 flex-col items-center" style="left: {{ $pct }}%" x-bind:style="`left: ${pct}%`">
            <span class="h-5 w-0.5 rounded-full bg-primary-ink"></span>
        </span>
    </div>
</div>
