<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CmsPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['key' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'sort_order' => 10],
            ['key' => 'facebook',  'name' => 'Facebook',  'icon' => 'facebook',  'sort_order' => 20],
            ['key' => 'youtube',   'name' => 'YouTube',   'icon' => 'youtube',   'sort_order' => 30],
            ['key' => 'threads',   'name' => 'Threads',   'icon' => 'at-sign',   'sort_order' => 40],
            ['key' => 'x',         'name' => 'X',         'icon' => 'twitter',   'sort_order' => 50],
        ];

        foreach ($platforms as $platform) {
            DB::table('cms_platforms')->updateOrInsert(
                ['key' => $platform['key']],
                array_merge($platform, [
                    'is_enabled' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
