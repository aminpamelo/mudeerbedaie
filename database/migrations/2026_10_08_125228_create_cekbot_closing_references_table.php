<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real closing conversations from the sales team, used by the Cekbot AI as
     * style references. flow_ids null/empty = applies to every flow.
     */
    public function up(): void
    {
        Schema::create('cekbot_closing_references', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('transcript');
            $table->text('notes')->nullable();
            $table->json('flow_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_closing_references');
    }
};
