<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('actual_live_record_id')->nullable()->constrained('actual_live_records')->nullOnDelete();
            $table->enum('action', ['verify_link', 'unverify', 'reject', 'link_changed']);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('gmv_snapshot', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
            // No updated_at — append-only.

            $table->index(['live_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_verification_events');
    }
};
