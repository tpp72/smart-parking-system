<?php

use App\Models\Notification;

if (! function_exists('notify_user')) {
    /**
     * สร้าง notification ให้ user
     *
     * @param int    $userId   user ที่จะได้รับ notification
     * @param string $title    หัวข้อ
     * @param string $message  เนื้อหา
     */
    function notify_user(int $userId, string $title, string $message): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'title'   => $title,
                'message' => $message,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // ไม่ให้ notification ทำเว็บล่ม
        }
    }
}
