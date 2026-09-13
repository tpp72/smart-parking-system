<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'parking_lot_id'  => ParkingLot::factory(),
            'parking_slot_id' => null,
            'license_plate'   => $this->faker->randomElement(['กข', 'ขค', 'คง', 'งจ', 'จฉ', 'ชซ', 'พร', 'สต'])
                . ' ' . $this->faker->unique()->numberBetween(1000, 9999),
            'plate_province'  => $this->faker->randomElement(config('thai_provinces')),
            'brand'           => $this->faker->randomElement(['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan']),
            'color'           => $this->faker->randomElement(config('car_colors')),
            'reserve_start'   => now()->addHours(2),
            'deposit_amount'  => 0,
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

    /** Walk-in: Reservation ของ System User "Walkin User" — Deposit 0, ไม่มีส่วนลด, เข้าจอดทันที */
    public function walkIn(): static
    {
        return $this->state(fn () => [
            'user_id'         => User::walkin()->id,
            'is_walk_in'      => true,
            'reserve_start'   => now(),
            'deposit_amount'  => 0,
            'reservation_fee' => 0,
            'status'          => 'checked_in',
            'checked_in_at'   => now(),
        ]);
    }
}
