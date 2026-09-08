<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->foreignId('lead_category_id')->nullable()->after('labels')
                ->constrained('cekbot_lead_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_category_id');
        });
    }
};
