<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Console\Command;

class CekbotSyncWebhooks extends Command
{
    protected $signature = 'cekbot:sync-webhooks';

    protected $description = 'Register the Cekbot inbound webhook on every WAHA session (backfill existing numbers)';

    public function handle(WahaSessionManager $waha): int
    {
        $url = config('cekbot.webhook_url');

        if (blank($url)) {
            $this->error('CEKBOT_WEBHOOK_URL is not set. Add it to .env first, e.g. https://your-domain/api/cekbot/webhook');

            return self::FAILURE;
        }

        if (! $waha->isConfigured()) {
            $this->error('WAHA is not configured (missing server URL).');

            return self::FAILURE;
        }

        $applied = 0;
        $failed = 0;

        foreach ($waha->listSessions() as $session) {
            $name = $session['name'] ?? null;

            if (! $name) {
                continue;
            }

            try {
                if ($waha->setWebhook($name)) {
                    $this->line("  ✓ {$name}");
                    $applied++;
                }
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$name}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->info("Applied webhook to {$applied} session(s) → {$url}".($failed ? " ({$failed} failed)" : ''));

        return self::SUCCESS;
    }
}
