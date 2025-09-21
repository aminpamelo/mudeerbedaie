<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStripeSubscriptionUpdated implements ShouldQueue
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

            Log::info('Processing subscription updated webhook', [
                'webhook_event_id' => $this->webhookEvent->id,
                'stripe_subscription_id' => $subscriptionId,
                'status' => $this->stripeSubscription['status'] ?? 'unknown',
            ]);

            // Find the enrollment with this subscription ID
            $enrollment = \App\Models\Enrollment::where('stripe_subscription_id', $subscriptionId)->first();

            if (! $enrollment) {
                Log::warning('No enrollment found for subscription update', [
                    'stripe_subscription_id' => $subscriptionId,
                ]);
                $this->webhookEvent->markAsProcessed();

                return;
            }

            // Update subscription status
            $newStatus = $this->stripeSubscription['status'];
            $oldStatus = $enrollment->subscription_status;

            $enrollment->updateSubscriptionStatus($newStatus);

            // Update collection status if pause_collection is present
            if (isset($this->stripeSubscription['pause_collection'])) {
                $pauseCollection = $this->stripeSubscription['pause_collection'];

                if ($pauseCollection && isset($pauseCollection['behavior']) && $pauseCollection['behavior'] === 'void') {
                    // Collection is paused
                    if (! $enrollment->isCollectionPaused()) {
                        $enrollment->pauseCollection();
                        Log::info('Collection status updated to paused via webhook', [
                            'enrollment_id' => $enrollment->id,
                            'subscription_id' => $subscriptionId,
                        ]);
                    }
                } else {
                    // Collection is active (pause_collection is null or has different behavior)
                    if ($enrollment->isCollectionPaused()) {
                        $enrollment->resumeCollection();
                        Log::info('Collection status updated to active via webhook', [
                            'enrollment_id' => $enrollment->id,
                            'subscription_id' => $subscriptionId,
                        ]);
                    }
                }
            }

            // Update next payment date based on subscription period
            if (in_array($newStatus, ['active', 'trialing']) && isset($this->stripeSubscription['current_period_end'])) {
                $nextPaymentDate = \Carbon\Carbon::createFromTimestamp($this->stripeSubscription['current_period_end'])->addDay();
                $enrollment->updateNextPaymentDate($nextPaymentDate);

                Log::info('Updated next payment date from subscription update', [
                    'enrollment_id' => $enrollment->id,
                    'next_payment_date' => $nextPaymentDate->toDateTimeString(),
                ]);
            } elseif (in_array($newStatus, ['canceled', 'incomplete_expired', 'past_due', 'unpaid'])) {
                // Clear next payment date for inactive subscriptions
                $enrollment->updateNextPaymentDate(null);

                Log::info('Cleared next payment date for inactive subscription', [
                    'enrollment_id' => $enrollment->id,
                    'status' => $newStatus,
                ]);
            }

            // Handle subscription cancellation details
            if (isset($this->stripeSubscription['cancel_at']) && $this->stripeSubscription['cancel_at']) {
                $cancelAt = \Carbon\Carbon::createFromTimestamp($this->stripeSubscription['cancel_at']);
                $enrollment->updateSubscriptionCancellation($cancelAt);
            } elseif (in_array($newStatus, ['canceled', 'incomplete_expired'])) {
                // If canceled but no cancel_at timestamp, set it to now
                $enrollment->updateSubscriptionCancellation(now());
            }

            // Update academic status based on subscription status changes
            $this->syncAcademicStatusFromSubscription($enrollment, $newStatus);

            Log::info('Updated enrollment subscription status', [
                'enrollment_id' => $enrollment->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'subscription_id' => $subscriptionId,
            ]);

            $this->webhookEvent->markAsProcessed();

        } catch (\Exception $e) {
            Log::error('Failed to process subscription updated webhook', [
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
