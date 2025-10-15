<?php

use App\Jobs\ProcessShipmentImport;
use App\Models\ClassDocumentShipment;
use App\Models\ClassDocumentShipmentItem;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('job can be dispatched successfully', function () {
    Queue::fake();

    $filePath = storage_path('app/imports/test.csv');
    $userId = 1;
    $shipmentId = 1;
    $matchBy = 'name';

    ProcessShipmentImport::dispatch($shipmentId, $filePath, $userId, $matchBy);

    Queue::assertPushed(ProcessShipmentImport::class, function ($job) use ($shipmentId, $filePath, $userId, $matchBy) {
        return $job->shipmentId === $shipmentId
            && $job->filePath === $filePath
            && $job->userId === $userId
            && $job->matchBy === $matchBy;
    });
});

test('job processes csv and updates tracking numbers', function () {
    // Create test data
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student1 = Student::factory()->create();
    $student2 = Student::factory()->create();

    // Create class students
    $classStudent1 = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $classStudent2 = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    // Create shipment manually to avoid factory issues
    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-001',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 2,
        'quantity_per_student' => 1,
        'total_quantity' => 2,
        'scheduled_at' => now(),
    ]);

    $item1 = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent1->id,
        'student_id' => $student1->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $item2 = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent2->id,
        'student_id' => $student2->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV file
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"{$student1->user->name}\",\"{$student1->phone}\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK123\",\"-\",\"-\"\n";
    $csvContent .= "\"{$student2->user->name}\",\"{$student2->phone}\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK456\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id);
    $job->handle();

    // Assert tracking numbers were updated
    expect($item1->fresh()->tracking_number)->toBe('TRACK123');
    expect($item2->fresh()->tracking_number)->toBe('TRACK456');

    // Assert cache has the result
    $result = Cache::get("shipment_import_{$shipment->id}_{$user->id}_result");
    expect($result)->not->toBeNull();
    expect($result['status'])->toBe('completed');
    expect($result['updated'])->toBe(2);
    expect($result['imported'])->toBe(2);

    // Assert file was deleted
    expect(file_exists($filePath))->toBeFalse();
});

test('job handles missing student gracefully', function () {
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student = Student::factory()->create();

    $classStudent = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-002',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    $item = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV with one valid student and one invalid
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"{$student->user->name}\",\"{$student->phone}\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK123\",\"-\",\"-\"\n";
    $csvContent .= "\"Non Existent Student\",\"1234567890\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK999\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id);
    $job->handle();

    // Assert valid student was updated
    expect($item->fresh()->tracking_number)->toBe('TRACK123');

    // Assert result has error for missing student
    $result = Cache::get("shipment_import_{$shipment->id}_{$user->id}_result");
    expect($result['updated'])->toBe(1);
    expect($result['imported'])->toBe(2);
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0])->toContain('Non Existent Student');
});

test('job handles missing file error', function () {
    $user = User::factory()->create();
    $shipmentId = 1;
    $filePath = storage_path('app/imports/non_existent.csv');

    $job = new ProcessShipmentImport($shipmentId, $filePath, $user->id);

    expect(fn () => $job->handle())->toThrow(\Exception::class, 'CSV file not found');

    // Assert error is cached
    $result = Cache::get("shipment_import_{$shipmentId}_{$user->id}_result");
    expect($result)->not->toBeNull();
    expect($result['status'])->toBe('failed');
});

test('job skips rows without tracking numbers', function () {
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student = Student::factory()->create();

    $classStudent = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-003',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    $item = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV with empty tracking number
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"{$student->user->name}\",\"{$student->phone}\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"-\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id);
    $job->handle();

    // Assert tracking number was NOT updated
    expect($item->fresh()->tracking_number)->toBeNull();

    $result = Cache::get("shipment_import_{$shipment->id}_{$user->id}_result");
    expect($result['updated'])->toBe(0);
});

test('job matches students by phone number when matchBy is phone', function () {
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student1 = Student::factory()->create(['phone' => '0123456789']);
    $student2 = Student::factory()->create(['phone' => '0198765432']);

    $classStudent1 = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student1->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $classStudent2 = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student2->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-004',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 2,
        'quantity_per_student' => 1,
        'total_quantity' => 2,
        'scheduled_at' => now(),
    ]);

    $item1 = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent1->id,
        'student_id' => $student1->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $item2 = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent2->id,
        'student_id' => $student2->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV with phone numbers
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"Some Name\",\"0123456789\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK123\",\"-\",\"-\"\n";
    $csvContent .= "\"Another Name\",\"0198765432\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK456\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job with matchBy=phone
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id, 'phone');
    $job->handle();

    // Assert tracking numbers were updated based on phone matching
    expect($item1->fresh()->tracking_number)->toBe('TRACK123');
    expect($item2->fresh()->tracking_number)->toBe('TRACK456');

    $result = Cache::get("shipment_import_{$shipment->id}_{$user->id}_result");
    expect($result['updated'])->toBe(2);
});

test('job updates student address when provided in CSV', function () {
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student = Student::factory()->create([
        'phone' => '0123456789',
        'address_line_1' => 'Old Address 1',
        'address_line_2' => 'Old Address 2',
        'city' => 'Old City',
        'state' => 'Old State',
        'postcode' => '00000',
        'country' => 'Old Country',
    ]);

    $classStudent = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-005',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    $item = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV with updated address
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"{$student->user->name}\",\"{$student->phone}\",\"New Address 1\",\"New Address 2\",\"New City\",\"New State\",\"54321\",\"New Country\",1,Pending,\"TRACK789\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id, 'name');
    $job->handle();

    // Assert tracking number was updated
    expect($item->fresh()->tracking_number)->toBe('TRACK789');

    // Assert address was updated
    $updatedStudent = $student->fresh();
    expect($updatedStudent->address_line_1)->toBe('New Address 1');
    expect($updatedStudent->address_line_2)->toBe('New Address 2');
    expect($updatedStudent->city)->toBe('New City');
    expect($updatedStudent->state)->toBe('New State');
    expect($updatedStudent->postcode)->toBe('54321');
    expect($updatedStudent->country)->toBe('New Country');
});

test('job handles phone matching with missing phone number', function () {
    $user = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create();
    $class = createClass($course, $teacher);

    $student = Student::factory()->create(['phone' => '0123456789']);

    $classStudent = ClassStudent::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-006',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    $item = ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV with empty phone number
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"Some Name\",\"\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK123\",\"-\",\"-\"\n";

    $filePath = storage_path('app/imports/test_'.time().'.csv');
    file_put_contents($filePath, $csvContent);

    // Run the job with matchBy=phone
    $job = new ProcessShipmentImport($shipment->id, $filePath, $user->id, 'phone');
    $job->handle();

    // Assert tracking number was NOT updated
    expect($item->fresh()->tracking_number)->toBeNull();

    // Assert error was recorded
    $result = Cache::get("shipment_import_{$shipment->id}_{$user->id}_result");
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0])->toContain('Phone number is empty');
});
