<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** กดการ์ด "ลานที่ว่างแนะนำ" บน Dashboard → หน้าจองเลือกลานนั้นไว้ให้ */
class ReservationLotPreselectTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['role' => 'user', 'force_password_reset' => false, 'email_verified_at' => now()]);
    }

    private function lotWithFreeSlot(array $attrs = []): ParkingLot
    {
        $lot = ParkingLot::factory()->create(array_merge(['reservations_enabled' => true], $attrs));
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        return $lot;
    }

    public function test_dashboard_recommends_only_bookable_lots_and_links_with_the_lot(): void
    {
        $bookable = $this->lotWithFreeSlot();
        $closed = $this->lotWithFreeSlot(['reservations_enabled' => false]);
        $full = ParkingLot::factory()->create(['reservations_enabled' => true]);
        ParkingSlot::factory()->create(['parking_lot_id' => $full->id, 'status' => 'occupied']);

        $response = $this->actingAs($this->user())->get(route('user.dashboard'))->assertOk();

        $this->assertSame([$bookable->id], $response->viewData('lotsAvailable')->pluck('id')->all());
        $response->assertSee(route('user.reservations.create', ['lot_id' => $bookable->id]), false);
    }

    public function test_create_form_preselects_the_lot_from_the_query_string(): void
    {
        $lot = $this->lotWithFreeSlot();
        $this->lotWithFreeSlot();

        $response = $this->actingAs($this->user())->get(route('user.reservations.create', ['lot_id' => $lot->id]))->assertOk();

        $this->assertSame($lot->id, $response->viewData('selectedLotId'));
        $response->assertSee("lotId: '{$lot->id}'", false);
        $response->assertSee('<option value="' . $lot->id . '" selected', false);
    }

    public function test_unbookable_or_unknown_lot_is_not_preselected(): void
    {
        $closed = $this->lotWithFreeSlot(['reservations_enabled' => false]);
        $user = $this->user();

        foreach ([$closed->id, 999999, 'abc'] as $lotId) {
            $this->actingAs($user)->get(route('user.reservations.create', ['lot_id' => $lotId]))
                ->assertOk()
                ->assertViewHas('selectedLotId', null);
        }
    }
}
