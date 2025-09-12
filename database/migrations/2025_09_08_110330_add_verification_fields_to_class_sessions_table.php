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
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('allowance_amount');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->string('verifier_role')->nullable()->after('verified_by');

            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['verified_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['verified_at', 'status']);
            $table->dropColumn(['verified_at', 'verified_by', 'verifier_role']);
        });
    }
};
