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
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->constrained('tms_projects')->nullOnDelete()->after('category_id');
            $table->unsignedInteger('estimated_minutes')->nullable()->after('deadline');
            $table->unsignedInteger('actual_minutes')->nullable()->after('estimated_minutes');
            $table->date('start_date')->nullable()->after('actual_minutes');
            $table->unsignedInteger('sort_order')->default(0)->after('start_date');
            $table->boolean('is_recurring')->default(false)->after('sort_order');
            $table->foreignId('recurring_config_id')->nullable()->constrained('task_recurring_configs')->nullOnDelete()->after('is_recurring');
            $table->string('approval_status')->nullable()->after('recurring_config_id');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedInteger('points')->default(0)->after('approved_at');
            $table->json('tags')->nullable()->after('points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['recurring_config_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'project_id', 'estimated_minutes', 'actual_minutes', 'start_date',
                'sort_order', 'is_recurring', 'recurring_config_id', 'approval_status',
                'approved_by', 'approved_at', 'points', 'tags',
            ]);
        });
    }
};
