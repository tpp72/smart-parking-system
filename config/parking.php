<?php

return [
    /*
     | ช่วงเวลา Check-in หลัง reserve_start (นาที) — project-plan.md §7.2, §23
     | ต้อง Check-in ภายใน 1 ชั่วโมง (ครบ 60 นาทีพอดียังเช็คอินได้) เกินกว่านั้น Scheduler เปลี่ยนการจองเป็น expired
     | เป็น Business Rule คงที่ จึงไม่อ่านค่าจาก .env
     */
    'grace_period' => 60,
];
