{{--
    ความแม่นยำของ AI เทียบเกณฑ์ (ผ่านเมื่อ "มากกว่า" เกณฑ์ — config carscan.accuracy_threshold)
    แถบความยาวคงที่ 0–100% มีขีดเกณฑ์ 1 เส้น · ผลบอกด้วยข้อความเสมอ ไม่ใช้สีอย่างเดียว
--}}
@props(['value' => null])

@php
    $threshold = (float) config('carscan.accuracy_threshold', 85);
    $known = $value !== null;
    $pct = $known ? max(0, min(100, (float) $value)) : 0;
    $passed = $known && $pct > $threshold;
@endphp

<div {{ $attributes->class('w-full') }}>
    <div class="flex items-baseline justify-between gap-3">
        <span class="text-caption text-fg-3">ความแม่นยำของ AI</span>
        <span class="text-label">
            <span class="num font-semibold text-fg">{{ $known ? number_format($pct, 1).'%' : 'ไม่ทราบค่า' }}</span>
            <span class="text-fg-2">· {{ $passed ? 'เกิน' : 'ไม่เกิน' }}เกณฑ์ <span class="num">{{ rtrim(rtrim(number_format($threshold, 1), '0'), '.') }}%</span></span>
        </span>
    </div>
    <div class="relative mt-2 h-2 rounded-full bg-line" role="img"
        aria-label="ความแม่นยำ {{ $known ? number_format($pct, 1).'%' : 'ไม่ทราบค่า' }} {{ $passed ? 'ผ่าน' : 'ไม่ผ่าน' }}เกณฑ์ {{ $threshold }}%">
        <div @class(['absolute inset-y-0 left-0 rounded-full', 'bg-success' => $passed, 'bg-warning' => ! $passed]) style="width: {{ $pct }}%"></div>
        <span class="absolute -inset-y-1 w-0.5 bg-fg" style="left: {{ $threshold }}%"></span>
    </div>
</div>
