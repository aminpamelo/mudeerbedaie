<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            // Scripted opening sequence sent verbatim when the flow triggers:
            // [{type: 'text', text} | {type: 'image', path, caption}]
            $table->json('opening_messages')->nullable()->after('welcome_message');
        });
    }

    public function down(): void
    {
        Schema::table('cekbot_flows', function (Blueprint $table) {
            $table->dropColumn('opening_messages');
        });
    }
};
