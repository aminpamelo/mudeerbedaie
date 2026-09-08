<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_products', function (Blueprint $table) {
            $table->longText('knowledge')->nullable()->after('description');
            $table->json('faqs')->nullable()->after('knowledge');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_products', function (Blueprint $table) {
            $table->dropColumn(['knowledge', 'faqs']);
        });
    }
};
