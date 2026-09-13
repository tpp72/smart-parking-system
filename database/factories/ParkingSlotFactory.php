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
            // (parking_lot_id, slot_number) ต้องไม่ซ้ำ
            'slot_number'    => strtoupper($this->faker->unique()->bothify('?###')),
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
