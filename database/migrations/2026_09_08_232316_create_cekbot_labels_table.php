<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cekbot_labels', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('color')->default('slate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('cekbot_labels')->insert([
            ['key' => 'baru', 'name' => 'Baru', 'color' => 'blue', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pending', 'name' => 'Pending', 'color' => 'amber', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'selesai', 'name' => 'Selesai', 'color' => 'green', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'penting', 'name' => 'Penting', 'color' => 'red', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cekbot_labels');
    }
};
