<?php

namespace App\Jobs\Funnel;

use App\Models\CustomDomain;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyCustomDomains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(CloudflareCustomHostnameService $cloudflare): void
    {
        $domains = CustomDomain::where('type', 'custom')
            ->whereIn('verification_status', ['pending'])
            ->whereNotNull('cloudflare_hostname_id')
            ->get();

        foreach ($domains as $domain) {
            try {
                $status = $cloudflare->getHostnameStatus($domain->cloudflare_hostname_id);

                $domain->update([
                    'verification_status' => $this->mapVerificationStatus($status['status']),
                    'ssl_status' => $this->mapSslStatus($status['ssl_status']),
                    'verification_errors' => $status['verification_errors'] ?: null,
                    'last_checked_at' => now(),
                    'verified_at' => $status['status'] === 'active' ? now() : $domain->verified_at,
                    'ssl_active_at' => $status['ssl_status'] === 'active' ? now() : $domain->ssl_active_at,
                ]);

                // Clear cache when status changes
                Cache::forget("custom_domain:custom:{$domain->domain}");

                Log::info('Custom domain verification check', [
                    'domain' => $domain->domain,
                    'verification_status' => $status['status'],
                    'ssl_status' => $status['ssl_status'],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to verify custom domain', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function mapVerificationStatus(string $cfStatus): string
    {
        return match ($cfStatus) {
            'active' => 'active',
            'pending' => 'pending',
            'moved', 'deleted' => 'deleting',
            default => 'failed',
        };
    }

    private function mapSslStatus(string $cfStatus): string
    {
        return match ($cfStatus) {
            'active' => 'active',
            'pending_validation', 'pending_issuance', 'pending_deployment', 'initializing' => 'pending',
            default => 'failed',
        };
    }
}
