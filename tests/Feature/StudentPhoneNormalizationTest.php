<?php

use App\Helpers\PhoneNumberHelper;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use App\Services\TikTokOrderProcessor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('phone number helper detects masked phone numbers', function () {
    expect(PhoneNumberHelper::isMasked('(+60)112*****40'))->toBeTrue();
    expect(PhoneNumberHelper::isMasked('60148****10'))->toBeTrue();
    expect(PhoneNumberHelper::isMasked('+60148*****10'))->toBeTrue();
    expect(PhoneNumberHelper::isMasked('60148271110'))->toBeFalse();
    expect(PhoneNumberHelper::isMasked('+60148271110'))->toBeFalse();
});

test('phone number helper normalizes various phone formats to same value', function () {
    $phone1 = PhoneNumberHelper::normalize('60148271110');
    $phone2 = PhoneNumberHelper::normalize('+60148271110');
    $phone3 = PhoneNumberHelper::normalize('(+60)148271110');
    $phone4 = PhoneNumberHelper::normalize('0148271110'); // Malaysian format without country code

    expect($phone1)->toBe('60148271110');
    expect($phone2)->toBe('60148271110');
    expect($phone3)->toBe('60148271110');
    expect($phone4)->toBe('60148271110');
});

test('phone number helper returns null for masked numbers', function () {
    expect(PhoneNumberHelper::normalize('(+60)112*****40'))->toBeNull();
    expect(PhoneNumberHelper::normalize('60148****10'))->toBeNull();
});

test('phone number helper correctly compares different formats', function () {
    expect(PhoneNumberHelper::areEqual('60148271110', '+60148271110'))->toBeTrue();
    expect(PhoneNumberHelper::areEqual('0148271110', '60148271110'))->toBeTrue();
    expect(PhoneNumberHelper::areEqual('(+60)148271110', '+60148271110'))->toBeTrue();
    expect(PhoneNumberHelper::areEqual('60148271110', '60148271111'))->toBeFalse();
});

test('tiktok order with masked phone does not create student', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    $orderData = [
        'order_id' => 'TEST-MASKED-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'John Doe',
        'customer_phone' => '(+60)112*****40', // Masked phone
        'order_amount' => 100,
    ];

    $result = $processor->processOrderRow($orderData);

    expect($result)->toHaveKey('product_order');
    expect($result['product_order']->student_id)->toBeNull();
    expect(Student::count())->toBe(0); // No student created
});

test('tiktok order with unmasked phone creates student', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    $orderData = [
        'order_id' => 'TEST-UNMASKED-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'Jane Smith',
        'customer_phone' => '60148271110', // Unmasked phone
        'order_amount' => 150,
    ];

    $result = $processor->processOrderRow($orderData);

    expect($result)->toHaveKey('product_order');
    expect($result['product_order']->student_id)->not->toBeNull();

    $student = Student::first();
    expect($student)->not->toBeNull();
    expect($student->phone)->toBe('60148271110');
    expect($student->user->name)->toBe('Jane Smith');
});

test('tiktok order with different phone formats links to same student', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    // First order with format: 60148271110
    $orderData1 = [
        'order_id' => 'TEST-FORMAT-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'Test User',
        'customer_phone' => '60148271110',
        'order_amount' => 100,
    ];

    $result1 = $processor->processOrderRow($orderData1);

    // Second order with format: +60148271110 (same number, different format)
    $orderData2 = [
        'order_id' => 'TEST-FORMAT-002',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'Test User',
        'customer_phone' => '+60148271110',
        'order_amount' => 200,
    ];

    $result2 = $processor->processOrderRow($orderData2);

    // Should only create ONE student
    expect(Student::count())->toBe(1);

    // Both orders should link to the same student
    expect($result1['product_order']->student_id)->toBe($result2['product_order']->student_id);

    $student = Student::first();
    expect($student->phone)->toBe('60148271110'); // Normalized format
});

test('tiktok order updates masked phone to unmasked phone for existing order', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    // First import with masked phone
    $orderData1 = [
        'order_id' => 'TEST-UPDATE-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'John***',
        'customer_phone' => '(+60)148****10',
        'order_amount' => 100,
    ];

    $result1 = $processor->processOrderRow($orderData1);

    expect($result1['product_order']->customer_phone)->toBe('(+60)148****10');
    expect($result1['product_order']->student_id)->toBeNull(); // No student with masked phone

    // Second import of same order with unmasked phone
    $orderData2 = [
        'order_id' => 'TEST-UPDATE-001', // Same order ID
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'John Doe',
        'customer_phone' => '60148271110', // Now unmasked
        'order_amount' => 100,
    ];

    $result2 = $processor->processOrderRow($orderData2);

    $updatedOrder = ProductOrder::find($result2['product_order']->id);

    expect($updatedOrder->customer_phone)->toBe('60148271110'); // Updated to normalized unmasked
    expect($updatedOrder->student_id)->not->toBeNull(); // Now linked to student
    expect(Student::count())->toBe(1); // Student created
});

test('student phone is stored in normalized format', function () {
    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    $orderData = [
        'order_id' => 'TEST-NORMALIZE-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'Test User',
        'customer_phone' => '+60148271110', // With + prefix
        'order_amount' => 100,
    ];

    $processor->processOrderRow($orderData);

    $student = Student::first();
    expect($student->phone)->toBe('60148271110'); // Stored without + prefix
});

test('existing student is updated when better unmasked data is available', function () {
    // Create existing student with minimal data
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'student_60148271110@mudeerbedaie.local',
    ]);

    $student = Student::factory()->create([
        'user_id' => $user->id,
        'phone' => '60148271110',
        'address' => null,
    ]);

    $platform = Platform::factory()->create(['slug' => 'tiktok-shop', 'name' => 'TikTok Shop']);
    $account = PlatformAccount::factory()->create(['platform_id' => $platform->id]);

    $processor = new TikTokOrderProcessor($platform, $account, [], []);

    $orderData = [
        'order_id' => 'TEST-UPDATE-STUDENT-001',
        'product_name' => 'Test Product',
        'quantity' => 1,
        'created_time' => now(),
        'customer_name' => 'Updated Name',
        'customer_phone' => '60148271110',
        'detail_address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'Selangor',
        'postal_code' => '50000',
        'country' => 'Malaysia',
        'order_amount' => 100,
    ];

    $processor->processOrderRow($orderData);

    $student->refresh();
    $student->user->refresh();

    expect($student->user->name)->toBe('Updated Name');
    expect($student->address)->toContain('123 Main St');
    expect($student->address)->toContain('Kuala Lumpur');
});
