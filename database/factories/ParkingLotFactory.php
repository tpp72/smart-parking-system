<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingLotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => 'ลานจอด ' . $this->faker->unique()->bothify('??-##'),
            'location'    => $this->faker->address(),
            'total_slots' => $this->faker->numberBetween(10, 200),
            'hourly_rate' => $this->faker->randomElement([20.00, 30.00, 40.00, 50.00]),
        ];
    }
}
