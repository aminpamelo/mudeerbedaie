<?php

use App\Http\Controllers\Api\ContactActivityController;
use App\Http\Controllers\Api\StudentTagController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\V1\AffiliateDashboardController;
use App\Http\Controllers\Api\V1\FunnelAffiliateController;
use App\Http\Controllers\Api\V1\FunnelAutomationController;
use App\Http\Controllers\Api\V1\FunnelCheckoutController;
use App\Http\Controllers\Api\V1\FunnelController;
use App\Http\Controllers\Api\V1\FunnelMediaController;
use App\Http\Controllers\Api\V1\FunnelOrderController;
use App\Http\Controllers\Api\V1\FunnelPixelController;
use App\Http\Controllers\Api\V1\FunnelProductController;
use App\Http\Controllers\Api\V1\FunnelStepController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// CRM Routes (Internal API - uses Sanctum session authentication)
Route::middleware(['auth:sanctum'])->prefix('crm')->group(function () {
    // Tags
    Route::apiResource('tags', TagController::class);
    Route::get('tags-stats', [TagController::class, 'stats'])->name('tags.stats');

    // Student Tags
    Route::get('students/{student}/tags', [StudentTagController::class, 'index'])->name('students.tags.index');
    Route::post('students/{student}/tags', [StudentTagController::class, 'store'])->name('students.tags.store');
    Route::delete('students/{student}/tags/{tag}', [StudentTagController::class, 'destroy'])->name('students.tags.destroy');
    Route::post('students/{student}/tags/sync', [StudentTagController::class, 'sync'])->name('students.tags.sync');

    // Contact Activities
    Route::get('students/{student}/activities', [ContactActivityController::class, 'index'])->name('students.activities.index');
    Route::post('students/{student}/activities', [ContactActivityController::class, 'store'])->name('students.activities.store');
    Route::get('activity-types', [ContactActivityController::class, 'types'])->name('activities.types');

    // Courses (for workflow builder)
    Route::get('courses', function () {
        return response()->json([
            'data' => \App\Models\Course::select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
        ]);
    })->name('crm.courses.index');

    // Classes (for workflow builder)
    Route::get('classes', function () {
        return response()->json([
            'data' => \App\Models\ClassModel::select('id', 'title', 'code')
                ->orderBy('title')
                ->get(),
        ]);
    })->name('crm.classes.index');
});

/*
|--------------------------------------------------------------------------
| Funnel Builder API Routes (V1)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Funnels
    Route::get('funnels', [FunnelController::class, 'index'])->name('api.funnels.index');
    Route::post('funnels', [FunnelController::class, 'store'])->name('api.funnels.store');
    Route::get('funnels/{uuid}', [FunnelController::class, 'show'])->name('api.funnels.show');
    Route::put('funnels/{uuid}', [FunnelController::class, 'update'])->name('api.funnels.update');
    Route::delete('funnels/{uuid}', [FunnelController::class, 'destroy'])->name('api.funnels.destroy');
    Route::post('funnels/{uuid}/duplicate', [FunnelController::class, 'duplicate'])->name('api.funnels.duplicate');
    Route::post('funnels/{uuid}/publish', [FunnelController::class, 'publish'])->name('api.funnels.publish');
    Route::post('funnels/{uuid}/unpublish', [FunnelController::class, 'unpublish'])->name('api.funnels.unpublish');
    Route::get('funnels/{uuid}/analytics', [FunnelController::class, 'analytics'])->name('api.funnels.analytics');

    // Funnel Orders
    Route::get('funnels/{uuid}/orders', [FunnelOrderController::class, 'index'])->name('api.funnels.orders.index');
    Route::get('funnels/{uuid}/orders/stats', [FunnelOrderController::class, 'stats'])->name('api.funnels.orders.stats');
    Route::get('funnels/{uuid}/carts', [FunnelOrderController::class, 'abandonedCarts'])->name('api.funnels.carts.index');

    // Funnel Automations
    Route::get('funnels/{funnelUuid}/automations', [FunnelAutomationController::class, 'index'])->name('api.funnels.automations.index');
    Route::post('funnels/{funnelUuid}/automations', [FunnelAutomationController::class, 'store'])->name('api.funnels.automations.store');
    Route::get('funnels/{funnelUuid}/automations/{automationId}', [FunnelAutomationController::class, 'show'])->name('api.funnels.automations.show');
    Route::put('funnels/{funnelUuid}/automations/{automationId}', [FunnelAutomationController::class, 'update'])->name('api.funnels.automations.update');
    Route::delete('funnels/{funnelUuid}/automations/{automationId}', [FunnelAutomationController::class, 'destroy'])->name('api.funnels.automations.destroy');
    Route::post('funnels/{funnelUuid}/automations/{automationId}/toggle', [FunnelAutomationController::class, 'toggleActive'])->name('api.funnels.automations.toggle');
    Route::post('funnels/{funnelUuid}/automations/{automationId}/duplicate', [FunnelAutomationController::class, 'duplicate'])->name('api.funnels.automations.duplicate');
    Route::get('funnels/{funnelUuid}/automations/{automationId}/logs', [FunnelAutomationController::class, 'logs'])->name('api.funnels.automations.logs');

    // Automation Merge Tag Variables
    Route::get('funnel-builder/variables', [FunnelAutomationController::class, 'variables'])->name('api.funnel-builder.variables');
    Route::get('funnel-builder/variables/all', [FunnelAutomationController::class, 'allVariables'])->name('api.funnel-builder.variables.all');

    // Funnel Steps
    Route::get('funnels/{funnelUuid}/steps', [FunnelStepController::class, 'index'])->name('api.funnels.steps.index');
    Route::post('funnels/{funnelUuid}/steps', [FunnelStepController::class, 'store'])->name('api.funnels.steps.store');
    Route::get('funnels/{funnelUuid}/steps/{stepId}', [FunnelStepController::class, 'show'])->name('api.funnels.steps.show');
    Route::put('funnels/{funnelUuid}/steps/{stepId}', [FunnelStepController::class, 'update'])->name('api.funnels.steps.update');
    Route::delete('funnels/{funnelUuid}/steps/{stepId}', [FunnelStepController::class, 'destroy'])->name('api.funnels.steps.destroy');
    Route::post('funnels/{funnelUuid}/steps/{stepId}/duplicate', [FunnelStepController::class, 'duplicate'])->name('api.funnels.steps.duplicate');
    Route::post('funnels/{funnelUuid}/steps/reorder', [FunnelStepController::class, 'reorder'])->name('api.funnels.steps.reorder');

    // Step Content
    Route::get('funnels/{funnelUuid}/steps/{stepId}/content', [FunnelStepController::class, 'getContent'])->name('api.funnels.steps.content.show');
    Route::put('funnels/{funnelUuid}/steps/{stepId}/content', [FunnelStepController::class, 'saveContent'])->name('api.funnels.steps.content.save');
    Route::post('funnels/{funnelUuid}/steps/{stepId}/content/publish', [FunnelStepController::class, 'publishContent'])->name('api.funnels.steps.content.publish');

    // Funnel Products
    Route::get('funnels/{funnelUuid}/products', [FunnelProductController::class, 'index'])->name('api.funnels.products.index');
    Route::post('funnels/{funnelUuid}/steps/{stepId}/products', [FunnelProductController::class, 'store'])->name('api.funnels.steps.products.store');
    Route::put('funnels/{funnelUuid}/steps/{stepId}/products/{productId}', [FunnelProductController::class, 'update'])->name('api.funnels.steps.products.update');
    Route::delete('funnels/{funnelUuid}/steps/{stepId}/products/{productId}', [FunnelProductController::class, 'destroy'])->name('api.funnels.steps.products.destroy');
    Route::post('funnels/{funnelUuid}/steps/{stepId}/products/reorder', [FunnelProductController::class, 'reorder'])->name('api.funnels.steps.products.reorder');

    // Order Bumps
    Route::get('funnels/{funnelUuid}/steps/{stepId}/order-bumps', [FunnelProductController::class, 'indexOrderBumps'])->name('api.funnels.steps.order-bumps.index');
    Route::post('funnels/{funnelUuid}/steps/{stepId}/order-bumps', [FunnelProductController::class, 'storeOrderBump'])->name('api.funnels.steps.order-bumps.store');
    Route::put('funnels/{funnelUuid}/steps/{stepId}/order-bumps/{bumpId}', [FunnelProductController::class, 'updateOrderBump'])->name('api.funnels.steps.order-bumps.update');
    Route::delete('funnels/{funnelUuid}/steps/{stepId}/order-bumps/{bumpId}', [FunnelProductController::class, 'destroyOrderBump'])->name('api.funnels.steps.order-bumps.destroy');

    // Product/Course Search
    Route::get('products/search', [FunnelProductController::class, 'searchProducts'])->name('api.products.search');
    Route::get('courses/search', [FunnelProductController::class, 'searchCourses'])->name('api.courses.search');
    Route::get('packages/search', [FunnelProductController::class, 'searchPackages'])->name('api.packages.search');

    // Templates
    Route::get('funnel-templates', function () {
        return response()->json([
            'data' => \App\Models\FunnelTemplate::query()
                ->where('is_active', true)
                ->orderBy('usage_count', 'desc')
                ->get(),
        ]);
    })->name('api.funnel-templates.index');

    // Alias for templates (frontend compatibility)
    Route::get('templates', function () {
        return response()->json([
            'data' => \App\Models\FunnelTemplate::query()
                ->where('is_active', true)
                ->orderBy('usage_count', 'desc')
                ->get(),
        ]);
    })->name('api.templates.index');

    // Media Manager
    Route::get('media', [FunnelMediaController::class, 'index'])->name('api.media.index');
    Route::post('media', [FunnelMediaController::class, 'store'])->name('api.media.store');
    Route::put('media/{id}', [FunnelMediaController::class, 'update'])->name('api.media.update');
    Route::delete('media/{id}', [FunnelMediaController::class, 'destroy'])->name('api.media.destroy');
    Route::post('media/bulk-delete', [FunnelMediaController::class, 'bulkDestroy'])->name('api.media.bulk-destroy');

    // Funnel Affiliate Management (Admin)
    Route::get('funnels/{uuid}/affiliates', [FunnelAffiliateController::class, 'index'])->name('api.funnels.affiliates.index');
    Route::get('funnels/{uuid}/affiliates/{affiliateId}/stats', [FunnelAffiliateController::class, 'affiliateStats'])->name('api.funnels.affiliates.stats');
    Route::get('funnels/{uuid}/affiliate-settings', [FunnelAffiliateController::class, 'settings'])->name('api.funnels.affiliate-settings');
    Route::put('funnels/{uuid}/affiliate-settings', [FunnelAffiliateController::class, 'updateSettings'])->name('api.funnels.affiliate-settings.update');
    Route::get('funnels/{uuid}/commissions', [FunnelAffiliateController::class, 'commissions'])->name('api.funnels.commissions.index');
    Route::post('funnels/{uuid}/commissions/{commissionId}/approve', [FunnelAffiliateController::class, 'approveCommission'])->name('api.funnels.commissions.approve');
    Route::post('funnels/{uuid}/commissions/{commissionId}/reject', [FunnelAffiliateController::class, 'rejectCommission'])->name('api.funnels.commissions.reject');
    Route::post('funnels/{uuid}/commissions/bulk-approve', [FunnelAffiliateController::class, 'bulkApprove'])->name('api.funnels.commissions.bulk-approve');
});

/*
|--------------------------------------------------------------------------
| Affiliate Dashboard API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1/affiliate')->middleware(\App\Http\Middleware\AffiliateSessionLifetime::class)->group(function () {
    // Public (no auth)
    Route::post('login', [AffiliateDashboardController::class, 'login'])->name('api.affiliate.login');
    Route::post('register', [AffiliateDashboardController::class, 'register'])->name('api.affiliate.register');

    // Protected (affiliate middleware)
    Route::middleware('affiliate')->group(function () {
        Route::post('logout', [AffiliateDashboardController::class, 'logout'])->name('api.affiliate.logout');
        Route::get('me', [AffiliateDashboardController::class, 'me'])->name('api.affiliate.me');
        Route::put('me', [AffiliateDashboardController::class, 'update'])->name('api.affiliate.update');
        Route::get('dashboard', [AffiliateDashboardController::class, 'dashboard'])->name('api.affiliate.dashboard');
        Route::get('funnels', [AffiliateDashboardController::class, 'joinedFunnels'])->name('api.affiliate.funnels');
        Route::get('funnels/discover', [AffiliateDashboardController::class, 'discoverFunnels'])->name('api.affiliate.funnels.discover');
        Route::post('funnels/{funnel}/join', [AffiliateDashboardController::class, 'joinFunnel'])->name('api.affiliate.funnels.join');
        Route::get('funnels/{funnel}/stats', [AffiliateDashboardController::class, 'funnelStats'])->name('api.affiliate.funnels.stats');
        Route::get('leaderboard', [AffiliateDashboardController::class, 'leaderboard'])->name('api.affiliate.leaderboard');
    });
});

/*
|--------------------------------------------------------------------------
| Funnel Checkout API Routes (Public - No Auth Required)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/funnel-checkout')->group(function () {
    // Get checkout configuration (Stripe publishable key)
    Route::get('config', [FunnelCheckoutController::class, 'getConfig'])->name('api.funnel-checkout.config');

    // Create checkout session and payment intent
    Route::post('{funnelUuid}/steps/{stepId}/checkout', [FunnelCheckoutController::class, 'createCheckout'])->name('api.funnel-checkout.create');

    // Confirm payment
    Route::post('confirm-payment', [FunnelCheckoutController::class, 'confirmPayment'])->name('api.funnel-checkout.confirm');

    // Upsell routes
    Route::post('{funnelUuid}/steps/{stepId}/upsell', [FunnelCheckoutController::class, 'processUpsell'])->name('api.funnel-checkout.upsell');
    Route::post('{funnelUuid}/steps/{stepId}/decline-upsell', [FunnelCheckoutController::class, 'declineUpsell'])->name('api.funnel-checkout.decline-upsell');
});

/*
|--------------------------------------------------------------------------
| Funnel Pixel Tracking API Routes (Public - No Auth Required)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/funnel')->group(function () {
    // Track pixel events from client-side (for server-side deduplication)
    Route::post('{funnelUuid}/pixel-event', [FunnelPixelController::class, 'trackEvent'])->name('api.funnel.pixel-event');
});

/*
|--------------------------------------------------------------------------
| Funnel Event Tracking API Routes (Public - No Auth Required)
|--------------------------------------------------------------------------
*/
Route::post('funnel-events/button-click', [\App\Http\Controllers\Api\FunnelEventController::class, 'trackButtonClick'])
    ->name('api.funnel-events.button-click');

// Pixel test connection (authenticated)
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::post('funnels/{uuid}/pixel/test', [FunnelPixelController::class, 'testConnection'])->name('api.funnels.pixel.test');
});

/*
|--------------------------------------------------------------------------
| POS (Point of Sale) API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('pos')->group(function () {
    Route::get('products', [\App\Http\Controllers\Api\PosController::class, 'products'])->name('api.pos.products');
    Route::get('packages', [\App\Http\Controllers\Api\PosController::class, 'packages'])->name('api.pos.packages');
    Route::get('courses', [\App\Http\Controllers\Api\PosController::class, 'courses'])->name('api.pos.courses');
    Route::get('classes/{course}', [\App\Http\Controllers\Api\PosController::class, 'courseClasses'])->name('api.pos.classes');
    Route::get('customers', [\App\Http\Controllers\Api\PosController::class, 'customers'])->name('api.pos.customers');
    Route::post('sales', [\App\Http\Controllers\Api\PosController::class, 'createSale'])->name('api.pos.sales.store');
    Route::get('sales', [\App\Http\Controllers\Api\PosController::class, 'salesHistory'])->name('api.pos.sales.index');
    Route::get('sales/{sale}', [\App\Http\Controllers\Api\PosController::class, 'saleDetail'])->name('api.pos.sales.show');
    Route::get('dashboard', [\App\Http\Controllers\Api\PosController::class, 'dashboard'])->name('api.pos.dashboard');
});

/*
|--------------------------------------------------------------------------
| Workflow Builder API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('workflows')->group(function () {
    Route::get('/', [WorkflowController::class, 'index'])->name('api.workflows.index');
    Route::post('/', [WorkflowController::class, 'store'])->name('api.workflows.store');
    Route::get('/{uuid}', [WorkflowController::class, 'show'])->name('api.workflows.show');
    Route::put('/{uuid}', [WorkflowController::class, 'update'])->name('api.workflows.update');
    Route::delete('/{uuid}', [WorkflowController::class, 'destroy'])->name('api.workflows.destroy');
    Route::post('/{uuid}/publish', [WorkflowController::class, 'publish'])->name('api.workflows.publish');
    Route::post('/{uuid}/pause', [WorkflowController::class, 'pause'])->name('api.workflows.pause');
    Route::get('/{uuid}/stats', [WorkflowController::class, 'stats'])->name('api.workflows.stats');
});
