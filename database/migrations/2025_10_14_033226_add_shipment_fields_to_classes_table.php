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
        Schema::table('classes', function (Blueprint $table) {
            $table->boolean('enable_document_shipment')->default(false)->after('notes');
            $table->enum('shipment_frequency', ['monthly', 'per_session', 'one_time', 'custom'])->nullable()->after('enable_document_shipment');
            $table->date('shipment_start_date')->nullable()->after('shipment_frequency');
            $table->foreignId('shipment_product_id')->nullable()->constrained('products')->nullOnDelete()->after('shipment_start_date');
            $table->foreignId('shipment_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('shipment_product_id');
            $table->integer('shipment_quantity_per_student')->nullable()->after('shipment_warehouse_id');
            $table->text('shipment_notes')->nullable()->after('shipment_quantity_per_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['shipment_product_id']);
            $table->dropForeign(['shipment_warehouse_id']);
            $table->dropColumn([
                'enable_document_shipment',
                'shipment_frequency',
                'shipment_start_date',
                'shipment_product_id',
                'shipment_warehouse_id',
                'shipment_quantity_per_student',
                'shipment_notes',
            ]);
        });
    }
};
