<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PIC-only upload of a TikTok export xlsx. Only Live Analysis is accepted as
 * of Task 9 — the legacy `order_list` (All Orders) flow has been retired in
 * favour of webhook-driven ProductOrder ingestion. The file is stored
 * untouched and a TiktokReportImport row is created; actual parsing happens
 * asynchronously in ProcessTiktokImportJob.
 */
class UploadTiktokReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'in:live_analysis'],
            'platform_account_id' => ['required', 'integer', 'exists:platform_accounts,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ];
    }
}
