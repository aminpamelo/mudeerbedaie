<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VaultCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VaultCredential>
 */
class VaultCredentialFactory extends Factory
{
    protected $model = VaultCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' '.fake()->randomElement(['Login', 'API', 'Dashboard', 'Admin']),
            'url' => fake()->url(),
            'username' => fake()->userName(),
            'password' => fake()->password(12, 20),
            'notes' => fake()->optional()->sentence(),
            'category_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
