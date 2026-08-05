<?php

namespace Database\Factories;

use App\Models\BlogSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogSubscriber>
 */
class BlogSubscriberFactory extends Factory
{
    protected $model = BlogSubscriber::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'locale' => 'en',
            'source' => 'blog',
            'confirmed_at' => now(),
            'token' => Str::random(48),
            'ip_address' => $this->faker->ipv4(),
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => ['unsubscribed_at' => now()]);
    }
}
