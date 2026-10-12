<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facebook_ad_connections', function (Blueprint $table) {
            // True once the token is confirmed to carry ads_management (write access).
            $table->boolean('can_manage_ads')->default(false)->after('status_message');
            $table->json('permissions')->nullable()->after('can_manage_ads');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_ad_connections', function (Blueprint $table) {
            $table->dropColumn(['can_manage_ads', 'permissions']);
        });
    }
};
