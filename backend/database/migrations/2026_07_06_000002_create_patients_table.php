<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();

            // Email is the patient's identity in this simplified system: booking
            // requests carry an email and we upsert the patient by it.
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
