<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a slot is already held by another active booking. Rendered as
 * HTTP 409 Conflict — the correct status for a lost race on a contended resource.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(public readonly int $slotId)
    {
        parent::__construct("Slot {$slotId} is no longer available.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'This slot has just been taken. Please choose another.',
            'error' => 'slot_unavailable',
            'slot_id' => $this->slotId,
        ], 409);
    }
}
