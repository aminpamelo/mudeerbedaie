<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The pivot `live_host_platform_account` was originally created with a
     * composite primary key (user_id, platform_account_id) and no id column.
     * Later FK relationships (live_sessions.live_host_platform_account_id,
     * live_time_slots.live_host_platform_account_id) require a single
     * surrogate id to reference, so we promote the pivot here.
     *
     * SQLite cannot ALTER TABLE to add a PRIMARY KEY autoincrement column,
     * so we rebuild the pivot on SQLite while using a native ALTER on MySQL.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('live_host_platform_account', 'id')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                // Drop the composite primary key, add surrogate id, and
                // keep the (user_id, platform_account_id) pairing as unique.
                DB::statement('ALTER TABLE live_host_platform_account DROP PRIMARY KEY');
                DB::statement('ALTER TABLE live_host_platform_account ADD COLUMN id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST');
                DB::statement('ALTER TABLE live_host_platform_account ADD UNIQUE KEY live_host_platform_account_user_platform_unique (user_id, platform_account_id)');
            } else {
                // SQLite: snapshot rows, recreate table with id + unique pair,
                // restore rows.
                $existing = DB::table('live_host_platform_account')->get();

                Schema::drop('live_host_platform_account');

                Schema::create('live_host_platform_account', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
                    $table->timestamps();

                    $table->unique(['user_id', 'platform_account_id'], 'live_host_platform_account_user_platform_unique');
                });

                foreach ($existing as $row) {
                    DB::table('live_host_platform_account')->insert([
                        'user_id' => $row->user_id,
                        'platform_account_id' => $row->platform_account_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }
        }

        Schema::table('live_host_platform_account', function (Blueprint $table) {
            $table->string('creator_handle')->nullable()->after('platform_account_id');
            $table->string('creator_platform_user_id')->nullable()->after('creator_handle');
            $table->boolean('is_primary')->default(false)->after('creator_platform_user_id');
            $table->index('creator_platform_user_id');
        });

        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->foreignId('live_host_platform_account_id')->nullable()
                ->after('platform_account_id')
                ->constrained('live_host_platform_account', 'id')
                ->nullOnDelete();
        });

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->foreignId('live_host_platform_account_id')->nullable()
                ->after('platform_account_id')
                ->constrained('live_host_platform_account', 'id')
                ->nullOnDelete();
            $table->decimal('gmv_amount', 12, 2)->nullable()->after('duration_minutes');
            $table->decimal('gmv_adjustment', 12, 2)->default(0)->after('gmv_amount');
            $table->string('gmv_source')->default('manual')->after('gmv_adjustment');
            $table->timestamp('gmv_locked_at')->nullable()->after('gmv_source');
            $table->json('commission_snapshot_json')->nullable()->after('gmv_locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('live_host_platform_account_id');
            $table->dropColumn([
                'gmv_amount',
                'gmv_adjustment',
                'gmv_source',
                'gmv_locked_at',
                'commission_snapshot_json',
            ]);
        });

        Schema::table('live_time_slots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('live_host_platform_account_id');
        });

        Schema::table('live_host_platform_account', function (Blueprint $table) {
            $table->dropIndex(['creator_platform_user_id']);
            $table->dropColumn(['creator_handle', 'creator_platform_user_id', 'is_primary']);
        });

        if (Schema::hasColumn('live_host_platform_account', 'id')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE live_host_platform_account DROP INDEX live_host_platform_account_user_platform_unique');
                DB::statement('ALTER TABLE live_host_platform_account DROP PRIMARY KEY');
                DB::statement('ALTER TABLE live_host_platform_account DROP COLUMN id');
                DB::statement('ALTER TABLE live_host_platform_account ADD PRIMARY KEY (user_id, platform_account_id)');
            } else {
                $existing = DB::table('live_host_platform_account')->get();

                Schema::drop('live_host_platform_account');

                Schema::create('live_host_platform_account', function (Blueprint $table) {
                    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
                    $table->timestamps();
                    $table->primary(['user_id', 'platform_account_id']);
                });

                foreach ($existing as $row) {
                    DB::table('live_host_platform_account')->insert([
                        'user_id' => $row->user_id,
                        'platform_account_id' => $row->platform_account_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }
        }
    }
};
