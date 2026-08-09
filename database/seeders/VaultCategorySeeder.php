<?php

namespace Database\Seeders;

use App\Models\VaultCategory;
use Illuminate\Database\Seeder;

class VaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Social Media', 'slug' => 'social-media', 'icon' => 'globe', 'color' => 'blue', 'sort_order' => 1],
            ['name' => 'E-commerce', 'slug' => 'ecommerce', 'icon' => 'shopping-bag', 'color' => 'green', 'sort_order' => 2],
            ['name' => 'Hosting & Domain', 'slug' => 'hosting-domain', 'icon' => 'server', 'color' => 'purple', 'sort_order' => 3],
            ['name' => 'Email Accounts', 'slug' => 'email-accounts', 'icon' => 'mail', 'color' => 'red', 'sort_order' => 4],
            ['name' => 'API Keys', 'slug' => 'api-keys', 'icon' => 'key', 'color' => 'amber', 'sort_order' => 5],
            ['name' => 'Banking & Payment', 'slug' => 'banking-payment', 'icon' => 'credit-card', 'color' => 'emerald', 'sort_order' => 6],
            ['name' => 'Others', 'slug' => 'others', 'icon' => 'folder', 'color' => 'slate', 'sort_order' => 99],
        ];

        foreach ($categories as $cat) {
            VaultCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
