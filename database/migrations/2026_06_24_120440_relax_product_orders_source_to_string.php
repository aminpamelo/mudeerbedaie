<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `source` column was a rigid MySQL enum that did not include the
     * storefront value (real cart orders fell back to the 'manual' default and
     * were mis-categorised as "Company"). Relax it to a plain string so new
     * sources can be added without an enum migration, and re-tag legacy
     * 'website' rows to the canonical 'storefront' value. SQLite already stores
     * this column as a varchar, so only the data re-tag runs there.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_orders MODIFY source VARCHAR(50) NOT NULL DEFAULT 'manual'");
        }

        DB::table('product_orders')->where('source', 'website')->update(['source' => 'storefront']);
    }

    public function down(): void
    {
        // Keep the column as a flexible string on rollback — restoring the old
        // enum would reject any storefront/website rows. Only undo the re-tag.
        DB::table('product_orders')->where('source', 'storefront')->update(['source' => 'website']);
    }
};
