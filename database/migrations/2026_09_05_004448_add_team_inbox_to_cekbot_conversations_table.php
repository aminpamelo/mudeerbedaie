<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('handed_over_by')->constrained('users')->nullOnDelete();
            $table->json('labels')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('labels');
        });
    }
};
