<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Slot */
class SlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Controllers eager-load `activeBooking` so availability is computed
        // without an extra query per slot (no N+1). If it wasn't loaded, fall
        // back to a direct check.
        $available = $this->relationLoaded('activeBooking')
            ? $this->activeBooking === null
            : $this->isAvailable();

        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'available' => $available,
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
        ];
    }
}
