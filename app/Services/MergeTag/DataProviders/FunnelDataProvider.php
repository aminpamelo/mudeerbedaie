<?php

declare(strict_types=1);

namespace App\Services\MergeTag\DataProviders;

use App\Models\Funnel;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Services\MergeTag\DataProviderInterface;

class FunnelDataProvider implements DataProviderInterface
{
    public function getValue(string $field, array $context): ?string
    {
        // Get funnel from context (directly or through session/order)
        $funnel = $this->getFunnel($context);
        $step = $this->getStep($context);
        $session = $context['funnel_session'] ?? $context['session'] ?? null;

        return match ($field) {
            'name' => $funnel?->name,
            'url' => $this->getFunnelUrl($funnel),
            'step_name' => $step?->name ?? $this->getStepNameFromSession($session),
            'step_url' => $this->getStepUrl($funnel, $step, $session),
            'description' => $funnel?->description,
            'slug' => $funnel?->slug,
            default => null,
        };
    }

    protected function getFunnel(array $context): ?Funnel
    {
        // Direct funnel in context
        if (isset($context['funnel']) && $context['funnel'] instanceof Funnel) {
            return $context['funnel'];
        }

        // From session
        $session = $context['funnel_session'] ?? $context['session'] ?? null;
        if ($session instanceof FunnelSession && $session->funnel) {
            return $session->funnel;
        }

        // From order
        $order = $context['funnel_order'] ?? null;
        if ($order && $order->funnel) {
            return $order->funnel;
        }

        // From step
        $step = $context['funnel_step'] ?? $context['step'] ?? null;
        if ($step instanceof FunnelStep && $step->funnel) {
            return $step->funnel;
        }

        return null;
    }

    protected function getStep(array $context): ?FunnelStep
    {
        // Direct step in context
        if (isset($context['funnel_step']) && $context['funnel_step'] instanceof FunnelStep) {
            return $context['funnel_step'];
        }

        if (isset($context['step']) && $context['step'] instanceof FunnelStep) {
            return $context['step'];
        }

        // From session
        $session = $context['funnel_session'] ?? $context['session'] ?? null;
        if ($session instanceof FunnelSession) {
            return $session->currentStep ?? $session->funnelStep ?? null;
        }

        return null;
    }

    protected function getStepNameFromSession(?FunnelSession $session): ?string
    {
        if (! $session) {
            return null;
        }

        return $session->currentStep?->name ?? $session->funnelStep?->name ?? null;
    }

    protected function getFunnelUrl(?Funnel $funnel): ?string
    {
        if (! $funnel) {
            return null;
        }

        $baseUrl = config('app.url');

        // Use slug if available, otherwise use ID
        $identifier = $funnel->slug ?? $funnel->id;

        return "{$baseUrl}/f/{$identifier}";
    }

    protected function getStepUrl(?Funnel $funnel, ?FunnelStep $step, ?FunnelSession $session): ?string
    {
        if (! $funnel) {
            return null;
        }

        $funnelUrl = $this->getFunnelUrl($funnel);

        if ($step) {
            $stepSlug = $step->slug ?? $step->id;

            return "{$funnelUrl}/{$stepSlug}";
        }

        // Try to get step from session
        if ($session) {
            $currentStep = $session->currentStep ?? $session->funnelStep;
            if ($currentStep) {
                $stepSlug = $currentStep->slug ?? $currentStep->id;

                return "{$funnelUrl}/{$stepSlug}";
            }
        }

        return $funnelUrl;
    }
}
