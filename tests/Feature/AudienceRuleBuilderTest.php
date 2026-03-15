<?php

use App\Models\ClassStudent;
use App\Models\ProductOrder;
use App\Models\Student;
use App\Services\AudienceRuleBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('filters by total spending greater than', function () {
    $highSpender = Student::factory()->create();
    ProductOrder::factory()->create([
        'student_id' => $highSpender->id,
        'total_amount' => 600,
        'paid_time' => now(),
    ]);

    $lowSpender = Student::factory()->create();
    ProductOrder::factory()->create([
        'student_id' => $lowSpender->id,
        'total_amount' => 100,
        'paid_time' => now(),
    ]);

    $query = AudienceRuleBuilder::buildQuery([
        ['field' => 'total_spending', 'operator' => '>', 'value' => '500'],
    ]);

    // Use toRawSql() to work around SQLite float binding limitation
    $results = DB::select($query->toRawSql());

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe($highSpender->id);
});

test('filters by student status', function () {
    $active = Student::factory()->create(['status' => 'active']);
    $inactive = Student::factory()->create(['status' => 'inactive']);

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'student_status', 'operator' => 'is', 'value' => 'active'],
    ])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($active->id);
});

test('filters by country', function () {
    $my = Student::factory()->create(['country' => 'Malaysia']);
    $sg = Student::factory()->create(['country' => 'Singapore']);

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'country', 'operator' => 'is', 'value' => 'Malaysia'],
    ])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($my->id);
});

test('filters by has paid orders', function () {
    $withPaid = Student::factory()->create();
    ProductOrder::factory()->create([
        'student_id' => $withPaid->id,
        'paid_time' => now(),
    ]);

    $withoutPaid = Student::factory()->create();
    ProductOrder::factory()->create([
        'student_id' => $withoutPaid->id,
        'paid_time' => null,
    ]);

    $noPaidResults = AudienceRuleBuilder::buildQuery([
        ['field' => 'has_paid_orders', 'operator' => 'is', 'value' => 'no'],
    ])->get();

    expect($noPaidResults)->toHaveCount(1)
        ->and($noPaidResults->first()->id)->toBe($withoutPaid->id);
});

test('combines rules with ALL match mode', function () {
    $match = Student::factory()->create([
        'status' => 'active',
        'country' => 'Malaysia',
    ]);
    Student::factory()->create([
        'status' => 'active',
        'country' => 'Singapore',
    ]);
    Student::factory()->create([
        'status' => 'inactive',
        'country' => 'Malaysia',
    ]);

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'student_status', 'operator' => 'is', 'value' => 'active'],
        ['field' => 'country', 'operator' => 'is', 'value' => 'Malaysia'],
    ], 'all')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($match->id);
});

test('combines rules with ANY match mode', function () {
    $activeInMy = Student::factory()->create([
        'status' => 'active',
        'country' => 'Malaysia',
    ]);
    $inactiveSg = Student::factory()->create([
        'status' => 'inactive',
        'country' => 'Singapore',
    ]);
    Student::factory()->create([
        'status' => 'active',
        'country' => 'Singapore',
    ]);

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'student_status', 'operator' => 'is', 'value' => 'inactive'],
        ['field' => 'country', 'operator' => 'is', 'value' => 'Malaysia'],
    ], 'any')->get();

    $ids = $results->pluck('id')->all();
    expect($ids)->toContain($activeInMy->id)
        ->and($ids)->toContain($inactiveSg->id)
        ->and($results)->toHaveCount(2);
});

test('returns all students when no rules applied', function () {
    Student::factory()->count(3)->create();

    $results = AudienceRuleBuilder::buildQuery([])->get();

    expect($results)->toHaveCount(3);
});

test('ignores incomplete rules', function () {
    Student::factory()->count(2)->create();

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => '', 'operator' => 'is', 'value' => 'active'],
        ['field' => 'student_status', 'operator' => '', 'value' => 'active'],
        ['field' => 'student_status', 'operator' => 'is', 'value' => ''],
    ])->get();

    expect($results)->toHaveCount(2);
});

test('filters by enrollment count', function () {
    $enrolled = Student::factory()->create();
    ClassStudent::factory()->count(3)->create(['student_id' => $enrolled->id, 'status' => 'active']);

    Student::factory()->create();

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'enrollment_count', 'operator' => '>=', 'value' => '2'],
    ])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($enrolled->id);
});

test('filters by gender', function () {
    $male = Student::factory()->create(['gender' => 'male']);
    Student::factory()->create(['gender' => 'female']);

    $results = AudienceRuleBuilder::buildQuery([
        ['field' => 'gender', 'operator' => 'is', 'value' => 'male'],
    ])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($male->id);
});
