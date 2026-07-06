<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(
                fn ($s) => $s->value,
                $this->status->allowedTransitions(),
            ),
            'slot_id' => $this->slot_id,
            'patient_id' => $this->patient_id,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'slot' => new SlotResource($this->whenLoaded('slot')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
        ];
    }
}
