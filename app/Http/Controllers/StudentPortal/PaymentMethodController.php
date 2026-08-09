<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $methods = $user->paymentMethods()
            ->active()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'brand' => $m->card_details['brand'] ?? 'card',
                'last4' => $m->card_details['last4'] ?? '****',
                'exp_month' => $m->card_details['exp_month'] ?? '**',
                'exp_year' => $m->card_details['exp_year'] ?? '**',
                'is_default' => $m->is_default,
                'is_expired' => $m->is_expired,
                'created_at' => $m->created_at->format('M d, Y'),
            ]);

        $stripeKey = '';
        try {
            $stripe = app(StripeService::class);
            if ($stripe->isConfigured()) {
                $stripeKey = $stripe->getPublishableKey();
            }
        } catch (\Exception $e) {
            // Stripe not configured
        }

        return Inertia::render('PaymentMethods', [
            'paymentMethods' => $methods,
            'stripePublishableKey' => $stripeKey,
        ]);
    }
}
