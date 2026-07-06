<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a patient already holds an active booking on the requested date.
 * Rendered as HTTP 422 — the request is well-formed but violates a business rule.
 */
class DailyBookingLimitException extends RuntimeException
{
    public function __construct(public readonly string $date)
    {
        parent::__construct(
            "You already have a booking on {$date}. Cancel it first to pick a different time."
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'daily_booking_limit',
            'date' => $this->date,
        ], 422);
    }
}
