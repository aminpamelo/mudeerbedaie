<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use Database\Seeders\AffiliateTaskTemplateSeeder;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['role' => 'admin', 'email' => 'admin@test.com']);
    $this->seed(DepartmentSeeder::class);
});

test('affiliate task template seeder creates 16 templates across sub-departments', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $contentStaff = Department::where('slug', 'content-staff')->first();
    $recruitAffiliate = Department::where('slug', 'recruit-affiliate')->first();
    $kpiContentCreator = Department::where('slug', 'kpi-content-creator')->first();

    expect($contentStaff)->not->toBeNull();
    expect($recruitAffiliate)->not->toBeNull();
    expect($kpiContentCreator)->not->toBeNull();

    $total = TaskTemplate::where('department_id', $contentStaff->id)->count()
        + TaskTemplate::where('department_id', $recruitAffiliate->id)->count()
        + TaskTemplate::where('department_id', $kpiContentCreator->id)->count();

    expect($total)->toBe(16);
});

test('seeder creates 6 content staff reporting templates in content-staff sub-department', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $department = Department::where('slug', 'content-staff')->first();

    $contentTemplates = TaskTemplate::where('department_id', $department->id)
        ->where('name', 'like', 'Laporan Harian TikTok%')
        ->get();

    expect($contentTemplates)->toHaveCount(6);

    $accounts = ['addaie.fin', 'theaaaz.bedaie', 'zearose.daie', 'daienation', 'amazah.daie', 'addaie.atiqa'];
    foreach ($accounts as $account) {
        expect($contentTemplates->contains('name', "Laporan Harian TikTok - {$account}"))->toBeTrue();
    }
});

test('seeder creates 7 recruit affiliate templates in recruit-affiliate sub-department', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $department = Department::where('slug', 'recruit-affiliate')->first();

    $recruitTemplates = TaskTemplate::where('department_id', $department->id)
        ->where('name', 'like', 'Recruit Affiliate%')
        ->get();

    expect($recruitTemplates)->toHaveCount(7);
});

test('seeder creates 3 KPI content creator templates in kpi-content-creator sub-department', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $department = Department::where('slug', 'kpi-content-creator')->first();

    $kpiTemplates = TaskTemplate::where('department_id', $department->id)
        ->whereIn('name', [
            'KPI Bulanan Content Creator',
            'Rakam Video Content Creator',
            'Semakan KPI Content Creator',
        ])
        ->get();

    expect($kpiTemplates)->toHaveCount(3);
});

test('templates have metadata_schema in template_data', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $subDeptSlugs = ['content-staff', 'recruit-affiliate', 'kpi-content-creator'];
    $subDeptIds = Department::whereIn('slug', $subDeptSlugs)->pluck('id');

    $templates = TaskTemplate::whereIn('department_id', $subDeptIds)->get();

    expect($templates)->toHaveCount(16);

    foreach ($templates as $template) {
        expect($template->template_data)->toBeArray();
        expect($template->template_data)->toHaveKey('title');
        expect($template->template_data)->toHaveKey('description');
        expect($template->template_data)->toHaveKey('workflow');
        expect($template->template_data)->toHaveKey('metadata_schema');
        expect($template->template_data['metadata_schema'])->toBeArray();
    }
});

test('seeder is idempotent and can be re-run safely', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $subDeptSlugs = ['content-staff', 'recruit-affiliate', 'kpi-content-creator'];
    $subDeptIds = Department::whereIn('slug', $subDeptSlugs)->pluck('id');

    expect(TaskTemplate::whereIn('department_id', $subDeptIds)->count())->toBe(16);
});

test('seeder cleans up old parent-level templates', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $affiliate = Department::where('slug', 'affiliate')->first();

    // No templates should be on the parent affiliate department
    expect(TaskTemplate::where('department_id', $affiliate->id)->count())->toBe(0);
});

test('template metadata schema can be applied to task metadata', function () {
    $this->seed(AffiliateTaskTemplateSeeder::class);

    $department = Department::where('slug', 'content-staff')->first();
    $user = User::factory()->create();

    $template = TaskTemplate::where('department_id', $department->id)
        ->where('name', 'like', 'Laporan Harian TikTok%')
        ->first();

    // Simulate what task-create does: copy metadata_schema to task
    $metadata = $template->template_data['metadata_schema'];

    $task = Task::create([
        'department_id' => $department->id,
        'title' => $template->template_data['title'],
        'description' => $template->template_data['description'],
        'task_type' => $template->task_type->value,
        'status' => 'todo',
        'priority' => $template->priority->value,
        'created_by' => $user->id,
        'metadata' => $metadata,
    ]);

    $task->refresh();

    expect($task->metadata)->toBeArray();
    expect($task->metadata)->toHaveKey('tiktok_account');
    expect($task->metadata['tiktok_account'])->not->toBeEmpty();
    expect($task->metadata)->toHaveKey('book_name');
    expect($task->metadata)->toHaveKey('tiktok_link');
    expect($task->metadata)->toHaveKey('video_id');
});

test('parent PIC can access child sub-departments', function () {
    $affiliate = Department::where('slug', 'affiliate')->first();
    $user = User::factory()->create(['role' => 'pic_department']);

    // Make user PIC of parent Affiliate department
    $affiliate->addPic($user);

    // Verify user is PIC of parent
    expect($user->isPicOfDepartment($affiliate))->toBeTrue();

    // Verify user is also PIC of child sub-departments
    $recruitAffiliate = Department::where('slug', 'recruit-affiliate')->first();
    $kpiContentCreator = Department::where('slug', 'kpi-content-creator')->first();
    $contentStaff = Department::where('slug', 'content-staff')->first();

    expect($user->isPicOfDepartment($recruitAffiliate))->toBeTrue();
    expect($user->isPicOfDepartment($kpiContentCreator))->toBeTrue();
    expect($user->isPicOfDepartment($contentStaff))->toBeTrue();

    // Verify creatable departments includes parent + children
    $creatableDepts = $user->getCreatableDepartments();
    expect($creatableDepts->pluck('slug')->toArray())->toContain('affiliate');
    expect($creatableDepts->pluck('slug')->toArray())->toContain('recruit-affiliate');
    expect($creatableDepts->pluck('slug')->toArray())->toContain('kpi-content-creator');
    expect($creatableDepts->pluck('slug')->toArray())->toContain('content-staff');
});

test('parent PIC accessible department IDs include children', function () {
    $affiliate = Department::where('slug', 'affiliate')->first();
    $user = User::factory()->create(['role' => 'pic_department']);

    // Make user PIC of parent Affiliate department
    $affiliate->addPic($user);

    $accessibleIds = $user->getAccessibleDepartmentIds();

    // Should include affiliate + 3 sub-departments
    $recruitAffiliate = Department::where('slug', 'recruit-affiliate')->first();
    $kpiContentCreator = Department::where('slug', 'kpi-content-creator')->first();
    $contentStaff = Department::where('slug', 'content-staff')->first();

    expect($accessibleIds)->toContain($affiliate->id);
    expect($accessibleIds)->toContain($recruitAffiliate->id);
    expect($accessibleIds)->toContain($kpiContentCreator->id);
    expect($accessibleIds)->toContain($contentStaff->id);
});

test('task with metadata stores and retrieves correctly', function () {
    $department = Department::where('slug', 'content-staff')->first();
    $user = User::factory()->create();

    $metadata = [
        'tiktok_account' => 'addaie.fin',
        'book_name' => 'Test Book',
        'tiktok_link' => 'https://vt.tiktok.com/test',
        'video_id' => '12345',
        'post_date' => '2026-01-30',
        'editor_review' => '',
        'editor_comments' => '',
    ];

    $task = Task::create([
        'department_id' => $department->id,
        'title' => 'Test Task with Metadata',
        'task_type' => 'kpi',
        'status' => 'todo',
        'priority' => 'medium',
        'created_by' => $user->id,
        'metadata' => $metadata,
    ]);

    $task->refresh();

    expect($task->metadata)->toBe($metadata);
    expect($task->metadata['tiktok_account'])->toBe('addaie.fin');
    expect($task->metadata['book_name'])->toBe('Test Book');
});
