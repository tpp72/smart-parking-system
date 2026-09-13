<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\ReservationService;
use App\Services\SlotAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** ระบบจัดสรร Slot อัตโนมัติ + Lock + วงจรสถานะ Slot (project-plan.md §6.3, §24) */
class SlotAllocationTest extends TestCase
{
    use RefreshDatabase;

    private int $plateSeq = 5000;

    private function user(string $role = 'user'): User
    {
        return User::factory()->create([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function book(ParkingLot $lot, array $overrides = []): Reservation
    {
        return app(ReservationService::class)->create($this->user(), $lot, array_merge([
            'license_plate'  => 'ทด ' . $this->plateSeq++,
            'plate_province' => 'สงขลา',
            'brand'          => 'Nissan',
            'color'          => 'เงิน',
            'reserve_start'  => now()->addHours(2),
        ], $overrides));
    }

    private function confirm(Reservation $reservation): array
    {
        return app(ReservationService::class)->markDepositPaid($reservation->depositPayment, $this->user('admin'));
    }

    private function slot(ParkingLot $lot, string $number, string $status = 'available'): ParkingSlot
    {
        return ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => $number, 'status' => $status]);
    }

    // ─── [1] ระบบเลือกช่องว่าง ข้ามช่อง reserved / occupied ─────────────────

    public function test_system_allocates_an_available_slot_skipping_reserved_and_occupied(): void
    {
        $lot = ParkingLot::factory()->create();
        $occupied = $this->slot($lot, 'A001', 'occupied');
        $reserved = $this->slot($lot, 'A002', 'reserved');
        $first    = $this->slot($lot, 'A003');
        $second   = $this->slot($lot, 'A004');

        $reservation = $this->book($lot);
        $result = $this->confirm($reservation);

        $this->assertSame(ReservationService::OUTCOME_CONFIRMED, $result['outcome']);
        $this->assertSame($first->id, $reservation->fresh()->parking_slot_id);

        $this->assertDatabaseHas('parking_slots', ['id' => $first->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('parking_slots', ['id' => $occupied->id, 'status' => 'occupied']);
        $this->assertDatabaseHas('parking_slots', ['id' => $reserved->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('parking_slots', ['id' => $second->id, 'status' => 'available']);
    }

    // ─── [2] ไม่เกิด Slot ซ้ำ ───────────────────────────────────────────────

    public function test_each_confirmed_reservation_gets_a_distinct_slot(): void
    {
        $lot = ParkingLot::factory()->create();
        foreach (['B001', 'B002', 'B003'] as $n) {
            $this->slot($lot, $n);
        }

        $slotIds = collect(range(1, 3))
            ->map(fn () => $this->confirm($this->book($lot))['slot']->id);

        $this->assertCount(3, $slotIds->unique());
        $this->assertSame(3, ParkingSlot::where('status', 'reserved')->count());
        $this->assertSame(0, ParkingSlot::where('status', 'available')->count());
    }

    // ─── [3] ช่องหมด → รายการถัดไปไม่ได้ช่องซ้ำ (ลานเต็ม) ──────────────────

    public function test_slot_is_never_given_to_a_second_reservation(): void
    {
        $lot  = ParkingLot::factory()->create();
        $only = $this->slot($lot, 'C001');

        $first  = $this->book($lot);
        $second = $this->book($lot);

        $this->assertSame(ReservationService::OUTCOME_CONFIRMED, $this->confirm($first)['outcome']);
        $this->assertSame(ReservationService::OUTCOME_LOT_FULL, $this->confirm($second)['outcome']);

        $this->assertSame($only->id, $first->fresh()->parking_slot_id);
        $this->assertNull($second->fresh()->parking_slot_id);
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $only->id, 'status' => 'reserved']);
    }

    // ─── [4] ช่องที่ User ส่งมาถูกละเลย — ระบบเป็นผู้จัดสรร ───────────────────

    public function test_slot_sent_by_user_is_ignored_and_system_allocates(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();
        $systemPick = $this->slot($lot, 'D001');
        $userPick   = $this->slot($lot, 'D009');

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'    => 'กข 9090',
            'plate_province'  => 'ตรัง',
            'brand'           => 'Ford',
            'color'           => 'ดำ',
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $userPick->id,
            'reserve_start'   => now()->addHours(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();
        $this->assertNull($reservation->parking_slot_id);

        $this->confirm($reservation);

        $this->assertSame($systemPick->id, $reservation->fresh()->parking_slot_id);
        $this->assertDatabaseHas('parking_slots', ['id' => $userPick->id, 'status' => 'available']);
    }

    // ─── [5] Race Condition — query ใช้ FOR UPDATE SKIP LOCKED ภายใน transaction ─

    public function test_available_slot_is_locked_with_skip_locked(): void
    {
        $lot = ParkingLot::factory()->create();
        $this->slot($lot, 'E001');

        DB::enableQueryLog();
        $slot = DB::transaction(fn () => app(SlotAllocator::class)->lockAvailableSlot($lot->id));
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertNotNull($slot);
        $this->assertStringContainsStringIgnoringCase('for update skip locked', $queries);
    }

    // ─── [6] Check-in → occupied (ช่องที่ Lock ไว้) → Check-out → available ─

    public function test_slot_lifecycle_reserved_occupied_available(): void
    {
        $lot = ParkingLot::factory()->create(['hourly_rate' => 20]);
        $this->slot($lot, 'F001');
        $this->slot($lot, 'F002');

        $reservation = $this->book($lot, ['reserve_start' => now()->subMinute()]);
        $locked = $this->confirm($reservation)['slot'];

        $checkIn = app(CheckInService::class)->checkInReservation($reservation->fresh());

        $this->assertTrue($checkIn['success']);
        $this->assertSame($locked->id, $checkIn['slot']->id);
        $this->assertDatabaseHas('parking_slots', ['id' => $locked->id, 'status' => 'occupied']);
        $this->assertSame(1, ParkingSlot::where('status', 'available')->count());

        app(CheckOutService::class)->checkOut($checkIn['log']->fresh());

        $this->assertDatabaseHas('parking_slots', ['id' => $locked->id, 'status' => 'available']);
    }

    // ─── [7] Cancel / Expire → คืน Slot ─────────────────────────────────────

    public function test_cancel_and_expire_release_the_locked_slot(): void
    {
        $lot = ParkingLot::factory()->create();
        $this->slot($lot, 'G001');
        $this->slot($lot, 'G002');

        $cancelled = $this->book($lot);
        $cancelledSlot = $this->confirm($cancelled)['slot'];
        app(ReservationService::class)->cancel($cancelled, $cancelled->user, 'User ยกเลิกการจอง');
        $this->assertDatabaseHas('parking_slots', ['id' => $cancelledSlot->id, 'status' => 'available']);

        $expiring = $this->book($lot, ['reserve_start' => now()->subHours(3)]);
        $expiringSlot = $this->confirm($expiring)['slot'];
        $this->artisan('reservations:expire')->assertSuccessful();

        $this->assertSame('expired', $expiring->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $expiringSlot->id, 'status' => 'available']);
    }
}
