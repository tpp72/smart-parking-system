<?php

namespace Database\Factories;

use App\Models\ParkingLot;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParkingLogFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('-7 days', '-1 hour');

        return [
            'parking_lot_id'  => ParkingLot::factory(),
            'parking_slot_id' => null,
            'license_plate'   => $this->faker->randomElement(['กข', 'ขค', 'คง', 'งจ', 'จฉ', 'ชซ', 'พร', 'สต'])
                . ' ' . $this->faker->unique()->numberBetween(1000, 9999),
            'plate_province'  => $this->faker->randomElement(config('thai_provinces')),
            'brand'           => $this->faker->randomElement(['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan']),
            'color'           => $this->faker->randomElement(config('car_colors')),
            'check_in_time'   => $checkIn,
            'check_out_time'  => null,
            // อัตราค่าจอด ณ ตอน Check-in = อัตราปัจจุบันของลาน
            'hourly_rate'     => fn (array $attrs) => ParkingLot::whereKey($attrs['parking_lot_id'])->value('hourly_rate') ?? 0,
            // ทุกการจอดมาจาก Reservation — ค่าเริ่มต้นเป็น Walk-in ของรถคันเดียวกันในลานเดียวกัน (ต้องอยู่ท้ายสุด)
            'reservation_id'  => fn (array $attrs) => Reservation::factory()->walkIn()->create([
                'parking_lot_id' => $attrs['parking_lot_id'],
                'license_plate'  => $attrs['license_plate'],
                'plate_province' => $attrs['plate_province'],
                'brand'          => $attrs['brand'],
                'color'          => $attrs['color'],
                'reserve_start'  => $attrs['check_in_time'],
                'checked_in_at'  => $attrs['check_in_time'],
            ])->id,
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
