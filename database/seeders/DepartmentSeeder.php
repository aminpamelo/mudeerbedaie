<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Remove old departments that are no longer needed
        Department::whereIn('slug', ['editor', 'content-creator', 'admin'])->delete();

        // Top-level departments
        $topLevel = [
            [
                'name' => 'Affiliate',
                'slug' => 'affiliate',
                'description' => 'Affiliate marketing department',
                'color' => '#3b82f6', // blue
                'icon' => 'link',
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'name' => 'Designer',
                'slug' => 'designer',
                'description' => 'Graphic design department',
                'color' => '#f59e0b', // amber
                'icon' => 'paint-brush',
                'status' => 'active',
                'sort_order' => 2,
            ],
        ];

        foreach ($topLevel as $department) {
            Department::updateOrCreate(
                ['slug' => $department['slug']],
                array_merge($department, ['parent_id' => null])
            );
        }

        // Affiliate sub-departments
        $affiliate = Department::where('slug', 'affiliate')->first();

        $affiliateChildren = [
            [
                'name' => 'Recruit Affiliate',
                'slug' => 'recruit-affiliate',
                'description' => 'Affiliate recruitment and onboarding',
                'color' => '#06b6d4', // cyan
                'icon' => 'user-plus',
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'name' => 'KPI Content Creator',
                'slug' => 'kpi-content-creator',
                'description' => 'Content creator KPI tracking and management',
                'color' => '#8b5cf6', // violet
                'icon' => 'chart-bar',
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'name' => 'Content Staff',
                'slug' => 'content-staff',
                'description' => 'Content staff operations and tasks',
                'color' => '#ec4899', // pink
                'icon' => 'document-text',
                'status' => 'active',
                'sort_order' => 3,
            ],
        ];

        foreach ($affiliateChildren as $child) {
            Department::updateOrCreate(
                ['slug' => $child['slug']],
                array_merge($child, ['parent_id' => $affiliate->id])
            );
        }

        $this->command->info('Created departments: Affiliate (with 3 sub-departments), Designer');
    }
}
