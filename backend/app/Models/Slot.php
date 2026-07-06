<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Slot extends Model
{
    use HasFactory;

    protected $fillable = ['doctor_id', 'start_at', 'end_at'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The single active booking, if any. Availability is derived from bookings
     * rather than stored on the slot, so there is one source of truth.
     */
    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value]);
    }

    /** Slots with no active booking. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereDoesntHave('bookings', function (Builder $q) {
            $q->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value]);
        });
    }

    public function isAvailable(): bool
    {
        return ! $this->bookings()
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->exists();
    }
}
