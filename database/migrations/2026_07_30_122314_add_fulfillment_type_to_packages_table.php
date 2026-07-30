<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a fulfillment nature (physical / digital / external_system) to packages.
     * External-system packages store their provisioning link under the existing
     * metadata['provisioning'] = {external_system_id, plan} bag.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->string('fulfillment_type', 30)->default('physical')->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('fulfillment_type');
        });
    }
};
