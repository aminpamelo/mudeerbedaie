<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE holidays MODIFY type VARCHAR(255) NOT NULL');
        } else {
            // SQLite: rename → add new col → copy data → drop old
            Schema::table('holidays', fn (Blueprint $t) => $t->renameColumn('type', 'type_old'));
            Schema::table('holidays', fn (Blueprint $t) => $t->string('type')->default('public'));
            DB::statement('UPDATE holidays SET type = type_old');
            Schema::table('holidays', fn (Blueprint $t) => $t->dropColumn('type_old'));
        }

        // Rename existing 'national' values to 'public' for consistency
        DB::table('holidays')->where('type', 'national')->update(['type' => 'public']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename 'public' back to 'national'
        DB::table('holidays')->where('type', 'public')->update(['type' => 'national']);

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY type ENUM('national', 'state') NOT NULL");
        } else {
            Schema::table('holidays', fn (Blueprint $t) => $t->renameColumn('type', 'type_old'));
            Schema::table('holidays', function (Blueprint $t) {
                $t->enum('type', ['national', 'state'])->default('national');
            });
            DB::statement('UPDATE holidays SET type = type_old');
            Schema::table('holidays', fn (Blueprint $t) => $t->dropColumn('type_old'));
        }
    }
};
