<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 7 — ลานจอดและช่องจอด (หน้าใช้ร่วม Owner / Admin) */
class LotSlotPagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'owner_status' => 'approved', 'force_password_reset' => false]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'force_password_reset' => false]);
    }

    public function test_lot_list_is_shared_view_with_real_slot_counts_and_delete_guard(): void
    {
        $owner = $this->owner();
        $free = ParkingLot::factory()->create(['owner_id' => $owner->id, 'name' => 'ลานว่าง']);
        $busy = ParkingLot::factory()->create(['owner_id' => $owner->id, 'name' => 'ลานมีจอง']);
        ParkingSlot::factory()->count(2)->create(['parking_lot_id' => $busy->id, 'status' => 'available']);
        ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $busy->id]);
        Reservation::factory()->create(['parking_lot_id' => $busy->id, 'status' => 'pending']);

        $response = $this->actingAs($owner)->get(route('owner.parking-lots.index'))->assertOk()->assertViewIs('parking.lots.index');
        $lots = $response->viewData('lots')->keyBy('id');
        $this->assertSame([3, 2, 1, 1], [$lots[$busy->id]->slots_count, $lots[$busy->id]->available_count, $lots[$busy->id]->occupied_count, $lots[$busy->id]->active_reservations_count]);

        $response->assertSee('ลบได้เมื่อไม่มีการจองที่ยังไม่จบ')
            ->assertSee('ยังไม่มีช่องจอด — ลูกค้าจองลานนี้ไม่ได้')
            ->assertSee('data-confirm-title="ลบลานจอดถาวร?"', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->actingAs($this->admin())->get(route('admin.parking-lots.index'))->assertOk()
            ->assertViewIs('parking.lots.index')->assertSee('ลานของผู้ดูแลระบบ')->assertDontSee('ลานว่าง');
    }

    public function test_lot_form_is_thai_and_explains_rate_and_reservation_toggle(): void
    {
        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id, 'province' => 'กรุงเทพมหานคร']);

        $this->actingAs($owner)->get(route('owner.parking-lots.edit', $lot->id))->assertOk()
            ->assertViewIs('parking.lots.form')
            ->assertSee('การจองเดิมและรถที่เข้าลานแล้วยังใช้อัตราเดิม')
            ->assertSee('รถ Walk-in ยังเข้าได้')
            ->assertSee('<option value="กรุงเทพมหานคร" selected', false)
            ->assertDontSee('Danger Zone')
            ->assertDontSee('(Reservations)');

        $this->actingAs($this->admin())->get(route('admin.parking-lots.create'))->assertOk()->assertViewIs('parking.lots.form');
    }

    public function test_slot_map_shows_one_lot_grouped_by_row_with_occupants(): void
    {
        $owner = $this->owner();
        $lotA = ParkingLot::factory()->create(['owner_id' => $owner->id, 'name' => 'ก ลานแรก']);
        $lotB = ParkingLot::factory()->create(['owner_id' => $owner->id, 'name' => 'ข ลานสอง']);
        foreach (['A2', 'A10', 'A1', 'B1'] as $number) {
            ParkingSlot::factory()->create(['parking_lot_id' => $lotB->id, 'slot_number' => $number, 'status' => 'available']);
        }
        $locked = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $lotB->id, 'slot_number' => 'B2']);
        Reservation::factory()->confirmed()->create(['parking_lot_id' => $lotB->id, 'parking_slot_id' => $locked->id, 'license_plate' => 'กข 9999']);
        ParkingSlot::factory()->create(['parking_lot_id' => $lotA->id, 'slot_number' => 'Z1']);

        $response = $this->actingAs($owner)->get(route('owner.parking-slots.index', ['lot_id' => $lotB->id]))->assertOk()->assertViewIs('parking.slots.index');

        // ทุกช่องของลานที่เลือก เรียงเลขแบบธรรมชาติ ไม่แบ่งหน้า
        $this->assertSame(['A1', 'A2', 'A10', 'B1', 'B2'], $response->viewData('slots')->pluck('slot_number')->all());
        $this->assertSame($locked->id, $response->viewData('occupants')->keys()->first());

        $response->assertSee('แถว A')->assertSee('แถว B')->assertSee('กข 9999')
            ->assertSee('ช่อง B2 จอง ทะเบียน กข 9999')
            ->assertDontSee('Z1')
            ->assertDontSee('>available<', false);
    }

    public function test_bulk_page_previews_numbers_in_thai(): void
    {
        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->get(route('owner.parking-slots.bulk.create', ['lot_id' => $lot->id]))->assertOk()
            ->assertViewIs('parking.slots.bulk')
            ->assertSee('อักษรนำหน้า')
            ->assertSee('จำนวนหลัก')
            ->assertSee('จะสร้าง')
            ->assertDontSee('Prefix')
            ->assertDontSee('(Range)');
    }

    public function test_bulk_page_knows_existing_numbers_to_block_duplicates_before_submit(): void
    {
        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $other = ParkingLot::factory()->create();
        foreach (['A001', 'A002'] as $number) {
            ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => $number]);
        }
        ParkingSlot::factory()->create(['parking_lot_id' => $other->id, 'slot_number' => 'X1']);

        $response = $this->actingAs($owner)->get(route('owner.parking-slots.bulk.create', ['lot_id' => $lot->id]))->assertOk();

        // ส่งเฉพาะเลขช่องของลานตัวเอง ไม่รั่วของลานคนอื่น
        $existing = $response->viewData('existing');
        $this->assertSame([$lot->id], $existing->keys()->all());
        $this->assertEqualsCanonicalizing(['A001', 'A002'], $existing[$lot->id]->all());

        $response->assertSee('มีเลขช่องซ้ำกับช่องที่มีอยู่แล้วในลานนี้')
            ->assertSee('duplicates.length > 0', false);

        // ถ้าผ่านหน้าเว็บมาได้ ฝั่งเซิร์ฟเวอร์ยังปฏิเสธเลขซ้ำอีกชั้น
        $this->actingAs($owner)->post(route('owner.parking-slots.bulk.store'), [
            'parking_lot_id' => $lot->id, 'mode' => 'list', 'slot_numbers' => "A002\nA003",
        ])->assertSessionHasErrors('slot_numbers');
        $this->assertSame(2, ParkingSlot::where('parking_lot_id', $lot->id)->count());
    }
}
