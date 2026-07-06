<?php

namespace App\Enums;

/**
 * Booking lifecycle and the single source of truth for valid transitions.
 *
 *   pending ──► confirmed ──► completed
 *      │            │
 *      └──► cancelled ◄──┘
 *
 * `cancelled` and `completed` are terminal. Only `pending` and `confirmed`
 * are "active" and therefore hold the slot (see the unique index in the
 * bookings migration).
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** Active bookings occupy their slot. */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /** Terminal states have no outgoing transitions. */
    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }

    /** @return array<int, self> States reachable from the current one. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Cancelled, self::Completed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
