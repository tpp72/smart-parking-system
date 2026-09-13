<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SuspiciousVehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_plate'  => $this->faker->randomElement(['ฆฒ', 'ฌญ', 'ฐฑ', 'ณด'])
                . ' ' . $this->faker->unique()->numberBetween(1000, 9999),
            'plate_province' => $this->faker->randomElement(config('thai_provinces')),
            'reason'         => $this->faker->sentence(),
            'level'          => $this->faker->randomElement(['low', 'medium', 'high']),
            'is_active'      => true,
            'added_by'       => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
