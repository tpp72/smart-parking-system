<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ข้อมูลตั้งต้นของเว็บ demo — บัญชีทดลองต้องเข้าใช้ได้ทันทีหลัง seed ทุกครั้ง */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * seeder สุ่มผู้ใช้มาบังคับตั้งรหัสผ่านใหม่เพื่อให้มีข้อมูล Audit Log
     * ต้องไม่สุ่มโดนบัญชีทดลอง @demo.com ที่หน้าเข้าสู่ระบบแสดงให้ผู้ทดสอบใช้ และ E2E ใช้ล็อกอิน
     * รันซ้ำหลายรอบเพราะเป็นการสุ่ม — บั๊กเดิมโผล่ราว 1 ใน 9 รอบ
     */
    public function test_demo_accounts_are_never_forced_to_reset_their_password(): void
    {
        foreach (range(1, 3) as $round) {
            $this->artisan('migrate:fresh');
            $this->seed(DatabaseSeeder::class);

            $this->assertSame(0, User::where('email', 'like', '%@demo.com')->where('force_password_reset', true)->count(),
                "รอบที่ {$round}: บัญชีทดลองโดนบังคับตั้งรหัสผ่านใหม่");
            // ยังมีผู้ใช้อื่นโดนบังคับอยู่ — ข้อมูลตัวอย่างของ Audit Log ไม่หายไป
            $this->assertSame(3, User::where('force_password_reset', true)->count());
        }
    }
}
