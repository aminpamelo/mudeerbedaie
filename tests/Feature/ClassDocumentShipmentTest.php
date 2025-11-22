<?php

use App\Models\ClassDocumentShipment;
use App\Models\ClassModel;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create document shipment for class with paid students', function () {
    // Create necessary records
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $product = Product::factory()->create(['track_quantity' => false]);
    $warehouse = Warehouse::factory()->create();

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
        'shipment_quantity_per_student' => 1,
    ]);

    // Add student with paid order (no subscription)
    $student = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
    ]);

    // Create paid order for this period
    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    \App\Models\Order::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => \App\Models\Order::STATUS_PAID,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    // Generate shipment
    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);

    expect($shipment)->not->toBeNull()
        ->and($shipment->class_id)->toBe($class->id)
        ->and($shipment->total_recipients)->toBe(1)
        ->and($shipment->status)->toBe('pending')
        ->and($shipment->items()->count())->toBe(1);
});

test('cannot create duplicate shipment for same period', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $product = Product::factory()->create(['track_quantity' => false]);
    $warehouse = Warehouse::factory()->create();

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
    ]);

    $student = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
    ]);

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    // Create paid order for this period
    \App\Models\Order::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => \App\Models\Order::STATUS_PAID,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    // Create first shipment
    $shipment1 = $class->generateShipmentForPeriod($periodStart, $periodEnd);
    expect($shipment1)->not->toBeNull();

    // Try to create duplicate
    $shipment2 = $class->generateShipmentForPeriod($periodStart, $periodEnd);
    expect($shipment2)->toBeNull();
});

test('shipment reserves and deducts stock correctly', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $product = Product::factory()->create(['track_quantity' => true]);

    // Create stock level
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
    ]);

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_quantity_per_student' => 2,
    ]);

    // Add 5 students
    for ($i = 0; $i < 5; $i++) {
        $student = Student::factory()->create();
        ClassStudent::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);

    // Reserve stock
    $result = $shipment->reserveStock();
    expect($result)->toBeTrue();

    $stockLevel->refresh();
    expect($stockLevel->reserved_quantity)->toBe(10) // 5 students * 2 qty
        ->and($stockLevel->available_quantity)->toBe(90);

    // Deduct stock
    $result = $shipment->deductStock();
    expect($result)->toBeTrue();

    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(90)
        ->and($stockLevel->reserved_quantity)->toBe(0)
        ->and($stockLevel->available_quantity)->toBe(90);
});

test('shipment cannot be created with insufficient stock', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $product = Product::factory()->create(['track_quantity' => true]);

    // Create low stock
    StockLevel::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
        'shipment_quantity_per_student' => 1,
    ]);

    // Add 10 students (need 10, only have 5)
    for ($i = 0; $i < 10; $i++) {
        $student = Student::factory()->create();
        ClassStudent::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);

    // Try to reserve - should fail
    $result = $shipment->reserveStock();
    expect($result)->toBeFalse();
});

test('shipment status can be updated', function () {
    $shipment = ClassDocumentShipment::factory()->create(['status' => 'pending']);

    $shipment->markAsProcessing();
    expect($shipment->status)->toBe('processing')
        ->and($shipment->processed_at)->not->toBeNull();

    $shipment->markAsShipped();
    expect($shipment->status)->toBe('shipped')
        ->and($shipment->shipped_at)->not->toBeNull();

    $shipment->markAsDelivered();
    expect($shipment->status)->toBe('delivered')
        ->and($shipment->delivered_at)->not->toBeNull();
});

test('artisan command generates shipments for eligible classes', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $product = Product::factory()->create(['track_quantity' => false]);
    $warehouse = Warehouse::factory()->create();

    // Create class with shipment enabled
    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_start_date' => now(),
    ]);

    $student = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
    ]);

    // Create paid order for current month
    \App\Models\Order::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => \App\Models\Order::STATUS_PAID,
        'period_start' => now()->startOfMonth(),
        'period_end' => now()->endOfMonth(),
    ]);

    // Run command
    $this->artisan('class:generate-shipments')
        ->assertSuccessful();

    // Check shipment was created
    expect(ClassDocumentShipment::count())->toBe(1);

    $shipment = ClassDocumentShipment::first();
    expect($shipment->class_id)->toBe($class->id)
        ->and($shipment->total_recipients)->toBe(1);
});

test('individual shipment item deducts stock when shipped', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $product = Product::factory()->create(['track_quantity' => true]);

    // Create stock level
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
    ]);

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
        'shipment_quantity_per_student' => 1,
    ]);

    // Add 3 students with active enrollments and subscriptions
    for ($i = 0; $i < 3; $i++) {
        $student = Student::factory()->create();
        ClassStudent::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        // Create enrollment with active subscription
        \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'stripe_subscription_id' => 'sub_test_'.uniqid(),
            'subscription_status' => 'active',
        ]);
    }

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);
    expect($shipment->items()->count())->toBe(3);

    // Mark individual item as shipped (not the entire shipment)
    $item = $shipment->items()->first();
    $item->markAsShipped();

    // Stock should be deducted for this one item
    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(99)
        ->and($item->hasStockBeenDeducted())->toBeTrue();

    // Verify stock movement was created
    $stockMovement = \App\Models\StockMovement::where('reference_type', \App\Models\ClassDocumentShipmentItem::class)
        ->where('reference_id', $item->id)
        ->first();

    expect($stockMovement)->not->toBeNull()
        ->and($stockMovement->quantity)->toBe(-1)
        ->and($stockMovement->type)->toBe('out')
        ->and($stockMovement->quantity_before)->toBe(100)
        ->and($stockMovement->quantity_after)->toBe(99);

    // Ship another item
    $item2 = $shipment->items()->skip(1)->first();
    $item2->markAsShipped();

    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(98);

    // Verify we can't double-deduct
    $item->markAsShipped(); // Call again
    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(98); // Should still be 98, not 97
});

test('shipment level processing does not double-deduct after individual items are shipped', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $product = Product::factory()->create(['track_quantity' => true]);

    // Create stock level with 100 items
    $stockLevel = StockLevel::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 0,
    ]);

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
        'shipment_quantity_per_student' => 1,
    ]);

    // Add 5 students with active enrollments
    for ($i = 0; $i < 5; $i++) {
        $student = Student::factory()->create();
        ClassStudent::factory()->create([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        \App\Models\Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'stripe_subscription_id' => 'sub_test_'.uniqid(),
            'subscription_status' => 'active',
        ]);
    }

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);
    expect($shipment->items()->count())->toBe(5)
        ->and($shipment->total_quantity)->toBe(5);

    // Ship 2 items individually
    $item1 = $shipment->items()->first();
    $item1->markAsShipped();

    $item2 = $shipment->items()->skip(1)->first();
    $item2->markAsShipped();

    // Stock should be deducted by 2
    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(98);

    // Now click "Start Processing" on the entire shipment
    $shipment->markAsProcessing();

    // Stock should only deduct the remaining 3 items (not all 5)
    $stockLevel->refresh();
    expect($stockLevel->quantity)->toBe(95); // 98 - 3 = 95 (not 93 if it double-deducted)

    // Verify stock movements
    $shipmentMovements = \App\Models\StockMovement::where('reference_type', \App\Models\ClassDocumentShipment::class)
        ->where('reference_id', $shipment->id)
        ->get();

    expect($shipmentMovements->count())->toBe(1)
        ->and($shipmentMovements->first()->quantity)->toBe(-3); // Only 3 items deducted at shipment level

    $itemMovements = \App\Models\StockMovement::where('reference_type', \App\Models\ClassDocumentShipmentItem::class)
        ->whereIn('reference_id', [$item1->id, $item2->id])
        ->get();

    expect($itemMovements->count())->toBe(2)
        ->and($itemMovements->sum('quantity'))->toBe(-2); // 2 items deducted at item level
});

test('shipment includes both subscribed students and paid students without subscription', function () {
    $course = Course::factory()->create();
    $teacher = Teacher::factory()->create();
    $product = Product::factory()->create(['track_quantity' => false]);
    $warehouse = Warehouse::factory()->create();

    $class = ClassModel::factory()->create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'enable_document_shipment' => true,
        'shipment_product_id' => $product->id,
        'shipment_warehouse_id' => $warehouse->id,
        'shipment_frequency' => 'monthly',
        'shipment_start_date' => now(),
        'shipment_quantity_per_student' => 1,
    ]);

    $periodStart = now()->startOfMonth();
    $periodEnd = now()->endOfMonth();

    // Student 1: Has active subscription (no paid order needed)
    $student1 = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'status' => 'active',
    ]);
    \App\Models\Enrollment::factory()->create([
        'student_id' => $student1->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test_'.uniqid(),
        'subscription_status' => 'active',
    ]);

    // Student 2: Has paid for this period but NO subscription
    $student2 = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'status' => 'active',
    ]);
    \App\Models\Order::factory()->create([
        'student_id' => $student2->id,
        'course_id' => $course->id,
        'status' => \App\Models\Order::STATUS_PAID,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    // Student 3: Has trialing subscription (should be included)
    $student3 = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student3->id,
        'status' => 'active',
    ]);
    \App\Models\Enrollment::factory()->create([
        'student_id' => $student3->id,
        'course_id' => $course->id,
        'stripe_subscription_id' => 'sub_test_'.uniqid(),
        'subscription_status' => 'trialing',
    ]);

    // Student 4: Has pending order (should NOT be included)
    $student4 = Student::factory()->create();
    ClassStudent::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student4->id,
        'status' => 'active',
    ]);
    \App\Models\Order::factory()->create([
        'student_id' => $student4->id,
        'course_id' => $course->id,
        'status' => \App\Models\Order::STATUS_PENDING,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    // Generate shipment
    $shipment = $class->generateShipmentForPeriod($periodStart, $periodEnd);

    // Should include 3 students: student1 (active subscription), student2 (paid), student3 (trialing subscription)
    // Should NOT include student4 (pending order only)
    expect($shipment)->not->toBeNull()
        ->and($shipment->total_recipients)->toBe(3)
        ->and($shipment->items()->count())->toBe(3);

    // Verify the correct students are included
    $includedStudentIds = $shipment->items()->pluck('student_id')->toArray();
    expect($includedStudentIds)->toContain($student1->id)
        ->and($includedStudentIds)->toContain($student2->id)
        ->and($includedStudentIds)->toContain($student3->id)
        ->and($includedStudentIds)->not->toContain($student4->id);
});
