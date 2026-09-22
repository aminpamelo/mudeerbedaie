<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution of a purchase back to an email broadcast. One row per order (a
 * purchase is credited to at most one campaign — last touch), recording whether
 * the buyer actually clicked the email ("clicked") or merely received it within
 * the window ("assisted"). Order is polymorphic so both ProductOrder (shop) and
 * course/subscription Order can be attributed. Index names kept explicit + short
 * for the MySQL 64-char limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_log_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_type'); // 'product' | 'course'
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('attribution', 20)->default('assisted'); // 'clicked' | 'assisted'
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique(['order_type', 'order_id'], 'bcast_conv_order_unique');
            $table->index(['broadcast_id', 'converted_at'], 'bcast_conv_broadcast_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_conversions');
    }
};
