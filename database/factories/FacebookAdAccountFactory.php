<?php

namespace Database\Factories;

use App\Models\FacebookAdConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FacebookAdAccount>
 */
class FacebookAdAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facebook_ad_connection_id' => FacebookAdConnection::factory(),
            'account_id' => (string) fake()->unique()->numerify('##########'),
            'name' => fake()->company().' Ads',
            'currency' => 'MYR',
            'account_status' => '1',
        ];
    }
}
