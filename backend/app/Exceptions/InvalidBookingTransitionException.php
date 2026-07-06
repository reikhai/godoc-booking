<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a booking is asked to move to a state the state machine forbids
 * (e.g. confirming an already-cancelled booking). Rendered as HTTP 422.
 */
class InvalidBookingTransitionException extends RuntimeException
{
    public function __construct(
        public readonly BookingStatus $from,
        public readonly BookingStatus $to,
    ) {
        parent::__construct(
            "Cannot transition booking from {$from->value} to {$to->value}."
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_transition',
            'from' => $this->from->value,
            'to' => $this->to->value,
        ], 422);
    }
}
