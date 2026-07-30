<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a fulfillment nature (physical / digital / external_system) plus a
     * generic metadata bag. External-system products store their provisioning
     * link under metadata['provisioning'] = {external_system_id, plan}, mirroring
     * FunnelProduct.settings['provisioning'].
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('fulfillment_type', 30)->default('physical')->index()->after('type');

            if (! Schema::hasColumn('products', 'metadata')) {
                $table->json('metadata')->nullable()->after('dimensions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('fulfillment_type');

            if (Schema::hasColumn('products', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
