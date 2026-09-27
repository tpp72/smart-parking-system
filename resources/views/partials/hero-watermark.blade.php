{{--
    ลายน้ำโลโก้สำหรับส่วนหัวของหน้าสาธารณะ — ตกแต่งล้วน (aria-hidden) ต้องวางในกล่องที่เป็น relative + overflow-hidden

    ใช้ mask ไม่ใช่ <img> เพราะไฟล์โลโก้เป็นตัวอักษรสีขาวบนพื้นใส วางตรง ๆ แล้วธีมสว่างจะมองไม่เห็น
    วิธีนี้รูปกลายเป็นเงาสีเดียวที่เติมด้วย token สีตัวอักษร จึงสลับตามธีมเองทุกกรณี รวมถึงตอนปิด JavaScript

    ไฟล์ที่ใช้คือ brand-logo-mask.png — ย่อจาก brand-logo.png แล้วเก็บเฉพาะช่องความโปร่งใส
    (mask ไม่ใช้ค่าสีเลย) ได้ภาพเหมือนกันทุกพิกเซลแต่เล็กลงจาก 519 KB เหลือ 83 KB
--}}
@props(['height' => 'lg'])

@php
    $size = $height === 'sm'
        ? 'w-[26rem] sm:w-[34rem] lg:w-[44rem]'   // แถบหัวหน้าเตี้ย ๆ
        : 'w-[42rem] sm:w-[56rem] lg:w-[76rem]';  // Hero เต็มหน้า
    $mask = "url('".asset('brand-logo-mask.png')."') center / contain no-repeat";
@endphp

<div aria-hidden="true"
    class="pointer-events-none absolute -z-10 bg-fg opacity-[0.06] left-1/2 top-1/2 aspect-[1774/887] -translate-x-1/2 -translate-y-1/2 {{ $size }}"
    style="-webkit-mask: {{ $mask }}; mask: {{ $mask }};"></div>
