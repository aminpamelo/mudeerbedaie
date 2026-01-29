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
        $departments = [
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
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Video and content editing department',
                'color' => '#8b5cf6', // violet
                'icon' => 'pencil-square',
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'name' => 'Content Creator',
                'slug' => 'content-creator',
                'description' => 'Content creation department',
                'color' => '#ec4899', // pink
                'icon' => 'camera',
                'status' => 'active',
                'sort_order' => 3,
            ],
            [
                'name' => 'Designer',
                'slug' => 'designer',
                'description' => 'Graphic design department',
                'color' => '#f59e0b', // amber
                'icon' => 'paint-brush',
                'status' => 'active',
                'sort_order' => 4,
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['slug' => $department['slug']],
                $department
            );
        }

        $this->command->info('Created 4 departments: Affiliate, Editor, Content Creator, Designer');
    }
}
