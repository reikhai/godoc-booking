<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<\App\Models\Slot> */
class SlotFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::tomorrow()->setTime(9, 0)->addMinutes(30 * fake()->numberBetween(0, 20));

        return [
            'doctor_id' => Doctor::factory(),
            'start_at' => $start,
            'end_at' => (clone $start)->addMinutes(30),
        ];
    }
}
