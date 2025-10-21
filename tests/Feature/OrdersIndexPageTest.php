<?php

use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('orders index page loads successfully for admin', function () {
    actingAs($this->admin);

    $response = $this->get(route('orders.index'));

    $response->assertSuccessful();
});

test('orders index displays students with users correctly', function () {
    actingAs($this->admin);

    $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = Course::factory()->create(['name' => 'Test Course']);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => Order::STATUS_PAID,
        'amount' => 100,
    ]);

    $response = $this->get(route('orders.index'));

    $response->assertSuccessful();
    $response->assertSee('John Doe');
    $response->assertSee('john@example.com');
    $response->assertSee('Test Course');
    $response->assertSee($order->order_number);
});

test('orders index handles multiple orders correctly', function () {
    actingAs($this->admin);

    $students = Student::factory(3)->create();
    $course = Course::factory()->create();

    foreach ($students as $student) {
        Order::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    $response = $this->get(route('orders.index'));

    $response->assertSuccessful();
    $response->assertSee($course->name);
});
