<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamps();

            // A doctor cannot have two slots starting at the same instant.
            $table->unique(['doctor_id', 'start_at']);

            // Availability queries filter/sort by doctor + time.
            $table->index(['doctor_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
