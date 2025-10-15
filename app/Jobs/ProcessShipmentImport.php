<?php

namespace App\Jobs;

use App\Models\ClassDocumentShipmentItem;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessShipmentImport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $shipmentId,
        public string $filePath,
        public int $userId,
        public string $matchBy = 'name',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if file exists
            if (! file_exists($this->filePath)) {
                throw new \Exception("CSV file not found at path: {$this->filePath}");
            }

            $file = fopen($this->filePath, 'r');

            if ($file === false) {
                throw new \Exception('Unable to open CSV file');
            }

            // Skip header row
            $header = fgetcsv($file);

            $imported = 0;
            $updated = 0;
            $errors = [];

            while (($row = fgetcsv($file)) !== false) {
                // Expected CSV format: Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At
                if (count($row) < 11) {
                    continue; // Skip malformed rows
                }

                $studentName = trim($row[0]);
                $phone = trim($row[1]);
                $addressLine1 = trim($row[2]);
                $addressLine2 = trim($row[3]);
                $city = trim($row[4]);
                $state = trim($row[5]);
                $postcode = trim($row[6]);
                $country = trim($row[7]);
                $trackingNumber = trim($row[10]);

                if (empty($trackingNumber) || $trackingNumber === '-') {
                    continue; // Skip rows without tracking number
                }

                // Find the shipment item based on match_by parameter
                $item = null;
                $matchValue = '';

                if ($this->matchBy === 'phone') {
                    if (empty($phone)) {
                        $errors[] = "Row {$imported}: Phone number is empty, cannot match";

                        continue;
                    }

                    $item = ClassDocumentShipmentItem::query()
                        ->where('class_document_shipment_id', $this->shipmentId)
                        ->whereHas('student', function ($q) use ($phone) {
                            $q->where('phone', $phone);
                        })
                        ->first();

                    $matchValue = $phone;
                } else {
                    // Default: match by name
                    if (empty($studentName)) {
                        $errors[] = "Row {$imported}: Student name is empty, cannot match";

                        continue;
                    }

                    $item = ClassDocumentShipmentItem::query()
                        ->where('class_document_shipment_id', $this->shipmentId)
                        ->whereHas('student.user', function ($q) use ($studentName) {
                            $q->where('name', $studentName);
                        })
                        ->first();

                    $matchValue = $studentName;
                }

                if ($item) {
                    // Update tracking number
                    $item->update([
                        'tracking_number' => $trackingNumber,
                    ]);

                    // Update student address if provided in CSV
                    if ($item->student && ! empty($addressLine1)) {
                        $addressData = [];

                        if (! empty($addressLine1)) {
                            $addressData['address_line_1'] = $addressLine1;
                        }
                        if (! empty($addressLine2)) {
                            $addressData['address_line_2'] = $addressLine2;
                        }
                        if (! empty($city)) {
                            $addressData['city'] = $city;
                        }
                        if (! empty($state)) {
                            $addressData['state'] = $state;
                        }
                        if (! empty($postcode)) {
                            $addressData['postcode'] = $postcode;
                        }
                        if (! empty($country)) {
                            $addressData['country'] = $country;
                        }

                        if (! empty($addressData)) {
                            $item->student->update($addressData);
                        }
                    }

                    $updated++;
                } else {
                    $errors[] = "Student not found ({$this->matchBy}): {$matchValue}";
                }

                $imported++;

                // Update progress in cache
                Cache::put("shipment_import_{$this->shipmentId}_{$this->userId}_progress", [
                    'imported' => $imported,
                    'updated' => $updated,
                    'status' => 'processing',
                ], now()->addMinutes(10));
            }

            fclose($file);

            // Store final result in cache
            Cache::put("shipment_import_{$this->shipmentId}_{$this->userId}_result", [
                'imported' => $imported,
                'updated' => $updated,
                'errors' => array_slice($errors, 0, 10), // Limit to first 10 errors
                'status' => 'completed',
            ], now()->addMinutes(30));

            // Clean up uploaded file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }

            Log::info("Shipment import completed: {$imported} rows imported, {$updated} tracking numbers updated");
        } catch (\Exception $e) {
            Log::error("Shipment import failed: {$e->getMessage()}");

            // Store error in cache
            Cache::put("shipment_import_{$this->shipmentId}_{$this->userId}_result", [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ], now()->addMinutes(30));

            throw $e;
        }
    }
}
