<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DailyBookingLimitException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Book a slot for a patient, safe under concurrency.
     *
     * Invariants enforced (each by an app-level check AND a DB unique index):
     *
     *   1. One active booking per slot ("no double-booking").
     *      Primary: inside the transaction we `SELECT ... FOR UPDATE` the slot
     *      row, so concurrent bookers for the same slot serialise on the row
     *      lock — the loser wakes up, sees the winner's booking, and gets a 409.
     *      Backstop: UNIQUE(active_slot_id).
     *
     *   2. One active booking per patient per day.
     *      The slot row lock cannot serialise two requests for *different*
     *      slots on the same day, so the app-level check alone is racy.
     *      Backstop: UNIQUE(patient_id, active_date) — the second concurrent
     *      insert fails at the database and is translated to the same 422.
     *
     * Correctness therefore never depends on application code alone; the
     * database is the final arbiter for both rules.
     *
     * @throws SlotUnavailableException   (409) slot already actively booked.
     * @throws DailyBookingLimitException (422) patient already booked that day.
     */
    public function book(int $slotId, string $patientName, string $patientEmail): Booking
    {
        return DB::transaction(function () use ($slotId, $patientName, $patientEmail) {
            // Acquire a row-level lock on the slot. This is the serialization point.
            $slot = Slot::whereKey($slotId)->lockForUpdate()->firstOrFail();

            // With the lock held, check for an existing active booking.
            $alreadyBooked = $slot->bookings()
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->exists();

            if ($alreadyBooked) {
                throw new SlotUnavailableException($slotId);
            }

            // Patients are identified by email; upsert so repeat patients reuse a row.
            $patient = Patient::firstOrCreate(
                ['email' => $patientEmail],
                ['name' => $patientName],
            );

            // Business rule: one active booking per patient per calendar day.
            $date = $slot->start_at->toDateString();
            $hasBookingThatDay = $patient->bookings()
                ->where('active_date', $date)
                ->exists();

            if ($hasBookingThatDay) {
                throw new DailyBookingLimitException($date);
            }

            try {
                return Booking::create([
                    'slot_id' => $slot->id,
                    'patient_id' => $patient->id,
                    'status' => BookingStatus::Pending,
                ]);
            } catch (QueryException $e) {
                // A unique index fired: translate to the same domain error the
                // app-level check would have raised.
                if (str_contains($e->getMessage(), 'uniq_active_patient_day')) {
                    throw new DailyBookingLimitException($date);
                }
                if ($this->isUniqueViolation($e)) {
                    throw new SlotUnavailableException($slotId);
                }
                throw $e;
            }
        });
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'uniq_active_booking_per_slot');
    }
}
