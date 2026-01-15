<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();

    if ($user->isStudent()) {
        return redirect()->route('student.dashboard');
    }

    if ($user->isTeacher()) {
        return redirect()->route('teacher.dashboard');
    }

    if ($user->isLiveHost()) {
        return redirect()->route('live-host.dashboard');
    }

    if ($user->isClassAdmin()) {
        return redirect()->route('class-admin.dashboard');
    }

    return view('dashboard');
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // Stop impersonation route (accessible when impersonating any role)
    Route::post('stop-impersonation', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

// Product Cart routes - accessible by authenticated and guest users
Volt::route('cart', 'cart.shopping-cart')->name('cart');
Volt::route('checkout', 'cart.checkout')->name('checkout');

// Guest payment method update (magic link)
Volt::route('update-payment-method/{token}', 'guest.update-payment-method')->name('payment-method.update-guest');

// Student routes - accessible by students only
Route::middleware(['auth', 'role:student'])->prefix('my')->group(function () {
    // Student dashboard (home)
    Volt::route('/', 'student.dashboard')->name('student.dashboard');

    // Account hub for students
    Volt::route('account', 'student.account')->name('student.account');

    // Courses listing for students
    Volt::route('courses', 'student.courses')->name('student.courses');

    // Classes for students
    Volt::route('classes', 'student.my-classes')->name('student.classes.index');
    Volt::route('classes/{class}', 'student.class-show')->name('student.classes.show');

    // Timetable for students
    Volt::route('timetable', 'student.my-timetable')->name('student.timetable');

    // Subscription management for students
    Volt::route('subscriptions', 'student.subscriptions')->name('student.subscriptions');
    Volt::route('subscriptions/{enrollment}/cancel', 'student.subscription-cancel')->name('student.subscriptions.cancel');

    // Order history and receipts for students
    Volt::route('orders', 'student.orders')->name('student.orders');
    Volt::route('orders/{order}', 'student.orders-show')->name('student.orders.show');
    Volt::route('orders/{order}/receipt', 'student.orders-receipt')->name('student.orders.receipt');

    // Payment method management for students
    Volt::route('payment-methods', 'student.payment-methods')->name('student.payment-methods');

    // Refund requests for students
    Volt::route('refund-requests', 'student.refund-requests')->name('student.refund-requests');
    Volt::route('refund-requests/create', 'student.refund-request-create')->name('student.refund-requests.create');
    Volt::route('refund-requests/{refund}', 'student.refund-request-show')->name('student.refund-requests.show');

    // Legacy invoice routes (will be removed later)
    Volt::route('invoices', 'student.invoice-list')->name('student.invoices');
    Volt::route('invoices/{invoice}', 'student.invoice-show')->name('student.invoices.show');
    Volt::route('invoices/{invoice}/pay', 'student.invoice-pay')->name('student.invoices.pay');
    Volt::route('invoices/{invoice}/bank-transfer', 'student.bank-transfer-form')->name('student.invoices.bank-transfer');

    // Legacy payment history
    Volt::route('payments', 'student.payment-history')->name('student.payments');
    Volt::route('payments/{payment}', 'student.payment-show')->name('student.payments.show');
});

// Teacher routes - accessible by teachers only
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->group(function () {
    // Teacher dashboard
    Volt::route('dashboard', 'teacher.dashboard')->name('teacher.dashboard');

    // Core teaching modules
    Volt::route('courses', 'teacher.courses-index')->name('teacher.courses.index');
    Volt::route('courses/create', 'teacher.courses-create')->name('teacher.courses.create');
    Volt::route('courses/{course}', 'teacher.courses-show')->name('teacher.courses.show');
    Volt::route('courses/{course}/edit', 'teacher.courses-edit')->name('teacher.courses.edit');

    Volt::route('classes', 'teacher.classes-index')->name('teacher.classes.index');
    Volt::route('classes/create', 'teacher.classes-create')->name('teacher.classes.create');
    Volt::route('classes/{class}', 'teacher.classes-show')->name('teacher.classes.show');
    Volt::route('classes/{class}/edit', 'teacher.classes-edit')->name('teacher.classes.edit');

    Volt::route('students', 'teacher.students-index')->name('teacher.students.index');
    Volt::route('students/{student}', 'teacher.students-show')->name('teacher.students.show');

    Volt::route('sessions', 'teacher.sessions-index')->name('teacher.sessions.index');
    Volt::route('sessions/{session}', 'teacher.session-show')->name('teacher.sessions.show');

    // Payslip routes for teachers (read-only)
    Volt::route('payslips', 'teacher.payslips-index')->name('teacher.payslips.index');
    Volt::route('payslips/{payslip}', 'teacher.payslips-show')->name('teacher.payslips.show');

    Volt::route('timetable', 'teacher.timetable')->name('teacher.timetable');
    Volt::route('enrollments/{enrollment}', 'teacher.enrollments-show')->name('teacher.enrollments.show');
});

// Live Host routes - accessible by live hosts only
Route::middleware(['auth', 'role:live_host'])->prefix('live-host')->name('live-host.')->group(function () {
    Volt::route('dashboard', 'live-host.dashboard')->name('dashboard');
    Volt::route('schedule', 'live-host.schedule')->name('schedule');
    Volt::route('session-slots', 'live-host.session-upload')->name('session-slots');
    Volt::route('sessions', 'live-host.sessions-index')->name('sessions.index');
    Volt::route('sessions/{session}', 'live-host.sessions-show')->name('sessions.show');
});

// Public Live Schedule - accessible by everyone
Volt::route('live/schedule', 'live.schedule-public')->name('live.schedule');

// Class Admin Dashboard - only class_admin (entry point for class_admin users)
Route::middleware(['auth', 'role:class_admin'])->prefix('class-admin')->name('class-admin.')->group(function () {
    Volt::route('dashboard', 'class-admin.dashboard')->name('dashboard');
});

// ============================================================================
// SHARED ADMIN ROUTES - Accessible by both admin and class_admin roles
// ============================================================================
Route::middleware(['auth', 'role:admin,class_admin'])->prefix('admin')->group(function () {
    // Course routes
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::get('courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');

    // Student routes
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    Route::get('students/import', fn () => view('students.import'))->name('students.import');
    Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
    Route::get('students/sample-csv', [StudentController::class, 'sampleCsv'])->name('students.sample-csv');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');

    // Teacher routes
    Volt::route('teachers', 'admin.teacher-list')->name('teachers.index');
    Volt::route('teachers/create', 'admin.teacher-create')->name('teachers.create');
    Route::get('teachers/import', fn () => view('teachers.import'))->name('teachers.import');
    Route::get('teachers/export', [TeacherController::class, 'export'])->name('teachers.export');
    Route::get('teachers/sample-csv', [TeacherController::class, 'sampleCsv'])->name('teachers.sample-csv');
    Volt::route('teachers/{teacher}', 'admin.teacher-show')->name('teachers.show');
    Volt::route('teachers/{teacher}/edit', 'admin.teacher-edit')->name('teachers.edit');

    // Class routes
    Volt::route('classes', 'admin.class-list')->name('classes.index');
    Volt::route('classes/create', 'admin.class-create')->name('classes.create');
    Volt::route('classes/{class}', 'admin.class-show')->name('classes.show');
    Volt::route('classes/{class}/edit', 'admin.class-edit')->name('classes.edit');
    Volt::route('classes/notification-builder/{settingId}', 'admin.class-notification-builder')->name('admin.class-notification-builder');

    // Class Category routes
    Volt::route('class-categories', 'admin.class-category-list')->name('class-categories.index');

    // Master Timetable route
    Volt::route('master-timetable', 'admin.master-timetable')->name('admin.master-timetable');

    // Session routes
    Volt::route('sessions', 'admin.sessions-index')->name('admin.sessions.index');
    Volt::route('sessions/{session}', 'admin.sessions-show')->name('admin.sessions.show');

    // Student payment method management
    Volt::route('students/{student}/payment-methods', 'admin.student-payment-methods')->name('admin.students.payment-methods');

    // Enrollment routes
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('enrollments/create', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Volt::route('enrollments/bulk-create', 'admin.enrollment-bulk-create')->name('enrollments.bulk-create');
    Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
    Route::get('enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->name('enrollments.edit');

    // Order routes (replaces invoices)
    Volt::route('orders', 'admin.orders-index')->name('orders.index');
    Volt::route('orders/{order}', 'admin.orders-show')->name('orders.show');
    Volt::route('orders/{order}/receipt', 'admin.orders-receipt')->name('orders.receipt');

    // Legacy invoice routes (will be removed later)
    Volt::route('invoices', 'admin.invoice-list')->name('invoices.index');
    Volt::route('invoices/generate', 'admin.invoice-generate')->name('invoices.generate');
    Volt::route('invoices/{invoice}', 'admin.invoice-show')->name('invoices.show');

    // Payment management routes
    Volt::route('payments', 'admin.payment-dashboard')->name('admin.payments');
    Volt::route('payments/{payment}', 'admin.payment-show')->name('admin.payments.show');
    Volt::route('bank-transfers', 'admin.bank-transfer-list')->name('admin.bank-transfers');

    // Payslip management routes
    Volt::route('payslips', 'admin.payslips-index')->name('admin.payslips.index');
    Volt::route('payslips/generate', 'admin.payslips-generate')->name('admin.payslips.generate');
    Volt::route('payslips/{payslip}', 'admin.payslips-show')->name('admin.payslips.show');
    Volt::route('payslips/{payslip}/edit', 'admin.payslips-edit')->name('admin.payslips.edit');
});

// ============================================================================
// ADMIN-ONLY ROUTES - Accessible only by admin role
// ============================================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // User management routes
    Volt::route('users', 'admin.user-list')->name('users.index');
    Volt::route('users/create', 'admin.user-create')->name('users.create');
    Volt::route('users/{user}', 'admin.user-show')->name('users.show');
    Volt::route('users/{user}/edit', 'admin.user-edit')->name('users.edit');

    // Impersonation routes
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonation.start');
    Volt::route('impersonation-logs', 'admin.impersonation-logs')->name('admin.impersonation-logs');

    // Reports routes
    Volt::route('reports/subscriptions', 'admin.subscription-reports')->name('admin.reports.subscriptions');
    Volt::route('reports/student-payments', 'admin.student-payment-report')->name('admin.reports.student-payments');
    Volt::route('reports/packages-orders', 'admin.reports.packages-orders')->name('admin.reports.packages-orders');
    Volt::route('reports/student-product-orders', 'admin.reports.student-product-orders')->name('admin.reports.student-product-orders');
    Volt::route('reports/student-class-enrollments', 'admin.reports.student-class-enrollments')->name('admin.reports.student-class-enrollments');

    // Product Management routes
    Volt::route('products', 'admin.products.product-list')->name('products.index');
    Volt::route('products/create', 'admin.products.product-create')->name('products.create');
    Volt::route('products/{product}', 'admin.products.product-show')->name('products.show');
    Volt::route('products/{product}/edit', 'admin.products.product-edit')->name('products.edit');

    // Product Categories routes
    Volt::route('product-categories', 'admin.products.category-list')->name('product-categories.index');
    Volt::route('product-categories/create', 'admin.products.category-create')->name('product-categories.create');
    Volt::route('product-categories/{category}', 'admin.products.category-show')->name('product-categories.show');
    Volt::route('product-categories/{category}/edit', 'admin.products.category-edit')->name('product-categories.edit');

    // Product Attributes routes
    Volt::route('product-attributes', 'admin.products.attribute-list')->name('product-attributes.index');
    Volt::route('product-attributes/create', 'admin.products.attribute-create')->name('product-attributes.create');
    Volt::route('product-attributes/{attribute}', 'admin.products.attribute-show')->name('product-attributes.show');
    Volt::route('product-attributes/{attribute}/edit', 'admin.products.attribute-edit')->name('product-attributes.edit');

    // Stock Management routes
    Volt::route('inventory', 'admin.stock.stock-dashboard')->name('inventory.dashboard');
    Volt::route('stock/movements', 'admin.stock.stock-movements')->name('stock.movements');
    Volt::route('stock/movements/create', 'admin.stock.movement-create')->name('stock.movements.create');
    Volt::route('stock/levels', 'admin.stock.stock-levels')->name('stock.levels');
    Volt::route('stock/alerts', 'admin.stock.stock-alerts')->name('stock.alerts');

    // Warehouse Management routes
    Volt::route('warehouses', 'admin.stock.warehouse-list')->name('warehouses.index');
    Volt::route('warehouses/create', 'admin.stock.warehouse-create')->name('warehouses.create');
    Volt::route('warehouses/{warehouse}', 'admin.stock.warehouse-show')->name('warehouses.show');
    Volt::route('warehouses/{warehouse}/edit', 'admin.stock.warehouse-edit')->name('warehouses.edit');

    // Agent Management routes
    Volt::route('agents', 'admin.agents.agent-list')->name('agents.index');
    Volt::route('agents/create', 'admin.agents.agent-create')->name('agents.create');
    Volt::route('agents/{agent}', 'admin.agents.agent-show')->name('agents.show');
    Volt::route('agents/{agent}/edit', 'admin.agents.agent-edit')->name('agents.edit');

    // Product Order Management routes
    Volt::route('product-orders', 'admin.orders.order-list')->name('admin.orders.index');
    Volt::route('product-orders/create', 'admin.orders.order-create')->name('admin.orders.create');
    Volt::route('product-orders/{order}', 'admin.orders.order-show')->name('admin.orders.show');
    Volt::route('product-orders/{order}/edit', 'admin.orders.order-edit')->name('admin.orders.edit');
    Volt::route('product-orders/{order}/receipt', 'admin.orders.order-receipt')->name('admin.orders.receipt');

    // Package Management routes
    Volt::route('packages', 'admin.packages.index')->name('packages.index');
    Volt::route('packages/create', 'admin.packages.create')->name('packages.create');
    Volt::route('packages/{package}', 'admin.packages.show')->name('packages.show');
    Volt::route('packages/{package}/edit', 'admin.packages.edit')->name('packages.edit');

    // Platform Management routes
    Volt::route('platform-integration', 'admin.platforms.dashboard')->name('platforms.dashboard');
    Volt::route('platforms', 'admin.platforms.index')->name('platforms.index');
    Volt::route('platforms/create', 'admin.platforms.create')->name('platforms.create');

    // Platform Order Import routes (general routes without platform requirement) - MUST come before wildcard routes
    Volt::route('platforms/orders/import', 'admin.platforms.orders.tiktok-import')->name('platforms.orders.import');
    Volt::route('platforms/orders', 'admin.platforms.orders.general-index')->name('platforms.orders.index');

    // Platform Import History routes - MUST come before wildcard routes
    Volt::route('platforms/import-history', 'admin.platforms.import-history')->name('platforms.import-history');

    // Platform SKU Mapping routes - MUST come before wildcard routes
    Volt::route('platforms/sku-mappings', 'admin.platforms.sku-mappings.index')->name('platforms.sku-mappings.index');
    Volt::route('platforms/sku-mappings/create', 'admin.platforms.sku-mappings.create')->name('platforms.sku-mappings.create');
    Volt::route('platforms/sku-mappings/{mapping}', 'admin.platforms.sku-mappings.show')->name('platforms.sku-mappings.show');
    Volt::route('platforms/sku-mappings/{mapping}/edit', 'admin.platforms.sku-mappings.edit')->name('platforms.sku-mappings.edit');

    // Platform wildcard routes - MUST come after specific routes
    Volt::route('platforms/{platform}', 'admin.platforms.show')->name('platforms.show');
    Volt::route('platforms/{platform}/edit', 'admin.platforms.edit')->name('platforms.edit');

    // Platform Account Management routes
    Volt::route('platforms/{platform}/accounts', 'admin.platforms.accounts.index')->name('platforms.accounts.index');
    Volt::route('platforms/{platform}/accounts/create', 'admin.platforms.accounts.create')->name('platforms.accounts.create');
    Volt::route('platforms/{platform}/accounts/{account}', 'admin.platforms.accounts.show')->name('platforms.accounts.show');
    Volt::route('platforms/{platform}/accounts/{account}/edit', 'admin.platforms.accounts.edit')->name('platforms.accounts.edit');
    Volt::route('platforms/{platform}/accounts/{account}/credentials', 'admin.platforms.accounts.credentials')->name('platforms.accounts.credentials');

    // Platform-specific order routes
    Volt::route('platforms/{platform}/orders', 'admin.platforms.orders.index')->name('platforms.orders.platform.index');
    Volt::route('platforms/{platform}/orders/{order}', 'admin.platforms.orders.show')->name('platforms.orders.show');

    // Certificate Management routes
    Volt::route('certificates', 'admin.certificates.certificate-list')->name('certificates.index');
    Volt::route('certificates/create', 'admin.certificates.certificate-create')->name('certificates.create');
    Volt::route('certificates/{certificate}/edit', 'admin.certificates.certificate-edit')->name('certificates.edit');
    Volt::route('certificates/{certificate}/preview', 'admin.certificates.certificate-preview')->name('certificates.preview');
    Volt::route('certificates/{certificate}/assignments', 'admin.certificates.certificate-assignments')->name('certificates.assignments');
    Volt::route('certificates/issue', 'admin.certificates.certificate-issue')->name('certificates.issue');
    Volt::route('certificates/issued', 'admin.certificates.certificate-issued-list')->name('certificates.issued');
    Volt::route('certificates/bulk-issue', 'admin.certificates.certificate-bulk-issue')->name('certificates.bulk-issue');
    Route::get('certificates/{certificateIssue}/download', function (\App\Models\CertificateIssue $certificateIssue) {
        if (! $certificateIssue->hasFile()) {
            abort(404, 'Certificate file not found');
        }

        return \Storage::download($certificateIssue->file_path, $certificateIssue->certificate_number.'.pdf');
    })->name('certificates.download');

    // CRM & Automation routes
    Volt::route('crm/all-database', 'crm.all-database')->name('crm.all-database');
    Route::get('crm/export', [StudentController::class, 'exportCrm'])->name('crm.export');

    // Audience routes
    Volt::route('crm/audiences', 'crm.audience-list')->name('crm.audiences.index');
    Volt::route('crm/audiences/create', 'crm.audience-create')->name('crm.audiences.create');
    Volt::route('crm/audiences/{audience}/edit', 'crm.audience-edit')->name('crm.audiences.edit');

    // Broadcast routes
    Volt::route('crm/broadcasts', 'crm.broadcast-list')->name('crm.broadcasts.index');
    Volt::route('crm/broadcasts/create', 'crm.broadcast-create')->name('crm.broadcasts.create');
    Volt::route('crm/broadcasts/{broadcast}', 'crm.broadcast-show')->name('crm.broadcasts.show');
    Volt::route('crm/broadcasts/{broadcast}/edit', 'crm.broadcast-edit')->name('crm.broadcasts.edit');

    // Admin Settings routes
    Route::redirect('settings', 'admin/settings/general');
    Volt::route('settings/general', 'admin.settings-general')->name('admin.settings.general');
    Volt::route('settings/appearance', 'admin.settings-appearance')->name('admin.settings.appearance');
    Volt::route('settings/payment', 'admin.settings-payment')->name('admin.settings.payment');
    Volt::route('settings/email', 'admin.settings-email')->name('admin.settings.email');
    Volt::route('settings/notifications', 'admin.settings-notifications')->name('admin.settings.notifications');
    Volt::route('settings/notifications/{template}/builder', 'admin.react-template-builder')->name('admin.settings.notifications.builder');
    Volt::route('settings/whatsapp', 'admin.settings-whatsapp')->name('admin.settings.whatsapp');

    // Customer Service routes
    Volt::route('customer-service', 'admin.customer-service.dashboard')->name('admin.customer-service.dashboard');
    Volt::route('customer-service/return-refunds', 'admin.customer-service.return-refunds-index')->name('admin.customer-service.return-refunds.index');
    Volt::route('customer-service/return-refunds/create', 'admin.customer-service.return-refunds-create')->name('admin.customer-service.return-refunds.create');
    Volt::route('customer-service/return-refunds/{refund}', 'admin.customer-service.return-refunds-show')->name('admin.customer-service.return-refunds.show');

    // Customer Service - Tickets
    Volt::route('customer-service/tickets', 'admin.customer-service.tickets-index')->name('admin.customer-service.tickets.index');
    Volt::route('customer-service/tickets/create', 'admin.customer-service.tickets-create')->name('admin.customer-service.tickets.create');
    Volt::route('customer-service/tickets/{ticket}', 'admin.customer-service.tickets-show')->name('admin.customer-service.tickets.show');

});

// Live Host Management routes (Admin & Admin Livehost access)
Route::middleware(['auth', 'role:admin,admin_livehost'])->prefix('admin')->name('admin.')->group(function () {
    // Live Host CRUD
    Volt::route('live-hosts', 'admin.live-hosts-list')->name('live-hosts');
    Volt::route('live-hosts/create', 'admin.live-hosts-create')->name('live-hosts.create');
    Volt::route('live-hosts/{host}', 'admin.live-hosts-show')->name('live-hosts.show');
    Volt::route('live-hosts/{host}/edit', 'admin.live-hosts-edit')->name('live-hosts.edit');

    // Schedule Calendar (Main schedule management - spreadsheet style)
    Volt::route('live-schedule-calendar', 'admin.live-schedule-calendar')->name('live-schedule-calendar');

    // Time Slot Configuration
    Volt::route('live-time-slots', 'admin.live-time-slots')->name('live-time-slots');

    // Schedule Reports
    Volt::route('live-schedule-reports', 'admin.live-schedule-reports')->name('live-schedule-reports');

    // Legacy schedule routes (keeping for backward compatibility)
    Volt::route('live-schedules', 'admin.live-schedules-index')->name('live-schedules.index');
    Volt::route('live-schedules/create', 'admin.live-schedules-create')->name('live-schedules.create');
    Volt::route('live-schedules/{schedule}/edit', 'admin.live-schedules-edit')->name('live-schedules.edit');

    // Live Sessions
    Volt::route('live-sessions', 'admin.live-sessions-index')->name('live-sessions.index');
    Volt::route('live-sessions/{session}', 'admin.live-sessions-show')->name('live-sessions.show');

    // Uploaded Sessions (Session Slots)
    Volt::route('session-slots', 'admin.uploaded-sessions')->name('session-slots');
});

// Stripe webhook route - no auth middleware needed
Route::post('stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Payment processing routes - requires auth
Route::middleware(['auth'])->group(function () {
    // Payment creation and processing
    Route::post('invoices/{invoice}/create-payment', [App\Http\Controllers\PaymentController::class, 'createPayment'])->name('payments.create');
    Route::post('invoices/{invoice}/confirm-payment', [App\Http\Controllers\PaymentController::class, 'confirmPayment'])->name('payments.confirm');

    // Payment method management
    Route::post('payment-methods', [App\Http\Controllers\PaymentController::class, 'storePaymentMethod'])->name('payment-methods.store');
    Route::delete('payment-methods/{paymentMethod}', [App\Http\Controllers\PaymentController::class, 'deletePaymentMethod'])->name('payment-methods.delete');
    Route::patch('payment-methods/{paymentMethod}/default', [App\Http\Controllers\PaymentController::class, 'setDefaultPaymentMethod'])->name('payment-methods.default');

    // Admin payment method management (for managing student payment methods) - accessible by admin and class_admin
    Route::middleware(['role:admin,class_admin'])->group(function () {
        Route::post('admin/students/{student}/payment-methods', [App\Http\Controllers\PaymentController::class, 'adminStorePaymentMethod'])->name('admin.students.payment-methods.store');
        Route::delete('admin/students/{student}/payment-methods/{paymentMethod}', [App\Http\Controllers\PaymentController::class, 'adminDeletePaymentMethod'])->name('admin.students.payment-methods.delete');
        Route::patch('admin/students/{student}/payment-methods/{paymentMethod}/default', [App\Http\Controllers\PaymentController::class, 'adminSetDefaultPaymentMethod'])->name('admin.students.payment-methods.default');
        Route::post('admin/students/{student}/payment-methods/generate-magic-link', [App\Http\Controllers\PaymentController::class, 'generateMagicLink'])->name('admin.students.payment-methods.generate-magic-link');
    });
});

require __DIR__.'/auth.php';
