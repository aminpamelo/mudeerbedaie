<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            // Optional QR / bank-transfer poster sent with the transfer details.
            $table->string('bank_image')->nullable()->after('bank_details');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            $table->dropColumn('bank_image');
        });
    }
};
