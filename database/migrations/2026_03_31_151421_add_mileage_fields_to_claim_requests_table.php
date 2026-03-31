<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('claim_requests', function (Blueprint $table) {
            $table->foreignId('vehicle_rate_id')->nullable()->after('claim_type_id')
                ->constrained('claim_type_vehicle_rates')->nullOnDelete();
            $table->decimal('distance_km', 10, 2)->nullable()->after('vehicle_rate_id');
            $table->string('origin')->nullable()->after('distance_km');
            $table->string('destination')->nullable()->after('origin');
            $table->string('trip_purpose')->nullable()->after('destination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_requests', function (Blueprint $table) {
            $table->dropForeign(['vehicle_rate_id']);
            $table->dropColumn([
                'vehicle_rate_id',
                'distance_km',
                'origin',
                'destination',
                'trip_purpose',
            ]);
        });
    }
};
