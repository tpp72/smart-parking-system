{{--
    สีรถ: จุดสีตัวอย่าง + ชื่อสี (ข้อความคือข้อมูลหลัก จุดสีช่วยอ่านเร็วเท่านั้น)
    ค่าสีเป็นสีของตัวรถจริง ไม่ใช่สีของระบบ — ชื่อสีตรงกับ config/car_colors.php
--}}
@props(['color' => null])

@php
    $swatches = [
        'ขาว' => '#F4F5F2', 'ดำ' => '#1B1C1E', 'เทา' => '#8A8F94', 'เงิน' => '#C3C7CA', 'ทอง' => '#C2A367',
        'น้ำตาล' => '#7A4E2D', 'แดง' => '#C62F2A', 'ส้ม' => '#E3772B', 'เหลือง' => '#E4BD2F', 'เขียว' => '#2F8A4E',
        'น้ำเงิน' => '#2F56B0', 'ม่วง' => '#6F3FA0', 'ชมพู' => '#D8618F',
    ];
    $hex = $swatches[trim((string) $color)] ?? null;
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5') }}>
    @if ($hex)
        <span aria-hidden="true" class="h-3.5 w-3.5 shrink-0 rounded-full border border-field" style="background: {{ $hex }}"></span>
    @endif
    <span>{{ filled($color) ? $color : 'ไม่ระบุ' }}</span>
</span>
