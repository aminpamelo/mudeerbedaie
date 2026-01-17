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
        // Add visual builder fields to class_notification_settings
        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->json('design_json')->nullable()->after('custom_content');
            $table->longText('html_content')->nullable()->after('design_json');
            $table->string('editor_type')->default('text')->after('html_content');
        });

        // Create attachments table for class notification settings
        Schema::create('class_notification_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_notification_setting_id')
                ->constrained('class_notification_settings')
                ->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->string('disk')->default('public');
            $table->boolean('embed_in_email')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('class_notification_setting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_notification_attachments');

        Schema::table('class_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['design_json', 'html_content', 'editor_type']);
        });
    }
};
