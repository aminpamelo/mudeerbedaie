<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->string('verification_status', 20)
                ->default('pending')
                ->after('status');
            $table->foreignId('verified_by')
                ->nullable()
                ->after('verification_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')
                ->nullable()
                ->after('verified_by');
            $table->text('verification_notes')
                ->nullable()
                ->after('verified_at');

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_at', 'verification_notes']);
        });
    }
};
