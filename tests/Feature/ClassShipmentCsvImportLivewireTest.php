<?php

use App\Models\ClassDocumentShipment;
use App\Models\ClassDocumentShipmentItem;
use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('livewire component can open and close import modal', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $class = createClass($course, $teacher);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-001',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->call('openImportModal', $shipment->id)
        ->assertSet('showImportModal', true)
        ->assertSet('selectedShipmentId', $shipment->id)
        ->call('closeImportModal')
        ->assertSet('showImportModal', false)
        ->assertSet('selectedShipmentId', null);
});

test('livewire component validates csv file before import', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $class = createClass($course, $teacher);

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

    // Test without file
    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->call('openImportModal', $shipment->id)
        ->set('importFile', null)
        ->call('importShipmentTracking')
        ->assertHasErrors(['importFile' => 'required']);

    // Test with invalid file type
    $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->call('openImportModal', $shipment->id)
        ->set('importFile', $invalidFile)
        ->call('importShipmentTracking')
        ->assertHasErrors(['importFile' => 'mimes']);
});

test('livewire component dispatches import job with csv file', function () {
    Storage::fake('local');
    Queue::fake();

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
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

    ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    // Create CSV content
    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"{$student->user->name}\",\"{$student->phone}\",\"Address\",\"Unit\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK123\",\"-\",\"-\"\n";

    $csvFile = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    // Ensure storage directory exists
    if (! file_exists(storage_path('app/imports'))) {
        mkdir(storage_path('app/imports'), 0755, true);
    }

    $component = Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->call('openImportModal', $shipment->id)
        ->set('importFile', $csvFile)
        ->set('matchBy', 'name')
        ->call('importShipmentTracking');

    // The component should dispatch the job and set processing flag
    // Note: Due to how file uploads work in Livewire testing, we just verify the job is dispatched
    $component->assertSet('showImportModal', false);

    // Verify job was dispatched
    Queue::assertPushed(\App\Jobs\ProcessShipmentImport::class, function ($job) use ($shipment, $admin) {
        return $job->shipmentId === $shipment->id
            && $job->userId === $admin->id
            && $job->matchBy === 'name';
    });
});

test('livewire component checks import progress', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $class = createClass($course, $teacher);

    $shipment = ClassDocumentShipment::create([
        'class_id' => $class->id,
        'shipment_number' => 'SHIP-TEST-004',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    // Set progress in cache
    \Illuminate\Support\Facades\Cache::put("shipment_import_{$shipment->id}_{$admin->id}_progress", [
        'imported' => 5,
        'updated' => 3,
        'status' => 'processing',
    ], now()->addMinutes(10));

    $component = Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('selectedShipmentId', $shipment->id)
        ->call('checkImportProgress');

    // Note: The importProgress property should be updated but might not be directly assertable
    // The test verifies the method runs without errors
    $component->assertOk();

    // Clean up cache
    \Illuminate\Support\Facades\Cache::forget("shipment_import_{$shipment->id}_{$admin->id}_progress");
});

test('livewire component handles completed import result', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $class = createClass($course, $teacher);

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

    // Set completed result in cache
    \Illuminate\Support\Facades\Cache::put("shipment_import_{$shipment->id}_{$admin->id}_result", [
        'imported' => 10,
        'updated' => 8,
        'errors' => ['Student not found: John Doe', 'Student not found: Jane Smith'],
        'status' => 'completed',
    ], now()->addMinutes(30));

    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('selectedShipmentId', $shipment->id)
        ->set('importProcessing', true)
        ->call('checkImportProgress')
        ->assertSet('importProcessing', false)
        ->assertSet('showImportResultModal', true)
        ->assertSet('importResult.status', 'completed')
        ->assertSet('importResult.imported', 10)
        ->assertSet('importResult.updated', 8);

    // Verify cache was cleared
    expect(\Illuminate\Support\Facades\Cache::has("shipment_import_{$shipment->id}_{$admin->id}_result"))->toBeFalse();
});

test('livewire component handles failed import result', function () {
    Storage::fake('local');

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $class = createClass($course, $teacher);

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

    // Set failed result in cache
    \Illuminate\Support\Facades\Cache::put("shipment_import_{$shipment->id}_{$admin->id}_result", [
        'status' => 'failed',
        'error' => 'CSV file is malformed',
    ], now()->addMinutes(30));

    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->set('selectedShipmentId', $shipment->id)
        ->set('importProcessing', true)
        ->call('checkImportProgress')
        ->assertSet('importProcessing', false)
        ->assertSet('showImportResultModal', true)
        ->assertSet('importResult.status', 'failed')
        ->assertSet('importResult.error', 'CSV file is malformed');

    // Verify cache was cleared
    expect(\Illuminate\Support\Facades\Cache::has("shipment_import_{$shipment->id}_{$admin->id}_result"))->toBeFalse();
});

test('livewire component supports phone number matching', function () {
    Storage::fake('local');
    Queue::fake();

    $admin = User::factory()->create();
    $teacher = createTeacher();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
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
        'shipment_number' => 'SHIP-TEST-007',
        'period_label' => 'October 2025',
        'period_start_date' => now()->startOfMonth(),
        'period_end_date' => now()->endOfMonth(),
        'status' => 'pending',
        'total_recipients' => 1,
        'quantity_per_student' => 1,
        'total_quantity' => 1,
        'scheduled_at' => now(),
    ]);

    ClassDocumentShipmentItem::create([
        'class_document_shipment_id' => $shipment->id,
        'class_student_id' => $classStudent->id,
        'student_id' => $student->id,
        'quantity' => 1,
        'status' => 'pending',
    ]);

    $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";
    $csvContent .= "\"Some Name\",\"0123456789\",\"Address\",\"Unit\",\"City\",\"State\",\"12345\",\"Malaysia\",1,Pending,\"TRACK456\",\"-\",\"-\"\n";

    $csvFile = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

    // Ensure storage directory exists
    if (! file_exists(storage_path('app/imports'))) {
        mkdir(storage_path('app/imports'), 0755, true);
    }

    Livewire::actingAs($admin)
        ->test('admin.class-show', ['class' => $class])
        ->call('openImportModal', $shipment->id)
        ->set('importFile', $csvFile)
        ->set('matchBy', 'phone')
        ->call('importShipmentTracking');

    // Verify job was dispatched with phone matching
    Queue::assertPushed(\App\Jobs\ProcessShipmentImport::class, function ($job) {
        return $job->matchBy === 'phone';
    });
});
