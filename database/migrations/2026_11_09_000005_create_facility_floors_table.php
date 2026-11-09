<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per floor/level of a facility, so users can see what is on each floor
        // and how that floor can be reached (elevator, ramp, stairs only...).
        Schema::create('facility_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('level');            // 0 = ground, 1 = second floor, -1 = basement
            $table->string('label', 60);              // e.g. "Ground floor"
            $table->string('access', 20);             // elevator | lift | ramp | ground | stairs
            $table->string('services')->nullable();   // what is on this floor
            $table->timestamps();

            $table->unique(['facility_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_floors');
    }
};
