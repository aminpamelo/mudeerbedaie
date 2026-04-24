<?php

namespace Database\Factories;

use App\Models\LiveHostPlatformAccount;
use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LiveHostPlatformAccount>
 */
class LiveHostPlatformAccountFactory extends Factory
{
    protected $model = LiveHostPlatformAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform_account_id' => PlatformAccount::factory(),
            'creator_handle' => '@'.fake()->userName(),
            'creator_platform_user_id' => (string) fake()->numerify('##############'),
            'is_primary' => false,
        ];
    }
}
