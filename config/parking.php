<?php

return [
    /*
     | Minutes after reserve_start that check-in is still allowed.
     | After this window the scheduler auto-expires the reservation.
     | Set RESERVATION_GRACE_PERIOD in .env to override.
     */
    'grace_period' => (int) env('RESERVATION_GRACE_PERIOD', 30),

    /*
     | ลานจอดที่ใช้เป็นปลายทางสำหรับ Auto Check-in ของรถที่ไม่ได้จองล่วงหน้า
     | (สแกนป้ายทะเบียนแล้วหา reservation ไม่เจอ) — ผูกกับลานเดียวตายตัว
     | เพราะระบบไม่รู้ว่ากล้อง/ผู้สแกนอยู่ที่ลานไหนจริง
     | Set WALKIN_LOT_ID ใน .env เป็น id ของลานที่ต้องการ (null = ปิดฟีเจอร์นี้)
     */
    'walkin_lot_id' => env('WALKIN_LOT_ID') ? (int) env('WALKIN_LOT_ID') : null,
];
