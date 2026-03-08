<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;
use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    public function __construct(
        private SettingsService $settings,
    ) {}

    /**
     * Sync templates from Meta Graph API.
     *
     * @throws \RuntimeException
     */
    public function syncFromMeta(): int
    {
        ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

        $response = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates");

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to sync templates: '.($response->json('error.message') ?? 'Unknown error'));
        }

        $templates = $response->json('data', []);
        $count = 0;

        foreach ($templates as $template) {
            WhatsAppTemplate::updateOrCreate(
                ['name' => $template['name'], 'language' => $template['language']],
                [
                    'category' => strtolower($template['category']),
                    'status' => $template['status'],
                    'components' => $template['components'] ?? [],
                    'meta_template_id' => $template['id'] ?? null,
                    'last_synced_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Get all approved templates.
     *
     * @return Collection<int, WhatsAppTemplate>
     */
    public function getApproved(): Collection
    {
        return WhatsAppTemplate::approved()->get();
    }

    /**
     * Submit a local template to Meta for approval.
     *
     * @throws \RuntimeException
     */
    public function submitToMeta(WhatsAppTemplate $template): void
    {
        ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

        $hasDocumentHeader = collect($template->components ?? [])
            ->contains(fn ($c) => ($c['type'] ?? '') === 'HEADER' && ($c['format'] ?? '') === 'DOCUMENT');

        $documentHandle = null;
        if ($hasDocumentHeader) {
            $documentHandle = $this->uploadSampleDocument($accessToken, $apiVersion);
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
                'name' => $template->name,
                'language' => $template->language,
                'category' => strtoupper($template->category),
                'components' => $this->mapComponentsForMeta($template->components ?? [], $documentHandle),
            ]);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message') ?? 'Unknown error';
            $errorDetail = $response->json('error.error_user_msg') ?? '';
            $fullError = $errorDetail ? "{$errorMessage} — {$errorDetail}" : $errorMessage;

            throw new \RuntimeException("Failed to submit template to Meta: {$fullError}");
        }

        $template->update([
            'meta_template_id' => $response->json('id'),
            'status' => $response->json('status', 'PENDING'),
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Update an existing template on Meta.
     *
     * @throws \RuntimeException
     */
    public function updateOnMeta(WhatsAppTemplate $template): void
    {
        if (! $template->meta_template_id) {
            throw new \RuntimeException('Template has not been submitted to Meta yet');
        }

        ['accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

        $hasDocumentHeader = collect($template->components ?? [])
            ->contains(fn ($c) => ($c['type'] ?? '') === 'HEADER' && ($c['format'] ?? '') === 'DOCUMENT');

        $documentHandle = null;
        if ($hasDocumentHeader) {
            $documentHandle = $this->uploadSampleDocument($accessToken, $apiVersion);
        }

        $response = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$template->meta_template_id}", [
                'components' => $this->mapComponentsForMeta($template->components ?? [], $documentHandle),
                'category' => strtoupper($template->category),
            ]);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message') ?? 'Unknown error';
            $errorDetail = $response->json('error.error_user_msg') ?? '';
            $fullError = $errorDetail ? "{$errorMessage} — {$errorDetail}" : $errorMessage;

            throw new \RuntimeException("Failed to update template on Meta: {$fullError}");
        }

        $template->update(['last_synced_at' => now()]);
    }

    /**
     * Delete a template from Meta and locally.
     *
     * @throws \RuntimeException
     */
    public function deleteFromMeta(WhatsAppTemplate $template): void
    {
        if (! $template->meta_template_id) {
            throw new \RuntimeException('Template has not been submitted to Meta yet');
        }

        ['wabaId' => $wabaId, 'accessToken' => $accessToken, 'apiVersion' => $apiVersion] = $this->getMetaCredentials();

        $response = Http::withToken($accessToken)
            ->delete("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
                'name' => $template->name,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to delete template from Meta: '.($response->json('error.message') ?? 'Unknown error'));
        }

        $template->delete();
    }

    /**
     * Get Meta API credentials or throw.
     *
     * @return array{wabaId: string, accessToken: string, apiVersion: string}
     *
     * @throws \RuntimeException
     */
    private function getMetaCredentials(): array
    {
        $wabaId = $this->settings->get('meta_waba_id');
        $accessToken = $this->settings->get('meta_access_token');
        $apiVersion = $this->settings->get('meta_api_version', 'v21.0');

        if (! $wabaId || ! $accessToken) {
            throw new \RuntimeException('Meta WABA ID and access token are required');
        }

        return compact('wabaId', 'accessToken', 'apiVersion');
    }

    /**
     * Upload a sample PDF to Meta via Resumable Upload API for DOCUMENT header examples.
     *
     * @throws \RuntimeException
     */
    private function uploadSampleDocument(string $accessToken, string $apiVersion): string
    {
        // Create a minimal valid PDF as sample
        $samplePdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF";
        $fileLength = strlen($samplePdf);

        $appId = $this->settings->get('meta_app_id');

        if (! $appId) {
            throw new \RuntimeException('Meta App ID is required for uploading document samples. Please set it in Settings > WhatsApp.');
        }

        // Step 1: Create upload session
        $sessionResponse = Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$appId}/uploads", [
                'file_length' => $fileLength,
                'file_type' => 'application/pdf',
                'file_name' => 'sample_certificate.pdf',
            ]);

        if (! $sessionResponse->successful()) {
            Log::error('Meta upload session failed', ['response' => $sessionResponse->json()]);

            throw new \RuntimeException('Failed to create upload session: '.($sessionResponse->json('error.message') ?? 'Unknown error'));
        }

        $uploadSessionId = $sessionResponse->json('id');

        // Step 2: Upload the file data
        $uploadResponse = Http::withToken($accessToken)
            ->withHeaders([
                'file_offset' => '0',
            ])
            ->withBody($samplePdf, 'application/pdf')
            ->post("https://graph.facebook.com/{$apiVersion}/{$uploadSessionId}");

        if (! $uploadResponse->successful()) {
            Log::error('Meta file upload failed', ['response' => $uploadResponse->json()]);

            throw new \RuntimeException('Failed to upload sample document: '.($uploadResponse->json('error.message') ?? 'Unknown error'));
        }

        $handle = $uploadResponse->json('h');

        if (! $handle) {
            throw new \RuntimeException('Meta upload did not return a file handle');
        }

        return $handle;
    }

    /**
     * Map local components to Meta Graph API format.
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private function mapComponentsForMeta(array $components, ?string $documentHandle = null): array
    {
        return array_map(function (array $component) use ($documentHandle) {
            $mapped = ['type' => $component['type']];

            if ($component['type'] === 'HEADER') {
                $mapped['format'] = $component['format'] ?? 'TEXT';

                if ($mapped['format'] === 'TEXT' && ! empty($component['text'])) {
                    $mapped['text'] = $component['text'];
                }

                if ($mapped['format'] === 'DOCUMENT' && $documentHandle) {
                    $mapped['example'] = [
                        'header_handle' => [$documentHandle],
                    ];
                }
            } elseif ($component['type'] === 'BODY') {
                $mapped['text'] = $component['text'] ?? '';

                // Meta requires example values for body variables
                preg_match_all('/\{\{(\d+)\}\}/', $mapped['text'], $matches);
                if (! empty($matches[1])) {
                    $exampleValues = array_map(fn ($n) => "example_value_{$n}", $matches[1]);
                    $mapped['example'] = [
                        'body_text' => [$exampleValues],
                    ];
                }
            } elseif ($component['type'] === 'FOOTER') {
                $mapped['text'] = $component['text'] ?? '';
            } else {
                $mapped = $component;
            }

            return $mapped;
        }, $components);
    }
}
