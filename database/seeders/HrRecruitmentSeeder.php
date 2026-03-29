<?php

namespace Database\Seeders;

use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Database\Seeder;

class HrRecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        $template = OnboardingTemplate::create([
            'name' => 'Default Onboarding Checklist',
            'department_id' => null,
            'is_active' => true,
        ]);

        $items = [
            ['title' => 'Setup workstation/laptop', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 1],
            ['title' => 'Create email account', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 2],
            ['title' => 'System access setup', 'assigned_role' => 'IT', 'due_days' => 1, 'sort_order' => 3],
            ['title' => 'Issue access card', 'assigned_role' => 'Admin', 'due_days' => 1, 'sort_order' => 4],
            ['title' => 'HR orientation briefing', 'assigned_role' => 'HR', 'due_days' => 1, 'sort_order' => 5],
            ['title' => 'Sign employment contract', 'assigned_role' => 'HR', 'due_days' => 1, 'sort_order' => 6],
            ['title' => 'Submit personal documents (IC, bank details, EPF)', 'assigned_role' => 'HR', 'due_days' => 3, 'sort_order' => 7],
            ['title' => 'Department introduction & team meet', 'assigned_role' => 'Manager', 'due_days' => 1, 'sort_order' => 8],
            ['title' => 'Role briefing & KPIs review', 'assigned_role' => 'Manager', 'due_days' => 3, 'sort_order' => 9],
            ['title' => 'Safety & compliance training', 'assigned_role' => 'HR', 'due_days' => 7, 'sort_order' => 10],
            ['title' => 'Probation review schedule confirmed', 'assigned_role' => 'HR', 'due_days' => 7, 'sort_order' => 11],
        ];

        foreach ($items as $item) {
            OnboardingTemplateItem::create(array_merge($item, [
                'onboarding_template_id' => $template->id,
            ]));
        }
    }
}
