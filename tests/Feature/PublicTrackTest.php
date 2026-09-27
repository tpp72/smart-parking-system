<?php

namespace Tests\Feature;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * หน้าเช็คสถานะรถโดยไม่ต้องล็อกอิน — ยืนยันตัวด้วย ทะเบียน + จังหวัด + รหัสอ้างอิง
 * รหัสเป็นหลักฐานว่าคนที่ถืออยู่กับรถจริง ถ้าใช้แค่ทะเบียนจะตามดูรถคนอื่นได้
 */
class PublicTrackTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINCE = 'กรุงเทพมหานคร';

    /** รถที่กำลังจอดอยู่ 1 คัน พร้อมรหัสอ้างอิง */
    private function parkedCar(array $overrides = []): Reservation
    {
        $lot = ParkingLot::factory()->create(['hourly_rate' => 20]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'A007', 'status' => 'occupied']);

        $reservation = Reservation::factory()->create(array_merge([
            'user_id'         => User::walkin()->id,
            'is_walk_in'      => true,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'license_plate'   => 'กข 1234',
            'plate_province'  => self::PROVINCE,
            'status'          => 'checked_in',
            'deposit_amount'  => 0,
            'reservation_fee' => 0,
            'reserve_start'   => now()->subHours(3),
            'reference_code'  => 'ABC234',
        ], $overrides));

        ParkingLog::factory()->create([
            'reservation_id' => $reservation->id,
            'parking_lot_id' => $lot->id,
            'parking_slot_id' => $slot->id,
            'license_plate'  => $reservation->license_plate,
            'plate_province' => $reservation->plate_province,
            'hourly_rate'    => 20,
            'check_in_time'  => now()->subHours(3),
            'check_out_time' => null,
        ]);

        return $reservation->fresh();
    }

    private function lookup(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('track.find'), array_merge([
            'plate_number'   => 'กข 1234',
            'plate_province' => self::PROVINCE,
            'reference_code' => 'ABC234',
        ], $overrides));
    }

    // ─── ใช้งานได้จริง ─────────────────────────────────────────────────────

    public function test_page_is_public_and_needs_no_login(): void
    {
        $this->get(route('track.show'))->assertOk()
            ->assertSee('เช็คสถานะรถ')
            ->assertSee('รหัสอ้างอิง')
            ->assertSee('เลขทะเบียน');
    }

    public function test_correct_plate_and_code_shows_slot_time_and_fee(): void
    {
        $this->parkedCar();

        $this->lookup()->assertOk()
            ->assertSee('A007')                 // ช่องจอด
            ->assertSee('ค่าจอด ณ ตอนนี้')
            ->assertSee('฿60.00')               // 3 ชั่วโมง × 20
            ->assertSee('จอดมาแล้ว');
    }

    /** ทะเบียนพิมพ์คนละรูปแบบต้องยังหาเจอ (ใช้ตัวเทียบเดียวกับทั้งระบบ) */
    public function test_plate_format_does_not_matter(): void
    {
        $this->parkedCar();

        foreach (['กข1234', 'กข-1234', 'กข  1234'] as $variant) {
            $this->lookup(['plate_number' => $variant])->assertOk()->assertSee('A007');
        }
    }

    public function test_reference_code_is_case_insensitive(): void
    {
        $this->parkedCar();

        $this->lookup(['reference_code' => 'abc234'])->assertOk()->assertSee('A007');
    }

    // ─── ความเป็นส่วนตัว ───────────────────────────────────────────────────

    /** ทะเบียนถูกแต่ไม่มีรหัส = ดูไม่ได้ — นี่คือเหตุผลที่ต้องมีรหัส */
    public function test_wrong_code_reveals_nothing(): void
    {
        $this->parkedCar();

        $res = $this->lookup(['reference_code' => 'ZZZ999']);
        $res->assertSessionHasErrors('reference_code');
        $res->assertRedirect();

        $this->followRedirects($res)->assertDontSee('A007');
    }

    /** รหัสถูกแต่เป็นของรถคันอื่น ก็ต้องดูไม่ได้ */
    public function test_code_of_another_car_does_not_work(): void
    {
        $this->parkedCar();
        $this->parkedCar(['license_plate' => 'ขค 9999', 'reference_code' => 'XYZ678']);

        $this->lookup(['reference_code' => 'XYZ678'])->assertSessionHasErrors('reference_code');
    }

    public function test_wrong_province_does_not_match(): void
    {
        $this->parkedCar();

        $this->lookup(['plate_province' => 'ชลบุรี'])->assertSessionHasErrors('reference_code');
    }

    /** ข้อความผิดพลาดต้องเหมือนกันทุกกรณี ไม่บอกว่าผิดที่ทะเบียนหรือรหัส */
    public function test_failure_message_does_not_say_which_field_was_wrong(): void
    {
        $this->parkedCar();

        $wrongCode = $this->lookup(['reference_code' => 'ZZZ999'])->getSession()->get('errors')->first('reference_code');
        $wrongPlate = $this->lookup(['plate_number' => 'ขค 5555'])->getSession()->get('errors')->first('reference_code');

        $this->assertSame($wrongCode, $wrongPlate);
    }

    // ─── รถที่ไม่ได้จอดอยู่แล้ว ─────────────────────────────────────────────

    public function test_checked_out_car_is_not_shown(): void
    {
        $reservation = $this->parkedCar();
        $reservation->parkingLog->update(['check_out_time' => now()]);
        $reservation->update(['status' => 'completed']);

        $this->lookup()->assertSessionHasErrors('reference_code');
    }

    // ─── ความปลอดภัยของช่องกรอก ────────────────────────────────────────────

    public function test_junk_input_is_rejected(): void
    {
        $this->lookup(['plate_number' => 'ฟ้าพฟฟด'])->assertSessionHasErrors('plate_number');
        $this->lookup(['reference_code' => 'AB'])->assertSessionHasErrors('reference_code');
        $this->post(route('track.find'), [])->assertSessionHasErrors(['plate_number', 'plate_province', 'reference_code']);
    }

    public function test_lookups_are_rate_limited(): void
    {
        $this->parkedCar();

        for ($i = 0; $i < 10; $i++) {
            $this->lookup(['reference_code' => 'ZZZ99'.$i]);
        }

        $this->lookup()->assertStatus(429);
    }

    // ─── การจองล่วงหน้าก็ใช้หน้านี้ได้ หลังรถเข้าลานแล้ว ─────────────────────

    public function test_booked_car_also_works_after_check_in(): void
    {
        $user = User::factory()->create(['role' => 'user', 'force_password_reset' => false, 'email_verified_at' => now()]);

        $reservation = $this->parkedCar([
            'user_id'        => $user->id,
            'is_walk_in'     => false,
            'license_plate'  => 'ชล 4321',
            'reference_code' => 'BKD567',
            'deposit_amount' => 20,
        ]);

        $this->lookup(['plate_number' => 'ชล 4321', 'reference_code' => 'BKD567'])
            ->assertOk()
            ->assertSee('A007')
            // ไม่เปิดเผยตัวตนเจ้าของบัญชี
            ->assertDontSee($user->name)
            ->assertDontSee($user->email);

        $this->assertFalse((bool) $reservation->is_walk_in);
    }
}
