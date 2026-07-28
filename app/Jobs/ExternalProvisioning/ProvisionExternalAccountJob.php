<?php

namespace App\Jobs\ExternalProvisioning;

use App\Models\ExternalProvisioningRequest;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class ProvisionExternalAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60; // seconds between retries

    public function __construct(
        public int $provisioningRequestId
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('external-provision-'.$this->provisioningRequestId),
        ];
    }

    // NOTE: do NOT add retryUntil() here — it overrides $tries and would retry
    // for hours against a down endpoint, overflowing the jobs.attempts tinyint.

    public function handle(ExternalProvisioningManager $manager): void
    {
        $manager->fulfill($this->provisioningRequestId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProvisionExternalAccountJob failed', [
            'provisioning_request_id' => $this->provisioningRequestId,
            'error' => $exception->getMessage(),
        ]);

        $request = ExternalProvisioningRequest::find($this->provisioningRequestId);

        if ($request && ! $request->isSucceeded()) {
            $request->markFailed($exception->getMessage());
        }
    }
}
