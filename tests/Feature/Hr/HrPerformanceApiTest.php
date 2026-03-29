<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\RatingScale;
use App\Models\ReviewCycle;
use App\Models\ReviewKpi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPerformanceAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createPerformanceSetup(): array
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $admin = User::factory()->create(['role' => 'admin']);

    return compact('department', 'position', 'admin');
}

function seedRatingScales(): void
{
    $scales = [
        ['score' => 1, 'label' => 'Unsatisfactory', 'color' => '#EF4444'],
        ['score' => 2, 'label' => 'Needs Improvement', 'color' => '#F97316'],
        ['score' => 3, 'label' => 'Meets Expectations', 'color' => '#EAB308'],
        ['score' => 4, 'label' => 'Exceeds Expectations', 'color' => '#22C55E'],
        ['score' => 5, 'label' => 'Outstanding', 'color' => '#3B82F6'],
    ];

    foreach ($scales as $scale) {
        RatingScale::create($scale);
    }
}

test('unauthenticated users get 401 on performance endpoints', function () {
    $this->getJson('/api/hr/performance/dashboard')->assertUnauthorized();
    $this->getJson('/api/hr/performance/cycles')->assertUnauthorized();
    $this->getJson('/api/hr/performance/reviews')->assertUnauthorized();
});

test('admin can create review cycle', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/performance/cycles', [
        'name' => 'Q1 2026 Review',
        'type' => 'quarterly',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
        'submission_deadline' => '2026-04-14',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Q1 2026 Review');
});

test('admin can activate review cycle and auto-create reviews', function () {
    $setup = createPerformanceSetup();
    $employee = Employee::factory()->create([
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
        'status' => 'active',
    ]);

    $cycle = ReviewCycle::factory()->create(['created_by' => $setup['admin']->id]);

    $response = $this->actingAs($setup['admin'])->patchJson("/api/hr/performance/cycles/{$cycle->id}/activate");

    $response->assertSuccessful();
    expect(PerformanceReview::where('review_cycle_id', $cycle->id)->count())->toBeGreaterThanOrEqual(1);
});

test('admin can create KPI template', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->postJson('/api/hr/performance/kpis', [
        'title' => 'Customer Satisfaction',
        'target' => '90% satisfaction rate',
        'weight' => 25,
        'category' => 'quantitative',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Customer Satisfaction');
});

test('admin can submit manager review and complete with rating', function () {
    seedRatingScales();
    $admin = createPerformanceAdmin();
    $review = PerformanceReview::factory()->create(['status' => 'self_assessment']);

    $kpi1 = ReviewKpi::create([
        'performance_review_id' => $review->id,
        'title' => 'KPI 1',
        'target' => 'Target 1',
        'weight' => 60,
        'self_score' => 4,
    ]);
    $kpi2 = ReviewKpi::create([
        'performance_review_id' => $review->id,
        'title' => 'KPI 2',
        'target' => 'Target 2',
        'weight' => 40,
        'self_score' => 3,
    ]);

    // Submit manager review
    $response = $this->actingAs($admin)->putJson("/api/hr/performance/reviews/{$review->id}/manager-review", [
        'manager_notes' => 'Good performance overall.',
        'kpis' => [
            ['id' => $kpi1->id, 'manager_score' => 4, 'manager_comments' => 'Excellent'],
            ['id' => $kpi2->id, 'manager_score' => 3, 'manager_comments' => 'Good'],
        ],
    ]);
    $response->assertSuccessful();

    // Complete review
    $response = $this->actingAs($admin)->patchJson("/api/hr/performance/reviews/{$review->id}/complete");
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    // Rating = (4*60 + 3*40) / (60+40) = 3.6
    expect($response->json('data.overall_rating'))->toBe('3.6');
});

test('admin can create PIP with goals', function () {
    $setup = createPerformanceSetup();
    $employee = Employee::factory()->create([
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
    ]);
    $adminEmployee = Employee::factory()->create([
        'user_id' => $setup['admin']->id,
        'department_id' => $setup['department']->id,
        'position_id' => $setup['position']->id,
    ]);

    $response = $this->actingAs($setup['admin'])->postJson('/api/hr/performance/pips', [
        'employee_id' => $employee->id,
        'reason' => 'Performance below expectations in Q1.',
        'start_date' => '2026-04-01',
        'end_date' => '2026-06-30',
        'goals' => [
            ['title' => 'Improve response time', 'target_date' => '2026-05-15'],
            ['title' => 'Complete training modules', 'target_date' => '2026-05-30'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data.goals');
});

test('admin can get performance dashboard stats', function () {
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/performance/dashboard');

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['active_cycles', 'total_reviews', 'completion_rate', 'active_pips']]);
});

test('admin can get rating scales', function () {
    seedRatingScales();
    $admin = createPerformanceAdmin();

    $response = $this->actingAs($admin)->getJson('/api/hr/performance/rating-scales');

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

test('employee can view own reviews', function () {
    $user = User::factory()->create(['role' => 'employee']);
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);
    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);
    $cycle = ReviewCycle::factory()->create();
    PerformanceReview::factory()->create([
        'review_cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/hr/me/reviews');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
