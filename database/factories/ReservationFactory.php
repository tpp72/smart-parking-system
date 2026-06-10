<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'vehicle_id'      => Vehicle::factory(),
            'parking_lot_id'  => ParkingLot::factory(),
            'parking_slot_id' => null,
            'reserve_start'   => now()->addHours(2),
            'reservation_fee' => 0,
            'status'          => 'pending',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function expired(): static
    {
        return $this->state(['status' => 'expired']);
    }

    public function checkedIn(): static
    {
        return $this->state([
            'status'        => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    public function past(): static
    {
        return $this->state([
            'reserve_start' => now()->subHours(3),
        ]);
    }
}
