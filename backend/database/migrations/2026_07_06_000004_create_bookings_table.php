<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])
                ->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('patient_id');

            // --- Database-level double-booking guarantee ---------------------
            //
            // `active_slot_id` mirrors slot_id while the booking is active
            // (pending/confirmed) and is NULL once it is terminal
            // (cancelled/completed). The Booking model keeps it in sync on every
            // save (see Booking::booted()).
            //
            // The UNIQUE index below enforces "at most one active booking per
            // slot" at the storage-engine level. MySQL does not index NULLs
            // uniquely, so any number of cancelled/completed bookings for the
            // same slot may coexist — only the single active one is constrained.
            // This is the last line of defence behind the SELECT ... FOR UPDATE
            // lock in BookingService.
            //
            // (We keep this as an app-maintained column rather than a STORED
            // generated column: InnoDB refuses to add a generated column that
            // derives from a foreign-key column such as slot_id.)
            $table->unsignedBigInteger('active_slot_id')->nullable();
            $table->unique('active_slot_id', 'uniq_active_booking_per_slot');

            // --- One booking per patient per day -----------------------------
            //
            // Same pattern: `active_date` holds the slot's calendar date while
            // the booking is active and is NULL once terminal, so the composite
            // UNIQUE index enforces "a patient holds at most one active booking
            // per day" even under concurrent requests for different slots
            // (which the per-slot row lock alone would not serialise).
            $table->date('active_date')->nullable();
            $table->unique(['patient_id', 'active_date'], 'uniq_active_patient_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
