<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();

            // The resident who submitted the facility.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('category');
            $table->string('address');

            // Accessibility features
            $table->boolean('has_ramp')->default(false);
            $table->boolean('has_accessible_entrance')->default(false);
            $table->boolean('has_accessible_restroom')->default(false);
            $table->boolean('has_accessible_parking')->default(false);
            $table->text('notes')->nullable();

            // Review: pending, approved, or rejected
            $table->string('status')->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
