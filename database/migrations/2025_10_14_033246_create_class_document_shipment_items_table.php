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
        Schema::create('class_document_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_document_shipment_id')->constrained('class_document_shipments')->onDelete('cascade');
            $table->foreignId('class_student_id')->constrained('class_students')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('product_order_id')->nullable()->constrained('product_orders')->nullOnDelete();
            $table->foreignId('product_order_item_id')->nullable()->constrained('product_order_items')->nullOnDelete();
            $table->string('tracking_number')->nullable();
            $table->enum('status', ['pending', 'packed', 'shipped', 'delivered', 'failed', 'returned'])->default('pending');
            $table->integer('quantity')->default(1);
            $table->decimal('item_cost', 8, 2)->default(0);
            $table->decimal('shipping_cost', 8, 2)->default(0);
            $table->string('shipping_provider')->nullable();
            $table->string('shipping_address_line_1')->nullable();
            $table->string('shipping_address_line_2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_postcode')->nullable();
            $table->string('shipping_country')->default('Malaysia');
            $table->text('delivery_notes')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['class_document_shipment_id', 'student_id'], 'cdsi_shipment_student_idx');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_document_shipment_items');
    }
};
