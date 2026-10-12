<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which Landing Library template this step's page was copied from.
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->foreignId('landing_page_template_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landing_page_template_id');
        });
    }
};
