<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the provider switch + official WhatsApp Cloud API credentials to each
     * Cekbot number. `provider` decides whether a number sends/receives through
     * WAHA (unofficial) or Meta's official Cloud API. Cloud credentials are kept
     * per-number because Cekbot is multi-number and each official number has its
     * own phone number id + token. Plain add-columns — MySQL + SQLite safe.
     */
    public function up(): void
    {
        Schema::table('cekbot_sessions', function (Blueprint $table) {
            $table->string('provider')->default('waha')->after('status');

            $table->string('phone_number_id')->nullable()->after('provider');
            $table->string('waba_id')->nullable()->after('phone_number_id');
            $table->text('access_token')->nullable()->after('waba_id');
            $table->text('app_secret')->nullable()->after('access_token');
            $table->string('verify_token')->nullable()->after('app_secret');
            $table->string('api_version')->nullable()->after('verify_token');

            $table->index(['provider', 'phone_number_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_sessions', function (Blueprint $table) {
            $table->dropIndex(['provider', 'phone_number_id']);
            $table->dropColumn([
                'provider',
                'phone_number_id',
                'waba_id',
                'access_token',
                'app_secret',
                'verify_token',
                'api_version',
            ]);
        });
    }
};
