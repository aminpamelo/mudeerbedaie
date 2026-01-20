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
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // For SQLite, we need to recreate the table to change enum to string
            // This removes the CHECK constraint that restricts type values

            // Drop temp table if it exists from previous failed attempt
            Schema::dropIfExists('agents_temp');

            // Step 1: Create a temporary table with string type instead of enum
            Schema::create('agents_temp', function (Blueprint $table) {
                $table->id();
                $table->string('agent_code', 50);
                $table->string('name');
                $table->string('type', 20)->default('agent'); // Changed from enum to string
                $table->string('pricing_tier', 20)->default('standard')->nullable();
                $table->decimal('commission_rate', 5, 2)->nullable();
                $table->decimal('credit_limit', 12, 2)->default(0)->nullable();
                $table->boolean('consignment_enabled')->default(false)->nullable();
                $table->string('company_name')->nullable();
                $table->string('registration_number', 100)->nullable();
                $table->string('contact_person')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->json('address')->nullable();
                $table->string('payment_terms', 100)->nullable();
                $table->json('bank_details')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            // Step 2: Check which columns exist in the old table
            $columns = Schema::getColumnListing('agents');

            // Build the column list for INSERT
            $commonColumns = ['id', 'agent_code', 'name', 'type', 'company_name', 'registration_number',
                'contact_person', 'email', 'phone', 'address', 'payment_terms',
                'bank_details', 'is_active', 'notes', 'created_at', 'updated_at'];

            // Add optional columns if they exist
            $optionalColumns = ['pricing_tier', 'commission_rate', 'credit_limit', 'consignment_enabled'];

            $existingColumns = [];
            foreach ($commonColumns as $col) {
                if (in_array($col, $columns)) {
                    $existingColumns[] = $col;
                }
            }

            foreach ($optionalColumns as $col) {
                if (in_array($col, $columns)) {
                    $existingColumns[] = $col;
                }
            }

            $columnList = implode(', ', $existingColumns);

            // Step 3: Copy data from old table to new table
            DB::statement("INSERT INTO agents_temp ({$columnList}) SELECT {$columnList} FROM agents");

            // Step 4: Drop old table
            Schema::drop('agents');

            // Step 5: Rename temp table to agents
            Schema::rename('agents_temp', 'agents');

            // Step 6: Recreate indexes
            Schema::table('agents', function (Blueprint $table) {
                $table->unique('agent_code');
                $table->index(['is_active', 'type']);
                $table->index('pricing_tier');
            });
        }
        // For MySQL/PostgreSQL, the previous migration should have already handled it
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a one-way migration - we don't want to re-add the CHECK constraint
    }
};
