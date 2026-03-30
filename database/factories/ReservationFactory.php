<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 hour', '+2 days');
        $end   = (clone $start)->modify('+2 hours');

        return [
            'user_id'         => User::factory(),
            'vehicle_id'      => Vehicle::factory(),
            'parking_lot_id'  => ParkingLot::factory(),
            'parking_slot_id' => null,
            'reserve_start'   => $start,
            'reserve_end'     => $end,
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
}
