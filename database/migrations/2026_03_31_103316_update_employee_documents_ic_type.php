<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing ic_front and ic_back records to 'ic'
        DB::table('employee_documents')
            ->whereIn('document_type', ['ic_front', 'ic_back'])
            ->update(['document_type' => 'ic']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this - ic_front/ic_back distinction is lost
    }
};
