<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeSubscriptionDeleted implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public array $stripeSubscription
    ) {}

    public function handle(StripeService $stripeService): void
    {
        try {
            // Extract subscription ID - should now be properly converted by StripeService
            $subscriptionId = $this->stripeSubscription['id'] ?? null;

            if (! $subscriptionId) {
                Log::error('Subscription ID not found in webhook data', [
                    'webhook_event_id' => $this->webhookEvent->id,
                    'subscription_data_keys' => array_keys($this->stripeSubscription),
                ]);
                $this->webhookEvent->markAsFailed('Subscription ID not found in webhook data');

                return;
            }

            Log::info('Processing subscription deleted webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_subscription_id' => $subscriptionId,
            ]);

            // Find the enrollment with this subscription ID
            $enrollment = \App\Models\Enrollment::where('stripe_subscription_id', $subscriptionId)->first();

            if (! $enrollment) {
                Log::warning('No enrollment found for subscription deletion', [
                    'stripe_subscription_id' => $subscriptionId,
                ]);
                $this->webhookEvent->markAsProcessed();

                return;
            }

            // Update subscription status to canceled
            $oldSubscriptionStatus = $enrollment->subscription_status;
            $enrollment->updateSubscriptionStatus('canceled');

            // Clear subscription-related fields since subscription is deleted
            $enrollment->update([
                'subscription_cancel_at' => now(),
                'next_payment_date' => null,
            ]);

            // Update academic status if appropriate (same logic as subscription updated)
            $this->syncAcademicStatusFromSubscription($enrollment, 'canceled');

            Log::info('Processed subscription deletion', [
                'enrollment_id' => $enrollment->id,
                'old_subscription_status' => $oldSubscriptionStatus,
                'new_subscription_status' => 'canceled',
                'academic_status' => $enrollment->academic_status->value,
            ]);

            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process subscription deleted webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'error' => $e->getMessage(),
            ]);

            $this->webhookEvent->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync academic status based on subscription status changes.
     * Only updates between ACTIVE ↔ SUSPENDED for payment-related issues.
     * Never overrides COMPLETED or WITHDRAWN (final academic decisions).
     */
    private function syncAcademicStatusFromSubscription(\App\Models\Enrollment $enrollment, string $subscriptionStatus): void
    {
        $currentAcademicStatus = $enrollment->academic_status;

        // Never override final academic decisions
        if (in_array($currentAcademicStatus, [\App\AcademicStatus::COMPLETED, \App\AcademicStatus::WITHDRAWN])) {
            Log::info('Skipping academic status sync - final status', [
                'enrollment_id' => $enrollment->id,
                'current_academic_status' => $currentAcademicStatus->value,
                'subscription_status' => $subscriptionStatus,
            ]);

            return;
        }

        $newAcademicStatus = null;

        // Determine new academic status based on subscription status
        switch ($subscriptionStatus) {
            case 'active':
            case 'trialing':
                // Reactivate if currently suspended due to payment issues
                if ($currentAcademicStatus === \App\AcademicStatus::SUSPENDED) {
                    $newAcademicStatus = \App\AcademicStatus::ACTIVE;
                }
                break;

            case 'canceled':
            case 'incomplete_expired':
            case 'unpaid':
                // Suspend if currently active due to payment issues
                if ($currentAcademicStatus === \App\AcademicStatus::ACTIVE) {
                    $newAcademicStatus = \App\AcademicStatus::SUSPENDED;
                }
                break;

            case 'past_due':
                // Keep as active for now - past_due might recover
                // Could change this based on business preference
                break;

            default:
                Log::info('Unknown subscription status for academic sync', [
                    'enrollment_id' => $enrollment->id,
                    'subscription_status' => $subscriptionStatus,
                ]);
                break;
        }

        // Update academic status if needed
        if ($newAcademicStatus && $newAcademicStatus !== $currentAcademicStatus) {
            $enrollment->update(['academic_status' => $newAcademicStatus]);

            Log::info('Academic status synchronized from subscription', [
                'enrollment_id' => $enrollment->id,
                'old_academic_status' => $currentAcademicStatus->value,
                'new_academic_status' => $newAcademicStatus->value,
                'subscription_status' => $subscriptionStatus,
                'reason' => 'automatic_subscription_sync',
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->webhookEvent->markAsFailed($exception->getMessage());
    }
}
