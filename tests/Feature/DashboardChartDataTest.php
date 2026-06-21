<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $user    = User::factory()->create(['role' => 'user', 'force_password_reset' => false, 'email_verified_at' => now()]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        return Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'status'         => $status,
            'reserve_start'  => now()->addHour(),
        ]);
    }

    // ─── [1] admin chart structure ────────────────────────────────────────

    public function test_admin_dashboard_reservation_status_chart_has_six_statuses(): void
    {
        $admin    = $this->admin();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $chart = $response->viewData('chartReservationStatus');

        $this->assertArrayHasKey('labels', $chart);
        $this->assertArrayHasKey('datasets', $chart);
        $this->assertCount(6, $chart['labels']);
        $this->assertCount(6, $chart['datasets'][0]['data']);
    }

    // ─── [2] admin reservation status counts match DB ─────────────────────

    public function test_admin_reservation_status_counts_match_database(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        $this->makeReservationForLot($lot, 'pending');
        $this->makeReservationForLot($lot, 'pending');
        $this->makeReservationForLot($lot, 'confirmed');
        $this->makeReservationForLot($lot, 'completed');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $data     = $response->viewData('chartReservationStatus')['datasets'][0]['data'];

        // indices: 0=pending, 1=confirmed, 2=checked_in, 3=completed, 4=cancelled, 5=expired
        $this->assertSame(2, $data[0]); // pending
        $this->assertSame(1, $data[1]); // confirmed
        $this->assertSame(1, $data[3]); // completed
    }

    // ─── [3] admin slot occupancy reflects current state ──────────────────

    public function test_admin_slot_occupancy_chart_matches_slot_states(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        ParkingSlot::factory()->count(3)->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        ParkingSlot::factory()->count(2)->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        ParkingSlot::factory()->count(1)->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $data     = $response->viewData('chartSlotOccupancy')['datasets'][0]['data'];

        // indices: 0=available, 1=reserved, 2=occupied
        $this->assertSame(3, $data[0]); // available
        $this->assertSame(1, $data[1]); // reserved
        $this->assertSame(2, $data[2]); // occupied
    }

    // ─── [4] admin top lots limited to five ───────────────────────────────

    public function test_admin_top_lots_chart_limited_to_five(): void
    {
        $admin = $this->admin();

        // Create 6 lots, each with a different number of reservations
        for ($i = 1; $i <= 6; $i++) {
            $lot = ParkingLot::factory()->create();
            for ($j = 0; $j < $i; $j++) {
                $this->makeReservationForLot($lot, 'completed');
            }
        }

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $chart    = $response->viewData('chartTopLots');

        $this->assertLessThanOrEqual(5, count($chart['labels']));
        $this->assertLessThanOrEqual(5, count($chart['datasets'][0]['data']));
    }

    // ─── [5] owner revenue trend has twelve months ────────────────────────

    public function test_owner_revenue_trend_has_twelve_months(): void
    {
        $owner    = $this->owner();
        $response = $this->actingAs($owner)->get(route('owner.dashboard'));

        $response->assertStatus(200);
        $chart = $response->viewData('chartRevenueTrend');

        $this->assertCount(12, $chart['labels']);
        $this->assertCount(12, $chart['datasets'][0]['data']);
    }

    // ─── [6] owner only sees own lots in reservation status chart ─────────

    public function test_owner_reservation_status_scoped_to_own_lots(): void
    {
        $ownerA = $this->owner();
        $ownerB = $this->owner();

        $lotA = ParkingLot::factory()->create(['owner_id' => $ownerA->id]);
        $lotB = ParkingLot::factory()->create(['owner_id' => $ownerB->id]);

        // 3 pending for ownerA, 2 pending for ownerB
        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotA, 'pending');
        $this->makeReservationForLot($lotB, 'pending');
        $this->makeReservationForLot($lotB, 'pending');

        $response = $this->actingAs($ownerA)->get(route('owner.dashboard'));
        $data     = $response->viewData('chartReservationStatus')['datasets'][0]['data'];

        // ownerA should see 3 pending, not 5
        $this->assertSame(3, $data[0]); // index 0 = pending
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
        $data     = $response->viewData('chartSlotOccupancy')['datasets'][0]['data'];

        // ownerA sees only 4 available, not 14
        $this->assertSame(4, $data[0]); // available
    }
}
