<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AllOrderXlsxParser
{
    /**
     * Column letter → header name mapping for All Order xlsx (as exported by TikTok Shop).
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'A' => 'Order ID',
        'B' => 'Order Status',
        'C' => 'Order Substatus',
        'D' => 'Cancelation/Return Type',
        'E' => 'Normal or Pre-order',
        'F' => 'SKU ID',
        'G' => 'Seller SKU',
        'H' => 'Product Name',
        'I' => 'Variation',
        'J' => 'Quantity',
        'K' => 'Sku Quantity of return',
        'L' => 'SKU Unit Original Price',
        'M' => 'SKU Subtotal Before Discount',
        'N' => 'SKU Platform Discount',
        'O' => 'SKU Seller Discount',
        'P' => 'SKU Subtotal After Discount',
        'Q' => 'Shipping Fee After Discount',
        'R' => 'Original Shipping Fee',
        'S' => 'Shipping Fee Seller Discount',
        'T' => 'Shipping Fee Platform Discount',
        'U' => 'Payment platform discount',
        'V' => 'Taxes',
        'W' => 'Order Amount',
        'X' => 'Order Refund Amount',
        'Y' => 'Created Time',
        'Z' => 'Paid Time',
        'AA' => 'RTS Time',
        'AB' => 'Shipped Time',
        'AC' => 'Delivered Time',
        'AD' => 'Cancelled Time',
        'AE' => 'Cancel By',
        'AF' => 'Cancel Reason',
        'AG' => 'Fulfillment Type',
        'AH' => 'Warehouse Name',
        'AI' => 'Tracking ID',
        'AJ' => 'Delivery Option',
        'AK' => 'Shipping Provider Name',
        'AL' => 'Buyer Message',
        'AM' => 'Buyer Username',
        'AN' => 'Recipient',
        'AO' => 'Phone #',
        'AP' => 'Zipcode',
        'AQ' => 'Country',
        'AR' => 'State',
        'AS' => 'Post Town',
        'AT' => 'Detail Address',
        'AU' => 'Additional address information',
        'AV' => 'Payment Method',
        'AW' => 'Weight(kg)',
        'AX' => 'Product Category',
        'AY' => 'Package ID',
        'AZ' => 'Seller Note',
        'BA' => 'Checked Status',
        'BB' => 'Checked Marked by',
    ];

    /**
     * Parse a TikTok All Order xlsx file into typed associative rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $headerRowIndex = $this->findHeaderRow($sheet);
        if ($headerRowIndex === null) {
            return [];
        }

        // TikTok's export has a human-readable description row immediately after the header,
        // so data starts at headerRow + 2.
        $dataStart = $headerRowIndex + 2;
        $highestRow = $sheet->getHighestRow();

        $rows = [];
        for ($r = $dataStart; $r <= $highestRow; $r++) {
            $raw = $this->readRow($sheet, $r);

            if ($this->isBlankRow($raw)) {
                continue;
            }

            $rows[] = $this->mapRow($raw);
        }

        return $rows;
    }

    /**
     * Locate the header row by matching "Order ID" in column A.
     */
    private function findHeaderRow(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 20);

        for ($r = 1; $r <= $highestRow; $r++) {
            $value = $sheet->getCell('A'.$r)->getValue();
            if (is_string($value) && trim($value) === 'Order ID') {
                return $r;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function readRow(Worksheet $sheet, int $rowIndex): array
    {
        $out = [];

        foreach (self::COLUMNS as $col => $header) {
            $out[$header] = $sheet->getCell($col.$rowIndex)->getValue();
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function isBlankRow(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function mapRow(array $raw): array
    {
        return [
            'tiktok_order_id' => $this->stringOrNull($raw['Order ID'] ?? null),
            'order_status' => $this->stringOrNull($raw['Order Status'] ?? null),
            'order_substatus' => $this->stringOrNull($raw['Order Substatus'] ?? null),
            'cancelation_return_type' => $this->stringOrNull($raw['Cancelation/Return Type'] ?? null),
            'created_time' => $this->parseDateTime($raw['Created Time'] ?? null),
            'paid_time' => $this->parseDateTime($raw['Paid Time'] ?? null),
            'rts_time' => $this->parseDateTime($raw['RTS Time'] ?? null),
            'shipped_time' => $this->parseDateTime($raw['Shipped Time'] ?? null),
            'delivered_time' => $this->parseDateTime($raw['Delivered Time'] ?? null),
            'cancelled_time' => $this->parseDateTime($raw['Cancelled Time'] ?? null),
            'order_amount_myr' => $this->toFloat($raw['Order Amount'] ?? null) ?? 0.0,
            'order_refund_amount_myr' => $this->toFloat($raw['Order Refund Amount'] ?? null) ?? 0.0,
            'payment_method' => $this->stringOrNull($raw['Payment Method'] ?? null),
            'fulfillment_type' => $this->stringOrNull($raw['Fulfillment Type'] ?? null),
            'product_category' => $this->stringOrNull($raw['Product Category'] ?? null),
            'raw_row_json' => array_map(fn ($v) => $v === null ? null : (string) $v, $raw),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * Parse "18/04/2026 23:45:00" → Carbon. Returns null on failure.
     */
    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = trim((string) $value);
        $parsed = Carbon::createFromFormat('d/m/Y H:i:s', $string);

        return $parsed !== false ? $parsed : null;
    }
}
