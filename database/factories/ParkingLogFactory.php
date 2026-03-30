<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingLogFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('-7 days', '-1 hour');

        return [
            'vehicle_id'      => Vehicle::factory(),
            'parking_lot_id'  => ParkingLot::factory(),
            'parking_slot_id' => null,
            'check_in_time'   => $checkIn,
            'check_out_time'  => null,
        ];
    }

    /** รถยังอยู่ในลาน (active) */
    public function active(): static
    {
        return $this->state(['check_out_time' => null]);
    }

    /** รถออกไปแล้ว */
    public function completed(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'check_out_time' => now(),
            ];
        });
    }
}
