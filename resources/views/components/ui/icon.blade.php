{{--
    ไอคอนเส้นชุดเดียวของระบบ (20×20, เส้น 1.6) — ตกแต่งเท่านั้น (aria-hidden) ข้อความต้องอยู่คู่ไอคอนเสมอ
    <x-ui.icon name="ticket" class="h-5 w-5" />
--}}
@props(['name'])

@php
    $paths = [
        'dashboard'       => '<rect x="3.5" y="3.5" width="5.5" height="6.5" rx="1"/><rect x="11" y="3.5" width="5.5" height="4" rx="1"/><rect x="11" y="9.5" width="5.5" height="7" rx="1"/><rect x="3.5" y="12" width="5.5" height="4.5" rx="1"/>',
        'home'            => '<path d="M3.5 9 10 3.75 16.5 9v7.25h-4.25V12h-4.5v4.25H3.5z"/>',
        'plus'            => '<path d="M10 4.5v11M4.5 10h11"/>',
        'ticket'          => '<path d="M3 5.75h14v2.5a1.75 1.75 0 0 0 0 3.5v2.5H3v-2.5a1.75 1.75 0 0 0 0-3.5z"/><path d="M12.5 6.25v1.5M12.5 9.25v1.5M12.5 12.25v1.5"/>',
        'scan'            => '<path d="M3.5 7V4.5a1 1 0 0 1 1-1H7M13 3.5h2.5a1 1 0 0 1 1 1V7M16.5 13v2.5a1 1 0 0 1-1 1H13M7 16.5H4.5a1 1 0 0 1-1-1V13M6.5 10h7"/>',
        'scan-history'    => '<path d="M3.5 7V4.5a1 1 0 0 1 1-1H7M13 3.5h2.5a1 1 0 0 1 1 1V7M16.5 13v2.5a1 1 0 0 1-1 1H13M7 16.5H4.5a1 1 0 0 1-1-1V13"/><path d="M7 7.75h6M7 10h6M7 12.25h3.5"/>',
        'lot'             => '<rect x="3.5" y="3.5" width="13" height="13" rx="1.5"/><path d="M8 14V6.5h2.75a2.25 2.25 0 0 1 0 4.5H8"/>',
        'slots'           => '<path d="M3.5 4v12M10 4v12M16.5 4v12M3.5 10h13"/>',
        'payment'         => '<rect x="2.75" y="5.5" width="14.5" height="9" rx="1"/><circle cx="10" cy="10" r="2"/><path d="M5.5 8v4M14.5 8v4"/>',
        'revenue'         => '<path d="M3.5 16.5h13M5.5 13.5 9 10l2.5 2.5 4.5-5"/><path d="M12.5 7.5H16V11"/>',
        'users'           => '<circle cx="7.5" cy="7" r="2.75"/><path d="M2.75 16.25c.5-2.75 2.4-4.25 4.75-4.25s4.25 1.5 4.75 4.25M13 4.5a2.5 2.5 0 0 1 0 5M14.5 12.25c1.5.5 2.5 1.9 2.75 4"/>',
        'application'     => '<path d="M5 2.75h6.5l3.5 3.5v11H5z"/><path d="M11.5 2.75v3.5H15M7.75 12l1.75 1.75 3-3.25"/>',
        'resignation'     => '<path d="M11.5 3.5H5v13h6.5"/><path d="M9 10h8M14.5 7.5 17 10l-2.5 2.5"/>',
        'blacklist'       => '<path d="M10 2.75 16 5v4.75c0 3.5-2.5 6.25-6 7.5-3.5-1.25-6-4-6-7.5V5z"/><path d="m7.25 7.25 5.5 5.5"/>',
        'parking-log'     => '<path d="M7 5.5h9.5M7 10h9.5M7 14.5h9.5M3.5 5.5h.5M3.5 10h.5M3.5 14.5h.5"/>',
        'reservation-log' => '<path d="M5 2.75h10v14.5l-1.75-1.25-1.75 1.25-1.75-1.25-1.75 1.25-1.75-1.25L5 17.25z"/><path d="M7.5 6.5h5M7.5 9.5h5M7.5 12.5h3"/>',
        'audit'           => '<path d="M9 16.75H4.25V2.75h9.5V8"/><path d="M6.75 6h4.5M6.75 9h2.5"/><circle cx="13.25" cy="13.25" r="2.5"/><path d="m15.1 15.1 2.15 2.15"/>',
        'export'          => '<path d="M10 3.5v9M6.5 9 10 12.5 13.5 9M3.5 13.5v3h13v-3"/>',
        'bell'            => '<path d="M5.25 13.75V9a4.75 4.75 0 0 1 9.5 0v4.75l1.25 1.5H4z"/><path d="M8.25 17.25a1.9 1.9 0 0 0 3.5 0"/>',
        'user'            => '<circle cx="10" cy="7" r="3.25"/><path d="M3.75 17c.6-3.25 3.1-5 6.25-5s5.65 1.75 6.25 5"/>',
        'store'           => '<path d="M4 8.5v8h12v-8M2.75 8.5h14.5L15.5 3.5h-11z"/><path d="M8 16.5v-4h4v4"/>',
        'logout'          => '<path d="M8 3.5H4v13h4"/><path d="M8.5 10H17M14 7l3 3-3 3"/>',
        'menu'            => '<path d="M3.5 5.5h13M3.5 10h13M3.5 14.5h13"/>',
        'chevron-down'    => '<path d="m6 8 4 4 4-4"/>',
        'chevron-right'   => '<path d="m8 6 4 4-4 4"/>',
    ];
@endphp

<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
    stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }}>{!! $paths[$name] ?? '' !!}</svg>
