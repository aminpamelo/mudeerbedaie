<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Click-to-WhatsApp ads that start a flow, stored as [{id, name}].
     */
    public function up(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            $table->json('trigger_ads')->nullable()->after('trigger_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            $table->dropColumn('trigger_ads');
        });
    }
};
