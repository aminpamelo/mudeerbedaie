<?php

declare(strict_types=1);

namespace App\Services\LiveHost\Tiktok;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LiveAnalysisXlsxParser
{
    /**
     * Expected header columns (in order) for a Live Analysis export.
     *
     * @var array<int, string>
     */
    private const HEADERS = [
        'Creator ID',
        'Creator',
        'Nickname',
        'Launched Time',
        'Duration',
        'LIVE gross merchandise value (RM)',
        'Products added',
        'Different Products Sold',
        'Created SKU orders',
        'LIVE SKU orders',
        'LIVE items sold',
        'Unique customers',
        'Average Price (RM)',
        'Click-to-order rate (LIVE)',
        'LIVE attributed GMV (RM)',
        'Viewers',
        'Views',
        'Average viewing duration (LIVE streams)',
        'Comments',
        'Shares',
        'LIVE likes',
        'New followers (Creator video)',
        'Product Impressions',
        'Product Clicks',
        'CTR',
    ];

    /**
     * Parse a TikTok Live Analysis xlsx file into typed associative rows.
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

        $rows = [];
        $highestRow = $sheet->getHighestRow();

        for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {
            $raw = $this->readRow($sheet, $r);

            if ($this->isBlankRow($raw)) {
                continue;
            }

            $rows[] = $this->mapRow($raw);
        }

        return $rows;
    }

    /**
     * Locate the header row by matching "Creator ID" in column A.
     */
    private function findHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 20);

        for ($r = 1; $r <= $highestRow; $r++) {
            $value = $sheet->getCell('A'.$r)->getValue();
            if (is_string($value) && trim($value) === 'Creator ID') {
                return $r;
            }
        }

        return null;
    }

    /**
     * Read a single row's 25 columns into an associative array keyed by header name.
     *
     * @return array<string, mixed>
     */
    private function readRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $rowIndex): array
    {
        $out = [];
        $col = 'A';

        foreach (self::HEADERS as $header) {
            $value = $sheet->getCell($col.$rowIndex)->getValue();
            $out[$header] = $value;
            $col++;
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
     * Map a raw row into a typed associative array.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function mapRow(array $raw): array
    {
        return [
            'tiktok_creator_id' => $this->stringOrNull($raw['Creator ID'] ?? null),
            'creator_display_name' => $this->stringOrNull($raw['Creator'] ?? null),
            'creator_nickname' => $this->stringOrNull($raw['Nickname'] ?? null),
            'launched_time' => $this->parseLaunchedTime($raw['Launched Time'] ?? null),
            'duration_seconds' => $this->parseDuration($raw['Duration'] ?? null),
            'gmv_myr' => $this->toFloat($raw['LIVE gross merchandise value (RM)'] ?? null),
            'live_attributed_gmv_myr' => $this->toFloat($raw['LIVE attributed GMV (RM)'] ?? null),
            'products_added' => $this->toInt($raw['Products added'] ?? null),
            'products_sold' => $this->toInt($raw['Different Products Sold'] ?? null),
            'sku_orders' => $this->toInt($raw['Created SKU orders'] ?? null),
            'live_sku_orders' => $this->toInt($raw['LIVE SKU orders'] ?? null),
            'items_sold' => $this->toInt($raw['LIVE items sold'] ?? null),
            'unique_customers' => $this->toInt($raw['Unique customers'] ?? null),
            'avg_price_myr' => $this->toFloat($raw['Average Price (RM)'] ?? null),
            'click_to_order_rate' => $this->parsePercent($raw['Click-to-order rate (LIVE)'] ?? null),
            'viewers' => $this->toInt($raw['Viewers'] ?? null),
            'views' => $this->toInt($raw['Views'] ?? null),
            'avg_view_duration_sec' => $this->toInt($raw['Average viewing duration (LIVE streams)'] ?? null),
            'comments' => $this->toInt($raw['Comments'] ?? null),
            'shares' => $this->toInt($raw['Shares'] ?? null),
            'likes' => $this->toInt($raw['LIVE likes'] ?? null),
            'new_followers' => $this->toInt($raw['New followers (Creator video)'] ?? null),
            'product_impressions' => $this->toInt($raw['Product Impressions'] ?? null),
            'product_clicks' => $this->toInt($raw['Product Clicks'] ?? null),
            'ctr' => $this->parsePercent($raw['CTR'] ?? null),
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

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Parse "8.33%" → 8.33. Returns null for null/empty.
     */
    private function parsePercent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stripped = str_replace(['%', ',', ' '], '', (string) $value);
        if ($stripped === '') {
            return null;
        }

        return (float) $stripped;
    }

    /**
     * Parse "2026/04/18/ 22:14" → Carbon instance. Returns null on failure.
     */
    private function parseLaunchedTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = trim((string) $value);
        $parsed = Carbon::createFromFormat('Y/m/d/ H:i', $string);

        return $parsed !== false ? $parsed : null;
    }

    /**
     * Parse duration strings like "1h 40min", "45min", "2h" into total seconds.
     */
    private function parseDuration(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;
        $seconds = 0;
        $matched = false;

        if (preg_match('/(\d+)\s*h/i', $string, $matches)) {
            $seconds += (int) $matches[1] * 3600;
            $matched = true;
        }

        if (preg_match('/(\d+)\s*min/i', $string, $matches)) {
            $seconds += (int) $matches[1] * 60;
            $matched = true;
        }

        if (preg_match('/(\d+)\s*s(?!\w)/i', $string, $matches)) {
            $seconds += (int) $matches[1];
            $matched = true;
        }

        return $matched ? $seconds : null;
    }
}
