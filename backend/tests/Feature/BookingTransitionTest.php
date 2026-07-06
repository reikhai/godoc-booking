<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_happy_path_pending_confirmed_completed(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/bookings/{$booking->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/bookings/{$booking->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $booking->refresh();
        $this->assertNotNull($booking->confirmed_at);
        $this->assertNotNull($booking->completed_at);
    }

    public function test_cannot_complete_a_pending_booking(): void
    {
        $booking = Booking::factory()->create(); // pending

        $this->postJson("/api/bookings/{$booking->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_transition')
            ->assertJsonPath('from', 'pending')
            ->assertJsonPath('to', 'completed');
    }

    public function test_cannot_confirm_a_cancelled_booking(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $this->postJson("/api/bookings/{$booking->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_transition');
    }

    public function test_response_exposes_the_allowed_next_transitions(): void
    {
        $booking = Booking::factory()->create(); // pending

        $this->getJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.allowed_transitions', ['confirmed', 'cancelled']);
    }

    public function test_validation_rejects_a_missing_slot(): void
    {
        $this->postJson('/api/bookings', [
            'slot_id' => 999999,
            'patient' => ['name' => 'X', 'email' => 'x@example.com'],
        ])->assertStatus(422)->assertJsonValidationErrorFor('slot_id');
    }
}
