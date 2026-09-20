{{--
    ตัวเลขงานค้างบนเมนู — แสดงเมื่อ count > 0 · ตัวเลขซ่อนจาก screen reader แล้วอ่าน label เต็มแทน
    <x-ui.count-badge :count="3" label="ค้างชำระ 3 รายการ" />
--}}
@props(['count' => 0, 'label' => null])

@if ($count > 0)
    <span {{ $attributes->merge(['class' => 'num inline-flex h-5 min-w-5 items-center justify-center rounded-control bg-primary px-1 text-mini font-semibold leading-none text-on-primary']) }}>
        <span aria-hidden="true">{{ $count > 99 ? '99+' : $count }}</span>
        @if ($label)
            <span class="sr-only">({{ $label }})</span>
        @endif
    </span>
@endif
