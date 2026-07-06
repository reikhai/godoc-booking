<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = ['slot_id', 'patient_id', 'status'];

    protected $casts = [
        'status' => BookingStatus::class,
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Keep the two guard columns in sync with the lifecycle on every save:
     *
     *  - `active_slot_id` = slot_id while active, NULL once terminal
     *    → UNIQUE(active_slot_id) enforces one active booking per slot.
     *  - `active_date` = the slot's calendar date while active, NULL once
     *    terminal → UNIQUE(patient_id, active_date) enforces one active
     *    booking per patient per day.
     *
     * Both invariants therefore hold at the DB level no matter which code path
     * writes a booking.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            $status = $booking->status instanceof BookingStatus
                ? $booking->status
                : BookingStatus::from($booking->status);

            if ($status->isActive()) {
                $booking->active_slot_id = $booking->slot_id;
                $booking->active_date = $booking->slot?->start_at?->toDateString();
            } else {
                $booking->active_slot_id = null;
                $booking->active_date = null;
            }
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Move the booking to a new state, enforcing the state machine and stamping
     * the corresponding timestamp. The caller is responsible for wrapping this
     * in a transaction if it needs to be atomic with other work.
     *
     * @throws InvalidBookingTransitionException when the transition is not allowed.
     */
    public function transitionTo(BookingStatus $target): self
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidBookingTransitionException($this->status, $target);
        }

        $this->status = $target;

        match ($target) {
            BookingStatus::Confirmed => $this->confirmed_at = Carbon::now(),
            BookingStatus::Cancelled => $this->cancelled_at = Carbon::now(),
            BookingStatus::Completed => $this->completed_at = Carbon::now(),
            default => null,
        };

        $this->save();

        return $this;
    }

    public function confirm(): self
    {
        return $this->transitionTo(BookingStatus::Confirmed);
    }

    public function cancel(): self
    {
        return $this->transitionTo(BookingStatus::Cancelled);
    }

    public function complete(): self
    {
        return $this->transitionTo(BookingStatus::Completed);
    }
}
