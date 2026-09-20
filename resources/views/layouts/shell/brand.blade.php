{{--
    ชื่อระบบ + ตราประทับ "P" (ป้ายที่จอดรถ) · $compact = แสดงเฉพาะตรา (icon rail) · $nameFromSm = ชื่อระบบเห็นตั้งแต่จอ sm (หน้าสาธารณะที่มีปุ่มด้านขวามาก)
    ถ้าตั้ง BRAND_LOGO ใน .env (path จาก public เช่น images/brand-logo.svg) จะใช้ไฟล์นั้นแทนตรา "P"
--}}
@php
    $compact = $compact ?? false;
    $nameFromSm = $nameFromSm ?? false;
    $brandName = config('brand.name');
    $brandLogo = config('brand.logo');
    $logoHeight = config('brand.logo_height');
@endphp

<a href="{{ $nav['homeHref'] ?? url('/') }}"
    @class(['group flex min-h-touch items-center gap-2.5 rounded-card', 'justify-center' => $compact])
    @if ($compact) aria-label="{{ $brandName }} — หน้าหลัก" @endif>
    @if ($brandLogo)
        <img src="{{ asset($brandLogo) }}" alt="{{ $compact ? $brandName : '' }}" height="{{ $logoHeight }}"
            class="w-auto shrink-0" style="height: {{ $logoHeight }}px">
    @else
        <span aria-hidden="true"
            class="num inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-primary text-body font-bold text-on-primary">P</span>
    @endif
    @unless ($compact)
        <span @class(['text-body font-semibold leading-tight text-fg', 'sr-only sm:not-sr-only' => $nameFromSm])>{{ $brandName }}</span>
    @endunless
</a>
