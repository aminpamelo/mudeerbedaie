<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->timestamp('handed_over_at')->nullable()->after('archived_at');
            $table->foreignId('handed_over_by')->nullable()->after('handed_over_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handed_over_by');
            $table->dropColumn('handed_over_at');
        });
    }
};
