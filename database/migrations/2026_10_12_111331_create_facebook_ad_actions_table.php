<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facebook_ad_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('manual'); // manual | rule | launch
            $table->string('object_type', 20); // campaign | adset | ad
            $table->string('object_id', 40);
            $table->string('object_name')->nullable();
            $table->string('action', 30); // status | budget | launch
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->boolean('dry_run')->default(false);
            $table->boolean('success')->default(true);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['facebook_ad_rule_id', 'object_id', 'created_at'], 'fb_ad_actions_rule_object_idx');
            $table->index(['facebook_ad_account_id', 'created_at'], 'fb_ad_actions_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_actions');
    }
};
