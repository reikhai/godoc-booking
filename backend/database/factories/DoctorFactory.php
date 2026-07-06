<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Doctor> */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.fake()->name(),
            'specialty' => fake()->randomElement(['GP', 'Cardiology', 'Dermatology', 'Paediatrics']),
        ];
    }
}
