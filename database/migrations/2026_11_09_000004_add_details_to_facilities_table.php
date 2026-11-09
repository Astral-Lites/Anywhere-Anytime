<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // More accessibility features (BP 344 style checklist)
            $table->boolean('has_elevator')->default(false);
            $table->boolean('has_tactile_paths')->default(false);
            $table->boolean('has_accessible_signage')->default(false);
            $table->boolean('has_wide_doorways')->default(false);
            $table->boolean('has_handrails')->default(false);
            $table->boolean('has_priority_lane')->default(false);

            // Practical details
            $table->string('hours', 120)->nullable();
            $table->string('contact', 60)->nullable();
            $table->string('surface', 40)->nullable();   // approach path surface
            $table->string('slope', 40)->nullable();     // approach slope, as reported
            $table->json('amenities')->nullable();       // list of amenity keys
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn([
                'has_elevator', 'has_tactile_paths', 'has_accessible_signage',
                'has_wide_doorways', 'has_handrails', 'has_priority_lane',
                'hours', 'contact', 'surface', 'slope', 'amenities',
            ]);
        });
    }
};
