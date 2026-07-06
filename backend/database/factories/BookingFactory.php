<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slot_id' => Slot::factory(),
            'patient_id' => Patient::factory(),
            'status' => BookingStatus::Pending,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
