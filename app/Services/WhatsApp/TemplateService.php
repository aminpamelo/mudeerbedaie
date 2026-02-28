<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;
use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

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
        $wabaId = $this->settings->get('meta_waba_id');
        $accessToken = $this->settings->get('meta_access_token');
        $apiVersion = $this->settings->get('meta_api_version', 'v21.0');

        if (! $wabaId || ! $accessToken) {
            throw new \RuntimeException('Meta WABA ID and access token are required for template sync');
        }

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
}
