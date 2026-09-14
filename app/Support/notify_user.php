<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

if (! function_exists('notify_user')) {
    /**
     * สร้าง Notification ภายในเว็บให้ User (สถานะเริ่มต้น = ยังไม่อ่าน) — project-plan.md §15
     *
     * @param int    $userId   user ที่จะได้รับ notification
     * @param string $title    หัวข้อ
     * @param string $message  เนื้อหา
     */
    function notify_user(int $userId, string $title, string $message): void
    {
        try {
            // บัญชีระบบ (เช่น Walkin User) ไม่ได้รับ Notification
            if (User::whereKey($userId)->where('is_system', true)->exists()) {
                return;
            }

            Notification::create([
                'user_id' => $userId,
                'title'   => $title,
                'message' => $message,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // การแจ้งเตือนต้องไม่ทำให้รายการหลักล้มเหลว — เก็บข้อผิดพลาดไว้ใน Application Log
            Log::error("[Notification] {$title} was not sent: {$e->getMessage()}");
        }
    }
}
