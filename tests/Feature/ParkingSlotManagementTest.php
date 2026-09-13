<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** จัดการ Slot โดย Admin / Owner — สถานะช่องระบบจัดการเอง · ห้ามลบช่องที่ใช้งานอยู่ */
class ParkingSlotManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'force_password_reset' => false, 'email_verified_at' => now()]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'owner_status' => 'approved', 'force_password_reset' => false, 'email_verified_at' => now()]);
    }

    // ─── [1] ห้ามลบช่องที่มีรถจอดอยู่ (Admin) ────────────────────────────────

    public function test_admin_cannot_delete_occupied_slot(): void
    {
        $slot = ParkingSlot::factory()->occupied()->create(['parking_lot_id' => ParkingLot::factory()->create()->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.parking-slots.destroy', $slot))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
    }

    // ─── [2] ห้ามลบช่องที่ถูก Lock ให้การจอง (Admin) ─────────────────────────

    public function test_admin_cannot_delete_reserved_slot(): void
    {
        $slot = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => ParkingLot::factory()->create()->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.parking-slots.destroy', $slot))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
    }

    // ─── [3] ลบช่องว่างได้ ───────────────────────────────────────────────────

    public function test_admin_can_delete_available_slot(): void
    {
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => ParkingLot::factory()->create()->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.parking-slots.destroy', $slot))
            ->assertRedirect(route('admin.parking-slots.index'));

        $this->assertDatabaseMissing('parking_slots', ['id' => $slot->id]);
    }

    // ─── [4] Owner: ห้ามลบช่องที่มีรถจอด · ลบช่องว่างได้ ─────────────────────

    public function test_owner_cannot_delete_occupied_slot_but_can_delete_available_one(): void
    {
        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $occupied  = ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $lot->id]);
        $available = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $this->actingAs($owner)
            ->delete(route('owner.parking-slots.destroy', $occupied))
            ->assertSessionHasErrors('error');

        $this->actingAs($owner)
            ->delete(route('owner.parking-slots.destroy', $available))
            ->assertRedirect(route('owner.parking-slots.index'));

        $this->assertDatabaseHas('parking_slots', ['id' => $occupied->id]);
        $this->assertDatabaseMissing('parking_slots', ['id' => $available->id]);
    }

    // ─── [5] สร้างช่องใหม่เป็น available เสมอ (กำหนดสถานะเองไม่ได้) ─────────

    public function test_new_slot_is_always_available(): void
    {
        $lot = ParkingLot::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.parking-slots.store'), [
            'parking_lot_id' => $lot->id,
            'slot_number'    => 'X001',
            'status'         => 'occupied',
        ])->assertRedirect(route('admin.parking-slots.index'));

        $this->assertDatabaseHas('parking_slots', ['parking_lot_id' => $lot->id, 'slot_number' => 'X001', 'status' => 'available']);
    }

    // ─── [6] แก้ไขช่องเปลี่ยนสถานะเองไม่ได้ ─────────────────────────────────

    public function test_slot_update_cannot_change_status(): void
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'Y001']);

        $this->actingAs($this->admin())->patch(route('admin.parking-slots.update', $slot), [
            'parking_lot_id' => $lot->id,
            'slot_number'    => 'Y001-A',
            'status'         => 'available',
        ])->assertRedirect(route('admin.parking-slots.index'));

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'slot_number' => 'Y001-A', 'status' => 'reserved']);
    }

    // ─── [7] ช่องที่ใช้งานอยู่ย้ายลานไม่ได้ ───────────────────────────────────

    public function test_occupied_slot_cannot_be_moved_to_another_lot(): void
    {
        $lotA = ParkingLot::factory()->create();
        $lotB = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $lotA->id]);

        $this->actingAs($this->admin())->patch(route('admin.parking-slots.update', $slot), [
            'parking_lot_id' => $lotB->id,
            'slot_number'    => $slot->slot_number,
        ])->assertSessionHasErrors('parking_lot_id');

        $this->assertSame($lotA->id, $slot->fresh()->parking_lot_id);
    }

    // ─── [8] Bulk Create — ช่องใหม่ทั้งหมดเป็น available ────────────────────

    public function test_owner_bulk_created_slots_are_available(): void
    {
        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('owner.parking-slots.bulk.store'), [
            'parking_lot_id' => $lot->id,
            'mode'           => 'range',
            'prefix'         => 'Z',
            'start'          => 1,
            'end'            => 3,
            'pad'            => 2,
            'status'         => 'occupied',
        ])->assertRedirect(route('owner.parking-slots.index'));

        $this->assertSame(3, ParkingSlot::where('parking_lot_id', $lot->id)->where('status', 'available')->count());
        $this->assertDatabaseHas('parking_slots', ['parking_lot_id' => $lot->id, 'slot_number' => 'Z01']);
    }

    // ─── [9] เลขช่องซ้ำในลานเดียวกันไม่ได้ ──────────────────────────────────

    public function test_duplicate_slot_number_in_same_lot_is_rejected(): void
    {
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'W001']);

        $this->actingAs($this->admin())->post(route('admin.parking-slots.store'), [
            'parking_lot_id' => $lot->id,
            'slot_number'    => 'W001',
        ])->assertSessionHasErrors('slot_number');

        $this->assertSame(1, ParkingSlot::where('parking_lot_id', $lot->id)->count());
    }
}
