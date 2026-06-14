<?php

use App\Models\ItTicketType;
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
        if (Schema::hasTable('it_ticket_types')) {
            return;
        }

        Schema::create('it_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 20)->default('#6366f1');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->seedDefaults();
    }

    /**
     * Seed the original built-in types so existing tickets map cleanly and the
     * board keeps its familiar Bug / Feature / Task / Improvement set.
     */
    private function seedDefaults(): void
    {
        if (ItTicketType::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Bug', 'color' => '#ef4444'],
            ['name' => 'Feature', 'color' => '#22c55e'],
            ['name' => 'Task', 'color' => '#3b82f6'],
            ['name' => 'Improvement', 'color' => '#f59e0b'],
        ];

        foreach ($defaults as $index => $type) {
            ItTicketType::query()->create([
                ...$type,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('it_ticket_types');
    }
};
