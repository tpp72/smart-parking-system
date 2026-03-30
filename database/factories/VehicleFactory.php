<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $plates  = ['กข', 'ขค', 'คง', 'งจ', 'จฉ', 'ชซ', 'พร', 'สต'];
        $brands  = ['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan', 'BMW', 'Mercedes'];
        $colors  = ['ขาว', 'ดำ', 'เงิน', 'แดง', 'น้ำเงิน', 'เทา', 'เขียว'];
        $provinces = ['กรุงเทพมหานคร', 'เชียงใหม่', 'ชลบุรี', 'ภูเก็ต', 'นนทบุรี'];

        return [
            'user_id'       => User::factory(),
            'license_plate' => $this->faker->randomElement($plates) . ' '
                . $this->faker->numberBetween(1000, 9999) . ' '
                . $this->faker->randomElement($provinces),
            'brand'         => $this->faker->randomElement($brands),
            'color'         => $this->faker->randomElement($colors),
        ];
    }
}
