<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE student_import_progress MODIFY class_id BIGINT UNSIGNED NULL');
        } else {
            $rows = DB::table('student_import_progress')->select('id', 'class_id')->get();

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->dropForeign(['class_id']);
                $table->renameColumn('class_id', 'class_id_old');
            });

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->foreignId('class_id')->nullable()->after('id')->constrained('classes')->onDelete('cascade');
            });

            foreach ($rows as $row) {
                DB::table('student_import_progress')->where('id', $row->id)->update(['class_id' => $row->class_id]);
            }

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->dropColumn('class_id_old');
            });
        }

        Schema::table('student_import_progress', function (Blueprint $table) {
            $table->string('type', 32)->default('class_enrollment')->after('user_id');
        });

        // Convert status ENUM -> VARCHAR(32) on MySQL so we can store 'cancelled'.
        // SQLite already stores it as varchar (no-op there).
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE student_import_progress MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('student_import_progress', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE student_import_progress SET status = 'failed' WHERE status NOT IN ('pending','processing','completed','failed')");
            DB::statement("ALTER TABLE student_import_progress MODIFY status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending'");
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE student_import_progress MODIFY class_id BIGINT UNSIGNED NOT NULL');
        } else {
            $rows = DB::table('student_import_progress')->select('id', 'class_id')->whereNotNull('class_id')->get();

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->dropForeign(['class_id']);
                $table->renameColumn('class_id', 'class_id_old');
            });

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->foreignId('class_id')->after('id')->constrained('classes')->onDelete('cascade');
            });

            foreach ($rows as $row) {
                DB::table('student_import_progress')->where('id', $row->id)->update(['class_id' => $row->class_id]);
            }

            Schema::table('student_import_progress', function (Blueprint $table) {
                $table->dropColumn('class_id_old');
            });
        }
    }
};
