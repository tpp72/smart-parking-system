{{--
    ป้ายทะเบียนรถยนต์ไทย: หมวด+เลขทะเบียนบน · จังหวัดล่าง (ทะเบียน + จังหวัด คือกุญแจจับคู่รถของระบบ)
    <x-ui.plate plate="กข 1234" province="กรุงเทพมหานคร" size="md" />
--}}
@props(['plate', 'province' => null, 'size' => 'md'])

@php
    [$box, $number, $prov] = match ($size) {
        'sm' => ['min-w-[6.5rem] px-2 py-0.5 border', 'text-body', 'text-micro'],
        'lg' => ['min-w-[11rem] px-4 py-1.5 border-2', 'text-h1', 'text-caption'],
        default => ['min-w-[8.5rem] px-3 py-1 border-2', 'text-h3', 'text-mini'],
    };
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 flex-col items-center justify-center rounded-control border-fg bg-surface text-center text-fg', $box]) }}>
    <span class="sr-only">ป้ายทะเบียน</span>
    <span class="{{ $number }} whitespace-nowrap font-bold leading-tight">{{ $plate }}</span>
    @if ($province)
        <span class="{{ $prov }} whitespace-nowrap font-medium leading-tight text-fg-2">{{ $province }}</span>
    @endif
</span>
