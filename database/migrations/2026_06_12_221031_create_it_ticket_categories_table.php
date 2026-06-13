<?php

use App\Models\ItTicketCategory;
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
        if (Schema::hasTable('it_ticket_categories')) {
            return;
        }

        Schema::create('it_ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    /**
     * Seed a handful of starter categories so the board is useful immediately.
     */
    private function seedDefaults(): void
    {
        if (ItTicketCategory::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Frontend', 'color' => '#3b82f6'],
            ['name' => 'Backend', 'color' => '#8b5cf6'],
            ['name' => 'Infrastructure', 'color' => '#f59e0b'],
            ['name' => 'Design', 'color' => '#ec4899'],
            ['name' => 'DevOps', 'color' => '#10b981'],
            ['name' => 'Database', 'color' => '#06b6d4'],
        ];

        foreach ($defaults as $index => $category) {
            ItTicketCategory::query()->create([
                ...$category,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('it_ticket_categories');
    }
};
