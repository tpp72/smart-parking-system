{{--
    ตราสถานะ (Status Stamp) — ข้อความไทย + รูปทรง + สีหมึก จาก App\Support\StatusCatalog เท่านั้น
    <x-ui.status type="reservation" :value="$reservation->status" audience="user" />
--}}
@props(['type', 'value' => null, 'audience' => 'staff', 'size' => 'md'])

@php
    $status = \App\Support\StatusCatalog::resolve($type, $value, $audience);

    $tones = [
        'neutral' => 'text-fg-2',
        'info'    => 'text-primary-ink',
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger'  => 'text-danger',
    ];

    // รูปทรงบอกความหมายโดยไม่พึ่งสี (viewBox 16×16, เส้น currentColor)
    $shapes = [
        'pending'   => '<circle cx="8" cy="8" r="5.75"/><path d="M8 5v3.25l2.25 1.5"/>',
        'confirmed' => '<rect x="2.75" y="2.75" width="10.5" height="10.5" rx="1"/><path d="M5.5 8.25l1.75 1.75L10.75 6.5"/>',
        'active'    => '<circle cx="8" cy="8" r="5.75"/><circle cx="8" cy="8" r="2.5" fill="currentColor" stroke="none"/>',
        'done'      => '<path d="M3.25 8.5l3 3 6.5-6.75"/>',
        'cancelled' => '<path d="M4.25 4.25l7.5 7.5M11.75 4.25l-7.5 7.5"/>',
        'expired'   => '<path d="M4.5 2.75h7M4.5 13.25h7M5.5 2.75v1.5L8 8l2.5-3.75v-1.5M5.5 13.25v-1.5L8 8l2.5 3.75v1.5"/>',
        'void'      => '<circle cx="8" cy="8" r="5.75"/><path d="M3.9 12.1l8.2-8.2"/>',
        'alert'     => '<path d="M8 2.75l5.75 10.5H2.25z"/><path d="M8 6.75v2.75M8 11.5v.01"/>',
        'available' => '<rect x="3" y="3" width="10" height="10" rx="1"/>',
        'reserved'  => '<rect x="3" y="3" width="10" height="10" rx="1"/><path d="M3 13L13 3"/>',
        'occupied'  => '<rect x="3" y="3" width="10" height="10" rx="1" fill="currentColor"/>',
        'unknown'   => '<path d="M4 8h8"/>',
    ];

    $sizeClass = $size === 'sm' ? 'min-h-6 px-1.5 text-caption' : 'min-h-7 px-2 text-label';
@endphp

<span data-status-type="{{ $type }}" data-status="{{ $status['key'] }}"
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-control border border-current font-semibold leading-none',
        $tones[$status['tone']] ?? $tones['neutral'],
        $sizeClass,
    ]) }}>
    <svg class="{{ $size === 'sm' ? 'h-3 w-3' : 'h-3.5 w-3.5' }} shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor"
        stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $shapes[$status['shape']] ?? $shapes['unknown'] !!}</svg>
    <span>{{ $status['label'] }}</span>
</span>
