<?php

namespace App\Jobs\Funnel;

use App\Models\CustomDomain;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupFailedDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(CloudflareCustomHostnameService $cloudflare): void
    {
        $failedDomains = CustomDomain::where('verification_status', 'failed')
            ->where('updated_at', '<', now()->subDays(7))
            ->get();

        foreach ($failedDomains as $domain) {
            try {
                if ($domain->cloudflare_hostname_id) {
                    $cloudflare->deleteHostname($domain->cloudflare_hostname_id);
                }

                $domain->delete();

                Log::info('Cleaned up failed custom domain', [
                    'domain' => $domain->domain,
                    'funnel_id' => $domain->funnel_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to cleanup custom domain', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
