<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private StripeService $stripeService) {}

    /**
     * Create a payment intent for an invoice
     */
    public function createPayment(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            // Verify user can pay this invoice
            if (! $this->canUserPayInvoice($invoice)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Validate request
            $request->validate([
                'payment_method_id' => 'nullable|exists:payment_methods,id',
                'save_payment_method' => 'boolean',
            ]);

            $paymentMethodId = $request->input('payment_method_id');
            $paymentMethod = null;

            if ($paymentMethodId) {
                $paymentMethod = PaymentMethod::where('id', $paymentMethodId)
                    ->where('user_id', Auth::id())
                    ->first();

                if (! $paymentMethod) {
                    return response()->json(['error' => 'Payment method not found'], 404);
                }
            }

            // Create payment intent
            $payment = $this->stripeService->createPaymentIntent($invoice, $paymentMethod);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'client_secret' => $payment->stripe_payment_intent_id ?
                    $this->getClientSecret($payment->stripe_payment_intent_id) : null,
                'publishable_key' => $this->stripeService->getPublishableKey(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create payment', [
                'invoice_id' => $invoice->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm a payment intent
     */
    public function confirmPayment(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            // Verify user can pay this invoice
            if (! $this->canUserPayInvoice($invoice)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'payment_intent_id' => 'required|string',
                'payment_method_id' => 'nullable|string',
            ]);

            $result = $this->stripeService->confirmPaymentIntent(
                $request->input('payment_intent_id'),
                $request->input('payment_method_id')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to confirm payment', [
                'invoice_id' => $invoice->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to confirm payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new payment method
     */
    public function storePaymentMethod(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'payment_method_id' => 'required|string', // Stripe payment method ID
                'set_as_default' => 'boolean',
            ]);

            $user = Auth::user();

            // Create payment method using StripeService
            $token = $request->input('payment_method_id');
            $paymentMethod = $this->stripeService->createPaymentMethodFromToken($user, $token);

            // Set as default if requested or if it's the first payment method
            if ($request->boolean('set_as_default') || $user->paymentMethods()->count() === 1) {
                $paymentMethod->setAsDefault();
            }

            return response()->json([
                'success' => true,
                'payment_method' => $paymentMethod->load('user'),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store payment method', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to save payment method: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a payment method
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Delete from Stripe and database
            $deleted = $this->stripeService->deletePaymentMethod($paymentMethod);

            if ($deleted) {
                return response()->json(['success' => true]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete payment method',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', [
                'payment_method_id' => $paymentMethod->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete payment method: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set a payment method as default
     */
    public function setDefaultPaymentMethod(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify ownership
            if ($paymentMethod->user_id !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $paymentMethod->setAsDefault();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to set default payment method', [
                'payment_method_id' => $paymentMethod->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to set default payment method: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if user can pay an invoice
     */
    private function canUserPayInvoice(Invoice $invoice): bool
    {
        $user = Auth::user();

        // Admin can pay any invoice
        if ($user->isAdmin()) {
            return true;
        }

        // Student can only pay their own invoices
        if ($user->isStudent()) {
            return $invoice->student_id === $user->id;
        }

        return false;
    }

    /**
     * Get client secret from payment intent
     */
    private function getClientSecret(string $paymentIntentId): ?string
    {
        try {
            // This would retrieve the client secret from Stripe
            // For now, return a placeholder
            return $paymentIntentId.'_secret_placeholder';
        } catch (\Exception $e) {
            Log::error('Failed to get client secret', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ==================== Admin Methods for Managing Student Payment Methods ====================

    /**
     * Store a new payment method for a student (admin only)
     */
    public function adminStorePaymentMethod(Request $request, \App\Models\Student $student): JsonResponse
    {
        try {
            // Verify admin permissions
            if (! Auth::user()->isAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $request->validate([
                'payment_method_id' => 'required|string', // Stripe payment method ID
                'set_as_default' => 'boolean',
            ]);

            $user = $student->user;

            // Create payment method using StripeService
            $token = $request->input('payment_method_id');
            $paymentMethod = $this->stripeService->createPaymentMethodFromToken($user, $token);

            // Set as default if requested or if it's the first payment method
            if ($request->boolean('set_as_default') || $user->paymentMethods()->count() === 1) {
                $paymentMethod->setAsDefault();
            }

            // Log admin action
            Log::info('Admin added payment method for student', [
                'admin_id' => Auth::id(),
                'admin_name' => Auth::user()->name,
                'student_id' => $student->id,
                'student_name' => $user->name,
                'payment_method_id' => $paymentMethod->id,
            ]);

            return response()->json([
                'success' => true,
                'payment_method' => $paymentMethod->load('user'),
            ]);

        } catch (\Exception $e) {
            Log::error('Admin failed to store payment method for student', [
                'admin_id' => Auth::id(),
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to save payment method: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a student's payment method (admin only)
     */
    public function adminDeletePaymentMethod(\App\Models\Student $student, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify admin permissions
            if (! Auth::user()->isAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Verify the payment method belongs to the student
            if ($paymentMethod->user_id !== $student->user_id) {
                return response()->json(['error' => 'Payment method does not belong to this student'], 403);
            }

            // Delete from Stripe and database
            $deleted = $this->stripeService->deletePaymentMethod($paymentMethod);

            if ($deleted) {
                // Log admin action
                Log::info('Admin deleted payment method for student', [
                    'admin_id' => Auth::id(),
                    'admin_name' => Auth::user()->name,
                    'student_id' => $student->id,
                    'student_name' => $student->user->name,
                    'payment_method_id' => $paymentMethod->id,
                ]);

                return response()->json(['success' => true]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete payment method',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Admin failed to delete student payment method', [
                'admin_id' => Auth::id(),
                'student_id' => $student->id,
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete payment method: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set a student's payment method as default (admin only)
     */
    public function adminSetDefaultPaymentMethod(\App\Models\Student $student, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Verify admin permissions
            if (! Auth::user()->isAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Verify the payment method belongs to the student
            if ($paymentMethod->user_id !== $student->user_id) {
                return response()->json(['error' => 'Payment method does not belong to this student'], 403);
            }

            $paymentMethod->setAsDefault();

            // Log admin action
            Log::info('Admin set default payment method for student', [
                'admin_id' => Auth::id(),
                'admin_name' => Auth::user()->name,
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'payment_method_id' => $paymentMethod->id,
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Admin failed to set default payment method for student', [
                'admin_id' => Auth::id(),
                'student_id' => $student->id,
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to set default payment method: '.$e->getMessage(),
            ], 500);
        }
    }
}
