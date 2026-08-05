<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\Enrollment;
use App\Models\ProductCart;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Models\User;
use App\Services\Enrolment\CourseEnrolmentFromOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function storeCourse(array $attrs = [], float $fee = 120): Course
{
    $course = Course::factory()->create(array_merge([
        'status' => 'active',
        'show_on_storefront' => true,
        'name' => 'Kelas Fardhu Ain',
    ], $attrs));

    CourseFeeSettings::create([
        'course_id' => $course->id,
        'fee_amount' => $fee,
        'billing_cycle' => 'monthly',
        'currency' => 'MYR',
        'is_recurring' => true,
    ]);

    return $course->fresh();
}

function guestCourseCart(): ProductCart
{
    return ProductCart::create([
        'session_id' => 'course-session',
        'currency' => 'MYR',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'discount_amount' => 0,
    ]);
}

it('auto-generates a unique slug for a course', function () {
    $a = Course::factory()->create(['name' => 'Tafsir Surah', 'status' => 'active']);
    $b = Course::factory()->create(['name' => 'Tafsir Surah', 'status' => 'active']);

    expect($a->slug)->toBe('tafsir-surah')
        ->and($b->slug)->toBe('tafsir-surah-1');
});

it('lists only storefront-visible courses in the catalog', function () {
    $visible = storeCourse(['name' => 'Visible Course']);
    Course::factory()->create(['name' => 'Hidden Course', 'status' => 'active', 'show_on_storefront' => false]);

    $this->get(route('storefront.courses'))
        ->assertOk()
        ->assertSee('Visible Course')
        ->assertDontSee('Hidden Course');
});

it('shows a visible course detail page and 404s otherwise', function () {
    $visible = storeCourse();
    $hidden = Course::factory()->create(['status' => 'active', 'show_on_storefront' => false]);
    $inactive = storeCourse(['status' => 'inactive']);

    $this->get(route('storefront.course', $visible->slug))->assertOk()->assertSeeLivewire('store.course-cart');
    $this->get(route('storefront.course', $hidden->slug))->assertNotFound();
    $this->get(route('storefront.course', $inactive->slug))->assertNotFound();
});

it('adds a course to the cart at its fee price, once', function () {
    $course = storeCourse(fee: 99);

    Volt::test('store.course-cart', ['course' => $course])->call('add')->assertDispatched('cart-updated');
    // Re-adding the same course is a no-op.
    Volt::test('store.course-cart', ['course' => $course])->call('add');

    $cart = ProductCart::first();

    expect($cart->items()->count())->toBe(1)
        ->and($cart->items()->first()->isCourse())->toBeTrue()
        ->and($cart->items()->first()->course_id)->toBe($course->id)
        ->and((float) $cart->items()->first()->unit_price)->toBe(99.0);
});

it('creates a course order line at checkout', function () {
    $course = storeCourse(fee: 120);
    $cart = guestCourseCart();
    $cart->addCourse($course);

    $order = ProductOrder::createFromCart($cart, ['email' => 'buyer@example.test', 'phone' => '0123456789'], []);
    $item = $order->items()->first();

    expect($item->isCourse())->toBeTrue()
        ->and($item->course_id)->toBe($course->id)
        ->and((float) $item->total_price)->toBe(120.0);
});

it('enrols the buyer into the course when a course order is paid', function () {
    $user = User::factory()->create(['role' => 'student']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $course = storeCourse();

    $order = ProductOrder::factory()->create([
        'source' => 'storefront',
        'customer_id' => $user->id,
        'student_id' => null,
        'payment_status' => 'pending',
    ]);
    $order->items()->create([
        'itemable_type' => Course::class,
        'itemable_id' => $course->id,
        'course_id' => $course->id,
        'product_name' => $course->name,
        'sku' => 'CRS-'.$course->id,
        'quantity_ordered' => 1,
        'unit_price' => 120,
        'total_price' => 120,
        'unit_cost' => 0,
    ]);

    expect(Enrollment::where('student_id', $student->id)->where('course_id', $course->id)->exists())->toBeFalse();

    $order->update(['payment_status' => 'paid', 'paid_time' => now()]);

    expect(Enrollment::where('student_id', $student->id)->where('course_id', $course->id)->whereIn('status', ['enrolled', 'active'])->count())->toBe(1);
});

it('does not enrol a guest course order (no student)', function () {
    $course = storeCourse();
    $order = ProductOrder::factory()->create(['source' => 'storefront', 'customer_id' => null, 'student_id' => null, 'payment_status' => 'pending']);
    $order->items()->create([
        'course_id' => $course->id,
        'product_name' => $course->name,
        'quantity_ordered' => 1,
        'unit_price' => 120,
        'total_price' => 120,
        'unit_cost' => 0,
    ]);

    $created = app(CourseEnrolmentFromOrder::class)->fulfil($order->fresh());

    expect($created)->toBe(0);
});
