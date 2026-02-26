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
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email')->nullable();
            $table->foreignId('salesperson_id')->constrained('users');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'bank_transfer'])->default('cash');
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['paid', 'pending'])->default('paid');
            $table->text('notes')->nullable();
            $table->datetime('sale_date');
            $table->timestamps();

            $table->index('sale_number');
            $table->index('salesperson_id');
            $table->index('sale_date');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
