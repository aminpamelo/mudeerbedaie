<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_lead_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('slate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('cekbot_lead_categories')->insert([
            ['name' => 'Baru', 'color' => 'blue', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Berminat', 'color' => 'amber', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Follow-up', 'color' => 'violet', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Deal', 'color' => 'emerald', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_lead_categories');
    }
};
