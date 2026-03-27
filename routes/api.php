<?php

use App\Http\Controllers\Api\ContactActivityController;
use App\Http\Controllers\Api\Hr\HrAttendanceAnalyticsController;
use App\Http\Controllers\Api\Hr\HrAttendanceController;
use App\Http\Controllers\Api\Hr\HrAttendancePenaltyController;
use App\Http\Controllers\Api\Hr\HrDashboardController;
use App\Http\Controllers\Api\Hr\HrDepartmentApproverController;
use App\Http\Controllers\Api\Hr\HrDepartmentController;
use App\Http\Controllers\Api\Hr\HrEmergencyContactController;
use App\Http\Controllers\Api\Hr\HrEmployeeController;
use App\Http\Controllers\Api\Hr\HrEmployeeDocumentController;
use App\Http\Controllers\Api\Hr\HrEmployeeHistoryController;
use App\Http\Controllers\Api\Hr\HrHolidayController;
use App\Http\Controllers\Api\Hr\HrLeaveBalanceController;
use App\Http\Controllers\Api\Hr\HrLeaveCalendarController;
use App\Http\Controllers\Api\Hr\HrLeaveDashboardController;
use App\Http\Controllers\Api\Hr\HrLeaveEntitlementController;
use App\Http\Controllers\Api\Hr\HrLeaveRequestController;
use App\Http\Controllers\Api\Hr\HrLeaveTypeController;
use App\Http\Controllers\Api\Hr\HrMyAttendanceController;
use App\Http\Controllers\Api\Hr\HrMyLeaveController;
use App\Http\Controllers\Api\Hr\HrMyProfileController;
use App\Http\Controllers\Api\Hr\HrOvertimeController;
use App\Http\Controllers\Api\Hr\HrPositionController;
use App\Http\Controllers\Api\Hr\HrWorkScheduleController;
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
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Middleware\VerifyWhatsAppWebhook;
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
    Route::get('funnels/{funnelUuid}/automation-logs', [FunnelAutomationController::class, 'allLogs'])->name('api.funnels.automation-logs');

    // Automation Merge Tag Variables
    Route::get('funnel-builder/variables', [FunnelAutomationController::class, 'variables'])->name('api.funnel-builder.variables');
    Route::get('funnel-builder/variables/all', [FunnelAutomationController::class, 'allVariables'])->name('api.funnel-builder.variables.all');

    // WhatsApp Templates for Funnel Builder
    Route::get('funnel-builder/whatsapp-templates', function () {
        return response()->json([
            'data' => \App\Models\WhatsAppTemplate::approved()
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'language' => $t->language,
                    'category' => $t->category,
                    'components' => $t->components,
                    'body_preview' => collect($t->components)
                        ->firstWhere('type', 'BODY')['text'] ?? '',
                ]),
        ]);
    })->name('api.funnel-builder.whatsapp-templates');

    // Funnel Email Templates (admin only)
    Route::middleware('role:admin,employee')->group(function () {
        Route::get('funnel-email-templates', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'index'])->name('api.funnel-email-templates.index');
        Route::get('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'show'])->name('api.funnel-email-templates.show');
        Route::post('funnel-email-templates', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'store'])->name('api.funnel-email-templates.store');
        Route::put('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'update'])->name('api.funnel-email-templates.update');
        Route::delete('funnel-email-templates/{id}', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'destroy'])->name('api.funnel-email-templates.destroy');
        Route::post('funnel-email-templates/{id}/duplicate', [\App\Http\Controllers\Api\V1\FunnelEmailTemplateController::class, 'duplicate'])->name('api.funnel-email-templates.duplicate');
    });

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
    Route::get('sales-sources', [\App\Http\Controllers\Api\PosController::class, 'salesSources'])->name('api.pos.sales-sources');
    Route::get('products', [\App\Http\Controllers\Api\PosController::class, 'products'])->name('api.pos.products');
    Route::get('packages', [\App\Http\Controllers\Api\PosController::class, 'packages'])->name('api.pos.packages');
    Route::get('courses', [\App\Http\Controllers\Api\PosController::class, 'courses'])->name('api.pos.courses');
    Route::get('classes/{course}', [\App\Http\Controllers\Api\PosController::class, 'courseClasses'])->name('api.pos.classes');
    Route::get('customers', [\App\Http\Controllers\Api\PosController::class, 'customers'])->name('api.pos.customers');
    Route::post('sales', [\App\Http\Controllers\Api\PosController::class, 'createSale'])->name('api.pos.sales.store');
    Route::get('sales', [\App\Http\Controllers\Api\PosController::class, 'salesHistory'])->name('api.pos.sales.index');
    Route::get('sales/{sale}', [\App\Http\Controllers\Api\PosController::class, 'saleDetail'])->name('api.pos.sales.show');
    Route::put('sales/{sale}/status', [\App\Http\Controllers\Api\PosController::class, 'updateSaleStatus'])->name('api.pos.sales.update-status');
    Route::put('sales/{sale}/details', [\App\Http\Controllers\Api\PosController::class, 'updateSaleDetails'])->name('api.pos.sales.update-details');
    Route::delete('sales/{sale}', [\App\Http\Controllers\Api\PosController::class, 'deleteSale'])->name('api.pos.sales.destroy');
    Route::get('dashboard', [\App\Http\Controllers\Api\PosController::class, 'dashboard'])->name('api.pos.dashboard');
    Route::get('reports/monthly', [\App\Http\Controllers\Api\PosController::class, 'reportMonthly'])->name('api.pos.reports.monthly');
    Route::get('reports/daily', [\App\Http\Controllers\Api\PosController::class, 'reportDaily'])->name('api.pos.reports.daily');
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

/*
|--------------------------------------------------------------------------
| WhatsApp Inbox API Routes (Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('admin/whatsapp')->group(function () {
    Route::get('conversations', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'index'])->name('api.admin.whatsapp.conversations');
    Route::get('conversations/{conversation}', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'show'])->name('api.admin.whatsapp.conversations.show');
    Route::post('conversations/{conversation}/reply', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'reply'])->name('api.admin.whatsapp.conversations.reply');
    Route::post('conversations/{conversation}/template', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'sendTemplate'])->name('api.admin.whatsapp.conversations.template');
    Route::post('conversations/{conversation}/archive', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'archive'])->name('api.admin.whatsapp.conversations.archive');
    Route::get('templates', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'templates'])->name('api.admin.whatsapp.templates');
    Route::post('templates/sync', [\App\Http\Controllers\Admin\WhatsAppInboxController::class, 'syncTemplates'])->name('api.admin.whatsapp.templates.sync');
});

/*
|--------------------------------------------------------------------------
| WhatsApp Webhook Routes (Public - No Auth Required)
|--------------------------------------------------------------------------
*/
Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->middleware(VerifyWhatsAppWebhook::class)
    ->name('api.whatsapp.webhook.handle');

/*
|--------------------------------------------------------------------------
| HR Module API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin,employee'])->prefix('hr')->group(function () {
    // Dashboard
    Route::get('dashboard/stats', [HrDashboardController::class, 'stats'])->name('api.hr.dashboard.stats');
    Route::get('dashboard/recent-activity', [HrDashboardController::class, 'recentActivity'])->name('api.hr.dashboard.recent-activity');
    Route::get('dashboard/headcount-by-department', [HrDashboardController::class, 'headcountByDepartment'])->name('api.hr.dashboard.headcount');

    // Employee Self-Service (My Profile)
    Route::get('me', [HrMyProfileController::class, 'show'])->name('api.hr.me');
    Route::put('me', [HrMyProfileController::class, 'update'])->name('api.hr.me.update');
    Route::get('me/documents', [HrMyProfileController::class, 'documents'])->name('api.hr.me.documents');
    Route::post('me/documents', [HrMyProfileController::class, 'uploadDocument'])->name('api.hr.me.documents.store');
    Route::get('me/emergency-contacts', [HrMyProfileController::class, 'emergencyContacts'])->name('api.hr.me.emergency-contacts');
    Route::post('me/emergency-contacts', [HrMyProfileController::class, 'storeEmergencyContact'])->name('api.hr.me.emergency-contacts.store');
    Route::put('me/emergency-contacts/{contactId}', [HrMyProfileController::class, 'updateEmergencyContact'])->name('api.hr.me.emergency-contacts.update');
    Route::delete('me/emergency-contacts/{contactId}', [HrMyProfileController::class, 'deleteEmergencyContact'])->name('api.hr.me.emergency-contacts.destroy');

    // Employees
    Route::get('employees/next-id', [HrEmployeeController::class, 'nextId'])->name('api.hr.employees.next-id');
    Route::get('employees/export', [HrEmployeeController::class, 'export'])->name('api.hr.employees.export');
    Route::apiResource('employees', HrEmployeeController::class)->names('api.hr.employees');
    Route::patch('employees/{employee}/status', [HrEmployeeController::class, 'updateStatus'])->name('api.hr.employees.update-status');

    // Employee sub-resources
    Route::get('employees/{employee}/history', [HrEmployeeHistoryController::class, 'index'])->name('api.hr.employees.history');
    Route::get('employees/{employee}/documents', [HrEmployeeDocumentController::class, 'index'])->name('api.hr.employees.documents.index');
    Route::post('employees/{employee}/documents', [HrEmployeeDocumentController::class, 'store'])->name('api.hr.employees.documents.store');
    Route::get('employees/{employee}/documents/{document}/download', [HrEmployeeDocumentController::class, 'download'])->name('api.hr.employees.documents.download');
    Route::delete('employees/{employee}/documents/{document}', [HrEmployeeDocumentController::class, 'destroy'])->name('api.hr.employees.documents.destroy');
    Route::apiResource('employees.emergency-contacts', HrEmergencyContactController::class)->shallow()->names('api.hr.emergency-contacts');

    // Departments
    Route::get('departments/tree', [HrDepartmentController::class, 'tree'])->name('api.hr.departments.tree');
    Route::get('departments/{department}/employees', [HrDepartmentController::class, 'employees'])->name('api.hr.departments.employees');
    Route::apiResource('departments', HrDepartmentController::class)->names('api.hr.departments');

    // Positions
    Route::apiResource('positions', HrPositionController::class)->names('api.hr.positions');

    // My Attendance (Employee Self-Service)
    Route::get('my-attendance', [HrMyAttendanceController::class, 'index'])->name('api.hr.my-attendance.index');
    Route::post('my-attendance/clock-in', [HrMyAttendanceController::class, 'clockIn'])->name('api.hr.my-attendance.clock-in');
    Route::post('my-attendance/clock-out', [HrMyAttendanceController::class, 'clockOut'])->name('api.hr.my-attendance.clock-out');
    Route::get('my-attendance/today', [HrMyAttendanceController::class, 'today'])->name('api.hr.my-attendance.today');
    Route::get('my-attendance/summary', [HrMyAttendanceController::class, 'summary'])->name('api.hr.my-attendance.summary');
    Route::get('my-attendance/overtime', [HrMyAttendanceController::class, 'myOvertime'])->name('api.hr.my-attendance.overtime');
    Route::post('my-attendance/overtime', [HrMyAttendanceController::class, 'submitOvertime'])->name('api.hr.my-attendance.overtime.store');
    Route::get('my-attendance/overtime-balance', [HrMyAttendanceController::class, 'overtimeBalance'])->name('api.hr.my-attendance.overtime-balance');
    Route::post('my-attendance/overtime/{overtimeRequest}/cancel', [HrMyAttendanceController::class, 'cancelOvertime'])->name('api.hr.my-attendance.overtime.cancel');

    // Attendance Admin
    Route::get('attendance', [HrAttendanceController::class, 'index'])->name('api.hr.attendance.index');
    Route::get('attendance/today', [HrAttendanceController::class, 'today'])->name('api.hr.attendance.today');
    Route::get('attendance/export', [HrAttendanceController::class, 'export'])->name('api.hr.attendance.export');
    Route::get('attendance/{attendanceLog}', [HrAttendanceController::class, 'show'])->name('api.hr.attendance.show');
    Route::put('attendance/{attendanceLog}', [HrAttendanceController::class, 'update'])->name('api.hr.attendance.update');

    // Work Schedules
    Route::apiResource('work-schedules', HrWorkScheduleController::class)->names('api.hr.work-schedules');
    Route::get('work-schedules/{workSchedule}/employees', [HrWorkScheduleController::class, 'employees'])->name('api.hr.work-schedules.employees');

    // Overtime Admin
    Route::get('overtime', [HrOvertimeController::class, 'index'])->name('api.hr.overtime.index');
    Route::get('overtime/{overtimeRequest}', [HrOvertimeController::class, 'show'])->name('api.hr.overtime.show');
    Route::post('overtime/{overtimeRequest}/approve', [HrOvertimeController::class, 'approve'])->name('api.hr.overtime.approve');
    Route::post('overtime/{overtimeRequest}/reject', [HrOvertimeController::class, 'reject'])->name('api.hr.overtime.reject');
    Route::post('overtime/{overtimeRequest}/complete', [HrOvertimeController::class, 'complete'])->name('api.hr.overtime.complete');

    // Holidays
    Route::apiResource('holidays', HrHolidayController::class)->names('api.hr.holidays');
    Route::post('holidays/bulk-import', [HrHolidayController::class, 'bulkImport'])->name('api.hr.holidays.bulk-import');

    // Department Approvers
    Route::apiResource('department-approvers', HrDepartmentApproverController::class)->except('show')->names('api.hr.department-approvers');

    // Attendance Penalties
    Route::get('attendance-penalties', [HrAttendancePenaltyController::class, 'index'])->name('api.hr.attendance-penalties.index');
    Route::get('attendance-penalties/flagged', [HrAttendancePenaltyController::class, 'flagged'])->name('api.hr.attendance-penalties.flagged');
    Route::get('attendance-penalties/summary', [HrAttendancePenaltyController::class, 'summary'])->name('api.hr.attendance-penalties.summary');

    // Attendance Analytics
    Route::get('attendance-analytics/overview', [HrAttendanceAnalyticsController::class, 'overview'])->name('api.hr.attendance-analytics.overview');
    Route::get('attendance-analytics/trends', [HrAttendanceAnalyticsController::class, 'trends'])->name('api.hr.attendance-analytics.trends');
    Route::get('attendance-analytics/department', [HrAttendanceAnalyticsController::class, 'department'])->name('api.hr.attendance-analytics.department');
    Route::get('attendance-analytics/punctuality', [HrAttendanceAnalyticsController::class, 'punctuality'])->name('api.hr.attendance-analytics.punctuality');
    Route::get('attendance-analytics/overtime', [HrAttendanceAnalyticsController::class, 'overtime'])->name('api.hr.attendance-analytics.overtime');

    // Leave Types
    Route::apiResource('leave-types', HrLeaveTypeController::class)->names('api.hr.leave-types');

    // Leave Entitlements
    Route::apiResource('leave-entitlements', HrLeaveEntitlementController::class)->except('show')->names('api.hr.leave-entitlements');
    Route::post('leave-entitlements/recalculate', [HrLeaveEntitlementController::class, 'recalculate'])->name('api.hr.leave-entitlements.recalculate');

    // Leave Balances
    Route::get('leave-balances', [HrLeaveBalanceController::class, 'index'])->name('api.hr.leave-balances.index');
    Route::get('leave-balances/employee/{employeeId}', [HrLeaveBalanceController::class, 'show'])->name('api.hr.leave-balances.show');
    Route::post('leave-balances/initialize', [HrLeaveBalanceController::class, 'initialize'])->name('api.hr.leave-balances.initialize');
    Route::patch('leave-balances/{leaveBalance}/adjust', [HrLeaveBalanceController::class, 'adjust'])->name('api.hr.leave-balances.adjust');
    Route::get('leave-balances/export', [HrLeaveBalanceController::class, 'export'])->name('api.hr.leave-balances.export');

    // Leave Requests (Admin)
    Route::get('leave-requests', [HrLeaveRequestController::class, 'index'])->name('api.hr.leave-requests.index');
    Route::get('leave-requests/export', [HrLeaveRequestController::class, 'export'])->name('api.hr.leave-requests.export');
    Route::get('leave-requests/{leaveRequest}', [HrLeaveRequestController::class, 'show'])->name('api.hr.leave-requests.show');
    Route::post('leave-requests/{leaveRequest}/approve', [HrLeaveRequestController::class, 'approve'])->name('api.hr.leave-requests.approve');
    Route::post('leave-requests/{leaveRequest}/reject', [HrLeaveRequestController::class, 'reject'])->name('api.hr.leave-requests.reject');

    // Leave Calendar
    Route::get('leave-calendar', [HrLeaveCalendarController::class, 'index'])->name('api.hr.leave-calendar.index');
    Route::get('leave-calendar/overlaps', [HrLeaveCalendarController::class, 'overlaps'])->name('api.hr.leave-calendar.overlaps');

    // Leave Dashboard
    Route::get('leave-dashboard/stats', [HrLeaveDashboardController::class, 'stats'])->name('api.hr.leave-dashboard.stats');
    Route::get('leave-dashboard/pending', [HrLeaveDashboardController::class, 'pending'])->name('api.hr.leave-dashboard.pending');
    Route::get('leave-dashboard/distribution', [HrLeaveDashboardController::class, 'distribution'])->name('api.hr.leave-dashboard.distribution');

    // My Leave (Employee Self-Service)
    Route::get('my-leave/balances', [HrMyLeaveController::class, 'balances'])->name('api.hr.my-leave.balances');
    Route::get('my-leave/requests', [HrMyLeaveController::class, 'requests'])->name('api.hr.my-leave.requests');
    Route::post('my-leave/apply', [HrMyLeaveController::class, 'apply'])->name('api.hr.my-leave.apply');
    Route::post('my-leave/{leaveRequest}/cancel', [HrMyLeaveController::class, 'cancel'])->name('api.hr.my-leave.cancel');
    Route::get('my-leave/calculate-days', [HrMyLeaveController::class, 'calculateDays'])->name('api.hr.my-leave.calculate-days');
});
