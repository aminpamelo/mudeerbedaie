<?php

namespace App\Console\Commands;

use App\Models\ClassModel;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateClassShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'class:generate-shipments
                            {--class-id= : Specific class ID to generate shipment for}
                            {--month= : Month to generate shipments for (format: YYYY-MM, default: current month)}
                            {--dry-run : Preview what would be generated without actually creating shipments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate document shipments for classes with enabled document shipment feature';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting class document shipment generation...');

        $classId = $this->option('class-id');
        $month = $this->option('month');
        $dryRun = $this->option('dry-run');

        // Determine the period
        if ($month) {
            try {
                $periodStart = Carbon::parse($month)->startOfMonth();
            } catch (\Exception $e) {
                $this->error('Invalid month format. Use YYYY-MM (e.g., 2025-01)');

                return self::FAILURE;
            }
        } else {
            $periodStart = now()->startOfMonth();
        }

        $periodEnd = $periodStart->copy()->endOfMonth();

        $this->info("Period: {$periodStart->format('F Y')} ({$periodStart->toDateString()} to {$periodEnd->toDateString()})");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No shipments will be created');
        }

        // Get eligible classes
        $query = ClassModel::query()
            ->where('enable_document_shipment', true)
            ->whereNotNull('shipment_product_id')
            ->with(['activeStudents', 'shipmentProduct', 'shipmentWarehouse']);

        if ($classId) {
            $query->where('id', $classId);
        }

        $classes = $query->get();

        if ($classes->isEmpty()) {
            $this->warn('No eligible classes found for shipment generation.');

            return self::SUCCESS;
        }

        $this->info("Found {$classes->count()} eligible classes");
        $this->newLine();

        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($classes as $class) {
            $this->line("Processing: {$class->title} (ID: {$class->id})");

            // Check if shipment already exists
            if (! $class->canGenerateShipment($periodStart)) {
                $this->warn('  ⏭  Skipped - Shipment already exists for this period');
                $skipped++;

                continue;
            }

            // Check if class has active students
            $studentCount = $class->activeStudents->count();
            if ($studentCount === 0) {
                $this->warn('  ⏭  Skipped - No active students');
                $skipped++;

                continue;
            }

            // Check product stock if tracking
            if ($class->shipmentProduct && $class->shipmentProduct->shouldTrackQuantity()) {
                $requiredQty = $studentCount * ($class->shipment_quantity_per_student ?? 1);
                $availableStock = $class->shipmentProduct->getStockQuantity($class->shipment_warehouse_id);

                if ($availableStock < $requiredQty) {
                    $this->error("  ✗ Error - Insufficient stock (Required: {$requiredQty}, Available: {$availableStock})");
                    $errors++;

                    continue;
                }
            }

            if ($dryRun) {
                $this->info("  ✓ Would create shipment for {$studentCount} students");
                $generated++;
            } else {
                try {
                    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);

                    if ($shipment) {
                        $this->info("  ✓ Created shipment #{$shipment->shipment_number} for {$studentCount} students");
                        $generated++;
                    } else {
                        $this->error('  ✗ Failed to create shipment');
                        $errors++;
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Error: {$e->getMessage()}");
                    $errors++;
                }
            }
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->info("Generated: {$generated}");
        $this->warn("Skipped: {$skipped}");
        $this->error("Errors: {$errors}");

        return self::SUCCESS;
    }
}
