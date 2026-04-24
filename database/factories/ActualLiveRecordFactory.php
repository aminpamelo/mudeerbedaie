<?php

namespace Database\Factories;

use App\Models\ActualLiveRecord;
use App\Models\PlatformAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActualLiveRecordFactory extends Factory
{
    protected $model = ActualLiveRecord::class;

    public function definition(): array
    {
        return [
            'platform_account_id' => PlatformAccount::factory(),
            'source' => 'csv_import',
            'source_record_id' => null,
            'creator_platform_user_id' => (string) fake()->numerify('##############'),
            'creator_handle' => fake()->userName(),
            'launched_time' => now()->subHours(fake()->numberBetween(1, 48)),
            'duration_seconds' => fake()->numberBetween(600, 7200),
            'gmv_myr' => fake()->randomFloat(2, 100, 5000),
            'live_attributed_gmv_myr' => fake()->randomFloat(2, 50, 4000),
            'viewers' => fake()->numberBetween(10, 5000),
            'raw_json' => [],
        ];
    }

    public function apiSync(): self
    {
        return $this->state([
            'source' => 'api_sync',
            'source_record_id' => (string) fake()->numerify('################'),
        ]);
    }
}
