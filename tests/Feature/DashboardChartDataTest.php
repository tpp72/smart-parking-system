<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartDataTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role'                 => 'owner',
            'owner_status'         => 'approved',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function makeReservationForLot(ParkingLot $lot, string $status): Reservation
    {
        $user = User::factory()->create(['role' => 'user', 'force_password_reset' => false, 'email_verified_at' => now()]);

        return Reservation::factory()->create([
            'user_id'        => $user->id,
            'parking_lot_id' => $lot->id,
            'status'         => $status,
            'reserve_start'  => now()->addHour(),
        ]);
    }

    // ─── [1] admin reservation status breakdown covers all six statuses ──

    public function test_admin_dashboard_reservation_status_has_six_statuses(): void
    {
        $admin    = $this->admin();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $this->assertSame(Reservation::STATUSES, $response->viewData('reservationStatus')->keys()->all());
        $response->assertDontSee('chart.js', false);
    }

    // ─── [2] admin reservation status counts match DB (การจองใหม่ในช่วงเวลา) ─

    public function test_admin_reservation_status_counts_match_database(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        $this->makeReservationForLot($lot, 'pending');
        $this->makeReservationForLot($lot, 'pending');
        $this->makeReservationForLot($lot, 'confirmed');
        $this->makeReservationForLot($lot, 'completed');

        $data = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('reservationStatus');

        $this->assertSame(2, $data['pending']);
        $this->assertSame(1, $data['confirmed']);
        $this->assertSame(1, $data['completed']);
        $this->assertSame(0, $data['cancelled']);
    }

    // ─── [3] admin slot counts reflect current state ──────────────────────

    public function test_admin_slot_counts_match_slot_states(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        ParkingSlot::factory()->count(3)->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        ParkingSlot::factory()->count(2)->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        ParkingSlot::factory()->count(1)->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);

        $stats = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('stats');

        $this->assertSame([6, 3, 1, 2], [$stats['slots_total'], $stats['slots_available'], $stats['slots_reserved'], $stats['slots_occupied']]);
    }

    // ─── [4] admin top lots limited to five, busiest first ────────────────

    public function test_admin_top_lots_limited_to_five(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 6; $i++) {
            $lot = ParkingLot::factory()->create();
            for ($j = 0; $j < $i; $j++) {
                $this->makeReservationForLot($lot, 'completed');
            }
        }

        $topLots = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('topLots');

        $this->assertCount(5, $topLots);
        $this->assertSame([6, 5, 4, 3, 2], $topLots->pluck('total')->map(fn ($v) => (int) $v)->all());
    }

    // ─── [5] owner revenue trend (หน้ารายได้) has twelve months ───────────

    public function test_owner_revenue_trend_has_twelve_months(): void
    {
        $owner    = $this->owner();
        $response = $this->actingAs($owner)->get(route('owner.revenue.index'));

        $response->assertStatus(200);
        $this->assertCount(12, $response->viewData('revenueTrend'));
    }

    // ─── [6] owner upcoming bookings scoped to own lots ───────────────────

    public function test_owner_upcoming_reservations_scoped_to_own_lots(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        $lotA = ParkingLot::factory()->create(['owner_id' => $ownerA->id]);
        $lotB = ParkingLot::factory()->create(['owner_id' => $ownerB->id]);

        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotB, 'pending');
        $this->makeReservationForLot($lotB, 'pending');

        $response = $this->actingAs($ownerA)->get(route('owner.dashboard'));

        $this->assertCount(3, $response->viewData('upcoming'));
    }

    // ─── [7] owner slot occupancy scoped to own lots ──────────────────────

    public function test_owner_slot_occupancy_scoped_to_own_lots(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        $lotA = ParkingLot::factory()->create(['owner_id' => $ownerA->id]);
        $lotB = ParkingLot::factory()->create(['owner_id' => $ownerB->id]);

        ParkingSlot::factory()->count(4)->create(['parking_lot_id' => $lotA->id, 'status' => 'available']);
        ParkingSlot::factory()->count(10)->create(['parking_lot_id' => $lotB->id, 'status' => 'available']);

        $response = $this->actingAs($ownerA)->get(route('owner.dashboard'));

        // ownerA sees only 4 available, not 14
        $this->assertSame(4, $response->viewData('totals')['available']);
    }
}
