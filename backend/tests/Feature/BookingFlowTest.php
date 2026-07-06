<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Slot;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patient_can_book_an_available_slot(): void
    {
        $slot = Slot::factory()->create();

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'patient' => ['name' => 'Alice', 'email' => 'alice@example.com'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.slot_id', $slot->id);

        $this->assertDatabaseHas('bookings', [
            'slot_id' => $slot->id,
            'status' => 'pending',
            'active_slot_id' => $slot->id,
        ]);
    }

    public function test_booking_the_same_slot_twice_is_rejected_with_409(): void
    {
        $slot = Slot::factory()->create();

        $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'patient' => ['name' => 'Alice', 'email' => 'alice@example.com'],
        ])->assertCreated();

        $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'patient' => ['name' => 'Bob', 'email' => 'bob@example.com'],
        ])->assertStatus(409)->assertJsonPath('error', 'slot_unavailable');

        $this->assertSame(1, Booking::where('slot_id', $slot->id)->count());
    }

    public function test_available_slots_excludes_booked_ones(): void
    {
        $slot = Slot::factory()->create();
        $doctorId = $slot->doctor_id;

        $this->getJson("/api/doctors/{$doctorId}/slots")
            ->assertJsonCount(1, 'data');

        app(BookingService::class)->book($slot->id, 'Alice', 'alice@example.com');

        $this->getJson("/api/doctors/{$doctorId}/slots")
            ->assertJsonCount(0, 'data');
    }

    public function test_the_database_unique_index_is_a_hard_backstop(): void
    {
        // Bypass the service entirely and force two active bookings for one slot
        // straight at the database. The unique index must reject the second.
        $slot = Slot::factory()->create();
        $p1 = Patient::factory()->create();
        $p2 = Patient::factory()->create();

        Booking::create(['slot_id' => $slot->id, 'patient_id' => $p1->id, 'status' => BookingStatus::Pending]);

        $this->expectException(QueryException::class);
        Booking::create(['slot_id' => $slot->id, 'patient_id' => $p2->id, 'status' => BookingStatus::Confirmed]);
    }

    public function test_a_patient_cannot_book_two_slots_on_the_same_day(): void
    {
        $doctor = \App\Models\Doctor::factory()->create();
        $nine = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(9, 30),
        ]);
        $ten = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(10, 30),
        ]);

        $payload = fn (Slot $s) => [
            'slot_id' => $s->id,
            'patient' => ['name' => 'Alice', 'email' => 'alice@example.com'],
        ];

        $this->postJson('/api/bookings', $payload($nine))->assertCreated();

        $this->postJson('/api/bookings', $payload($ten))
            ->assertStatus(422)
            ->assertJsonPath('error', 'daily_booking_limit');

        $this->assertSame(1, Booking::count());
    }

    public function test_a_patient_can_book_slots_on_different_days(): void
    {
        $doctor = \App\Models\Doctor::factory()->create();
        $tomorrow = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(9, 30),
        ]);
        $dayAfter = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDays(2)->setTime(9, 0),
            'end_at' => now()->addDays(2)->setTime(9, 30),
        ]);

        $service = app(BookingService::class);
        $service->book($tomorrow->id, 'Alice', 'alice@example.com');
        $service->book($dayAfter->id, 'Alice', 'alice@example.com');

        $this->assertSame(2, Booking::count());
    }

    public function test_cancelling_frees_the_day_for_another_booking(): void
    {
        $doctor = \App\Models\Doctor::factory()->create();
        $nine = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(9, 30),
        ]);
        $ten = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(10, 30),
        ]);

        $service = app(BookingService::class);
        $first = $service->book($nine->id, 'Alice', 'alice@example.com');
        $first->cancel();

        // Same patient, same day, different slot — allowed after cancelling.
        $second = $service->book($ten->id, 'Alice', 'alice@example.com');

        $this->assertSame(BookingStatus::Pending, $second->status);
    }

    public function test_the_daily_limit_unique_index_is_a_hard_backstop(): void
    {
        // Bypass the service and force two active same-day bookings for one
        // patient straight at the database. The composite index must reject it.
        $doctor = \App\Models\Doctor::factory()->create();
        $nine = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(9, 30),
        ]);
        $ten = Slot::factory()->create([
            'doctor_id' => $doctor->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(10, 30),
        ]);
        $patient = Patient::factory()->create();

        Booking::create(['slot_id' => $nine->id, 'patient_id' => $patient->id, 'status' => BookingStatus::Pending]);

        $this->expectException(QueryException::class);
        Booking::create(['slot_id' => $ten->id, 'patient_id' => $patient->id, 'status' => BookingStatus::Pending]);
    }

    public function test_cancelling_a_booking_frees_the_slot_for_rebooking(): void
    {
        $slot = Slot::factory()->create();
        $service = app(BookingService::class);

        $first = $service->book($slot->id, 'Alice', 'alice@example.com');
        $first->cancel();

        // Slot is free again; a different patient can now book it.
        $second = $service->book($slot->id, 'Bob', 'bob@example.com');

        $this->assertSame(BookingStatus::Pending, $second->status);
        $this->assertSame(BookingStatus::Cancelled, $first->fresh()->status);
        $this->assertNull($first->fresh()->active_slot_id);
    }
}
