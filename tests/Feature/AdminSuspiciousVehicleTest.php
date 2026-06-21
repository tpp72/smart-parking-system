<?php

namespace Tests\Feature;

use App\Models\SuspiciousVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSuspiciousVehicleTest extends TestCase
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

    private function regularUser(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    // ─── [1] admin can create a blacklist entry ───────────────────────────────

    public function test_admin_can_create_blacklist_entry(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.suspicious-vehicles.store'), [
                'license_plate' => 'AB-1234',
                'reason'        => 'Suspicious behaviour',
                'level'         => 'high',
                'is_active'     => '1',
            ])
            ->assertRedirect(route('admin.suspicious-vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suspicious_vehicles', [
            'license_plate' => 'AB-1234',
            'level'         => 'high',
        ]);
    }

    // ─── [2] admin can update a blacklist entry ───────────────────────────────

    public function test_admin_can_update_blacklist_entry(): void
    {
        $admin = $this->admin();
        $entry = SuspiciousVehicle::factory()->create(['level' => 'low']);

        $this->actingAs($admin)
            ->patch(route('admin.suspicious-vehicles.update', $entry), [
                'license_plate' => $entry->license_plate,
                'reason'        => 'Updated reason',
                'level'         => 'high',
                'is_active'     => '1',
            ])
            ->assertRedirect(route('admin.suspicious-vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suspicious_vehicles', [
            'id'    => $entry->id,
            'level' => 'high',
        ]);
    }

    // ─── [3] admin can toggle active status ──────────────────────────────────

    public function test_admin_can_toggle_active_status(): void
    {
        $admin = $this->admin();
        $entry = SuspiciousVehicle::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.suspicious-vehicles.toggle', $entry))
            ->assertRedirect();

        $this->assertDatabaseHas('suspicious_vehicles', [
            'id'        => $entry->id,
            'is_active' => false,
        ]);

        // Toggle back
        $this->actingAs($admin)
            ->post(route('admin.suspicious-vehicles.toggle', $entry))
            ->assertRedirect();

        $this->assertDatabaseHas('suspicious_vehicles', [
            'id'        => $entry->id,
            'is_active' => true,
        ]);
    }

    // ─── [4] admin can delete a blacklist entry ───────────────────────────────

    public function test_admin_can_delete_blacklist_entry(): void
    {
        $admin = $this->admin();
        $entry = SuspiciousVehicle::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.suspicious-vehicles.destroy', $entry))
            ->assertRedirect(route('admin.suspicious-vehicles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('suspicious_vehicles', ['id' => $entry->id]);
    }

    // ─── [5] non-admin is blocked from the index ──────────────────────────────

    public function test_non_admin_cannot_access_blacklist(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)
            ->get(route('admin.suspicious-vehicles.index'))
            ->assertStatus(403);
    }

    // ─── [6] search filters by license plate ──────────────────────────────────

    public function test_search_filters_by_license_plate(): void
    {
        $admin = $this->admin();
        SuspiciousVehicle::factory()->create(['license_plate' => 'AA-1111']);
        SuspiciousVehicle::factory()->create(['license_plate' => 'ZZ-9999']);

        $response = $this->actingAs($admin)
            ->get(route('admin.suspicious-vehicles.index', ['q' => 'AA-1111']));

        $response->assertStatus(200);
        $response->assertSee('AA-1111');
        $response->assertDontSee('ZZ-9999');
    }

    // ─── [7] pagination shows 15 per page ────────────────────────────────────

    public function test_pagination_shows_15_per_page(): void
    {
        $admin = $this->admin();
        SuspiciousVehicle::factory()->count(16)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.suspicious-vehicles.index'));

        $response->assertStatus(200);
        // Page 1 has exactly 15 entries
        $this->assertSame(15, $response->viewData('entries')->count());
    }

    // ─── [8] added_by is set to the authenticated admin ──────────────────────

    public function test_added_by_is_set_to_current_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.suspicious-vehicles.store'), [
                'license_plate' => 'XY-5678',
                'level'         => 'medium',
            ]);

        $this->assertDatabaseHas('suspicious_vehicles', [
            'license_plate' => 'XY-5678',
            'added_by'      => $admin->id,
        ]);
    }

    // ─── [9] duplicate license plate is rejected ──────────────────────────────

    public function test_duplicate_license_plate_is_rejected(): void
    {
        $admin = $this->admin();
        SuspiciousVehicle::factory()->create(['license_plate' => 'DUP-001']);

        $this->actingAs($admin)
            ->post(route('admin.suspicious-vehicles.store'), [
                'license_plate' => 'DUP-001',
                'level'         => 'low',
            ])
            ->assertSessionHasErrors('license_plate');
    }
}
