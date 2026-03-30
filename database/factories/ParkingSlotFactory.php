<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parking_lot_id' => ParkingLot::factory(),
            'slot_number'    => strtoupper($this->faker->bothify('?##')),
            'status'         => 'available',
        ];
    }

    public function occupied(): static
    {
        return $this->state(['status' => 'occupied']);
    }

    public function reserved(): static
    {
        return $this->state(['status' => 'reserved']);
    }
}
