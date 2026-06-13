<?php

use App\Models\TaskCategory;
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
        if (Schema::hasTable('task_categories')) {
            return;
        }

        Schema::create('task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    /**
     * Seed a handful of starter categories so the grouped view is useful immediately.
     */
    private function seedDefaults(): void
    {
        if (TaskCategory::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'General', 'color' => '#6366f1'],
            ['name' => 'Follow-up', 'color' => '#0ea5e9'],
            ['name' => 'Administrative', 'color' => '#f59e0b'],
            ['name' => 'Recruitment', 'color' => '#10b981'],
            ['name' => 'Finance', 'color' => '#ef4444'],
            ['name' => 'IT / Operations', 'color' => '#8b5cf6'],
        ];

        foreach ($defaults as $index => $category) {
            TaskCategory::query()->create([
                ...$category,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_categories');
    }
};
