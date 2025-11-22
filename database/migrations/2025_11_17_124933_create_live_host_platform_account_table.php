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
        Schema::create('live_host_platform_account', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'platform_account_id']);
        });

        // Migrate existing data from platform_accounts.user_id to the pivot table
        DB::table('platform_accounts')
            ->whereNotNull('user_id')
            ->get()
            ->each(function ($account) {
                DB::table('live_host_platform_account')->insert([
                    'user_id' => $account->user_id,
                    'platform_account_id' => $account->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_host_platform_account');
    }
};
