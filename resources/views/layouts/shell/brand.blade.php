{{-- ชื่อระบบ + ตราประทับ "P" (ป้ายที่จอดรถ) · $compact = แสดงเฉพาะตรา (icon rail) · $nameFromSm = ชื่อระบบเห็นตั้งแต่จอ sm (หน้าสาธารณะที่มีปุ่มด้านขวามาก) --}}
@php($compact = $compact ?? false)
@php($nameFromSm = $nameFromSm ?? false)

<a href="{{ $nav['homeHref'] ?? url('/') }}"
    @class(['group flex min-h-touch items-center gap-2.5 rounded-card', 'justify-center' => $compact])
    @if ($compact) aria-label="Smart Parking System — หน้าหลัก" @endif>
    <span aria-hidden="true"
        class="num inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-primary text-[0.9375rem] font-bold text-on-primary">P</span>
    @unless ($compact)
        <span @class(['text-body font-semibold leading-tight text-fg', 'sr-only sm:not-sr-only' => $nameFromSm])>Smart Parking System</span>
    @endunless
</a>
