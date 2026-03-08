<?php

namespace Database\Factories;

use App\Models\FunnelEmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FunnelEmailTemplateFactory extends Factory
{
    protected $model = FunnelEmailTemplate::class;

    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'subject' => 'Thank you for your order #{{order.number}}',
            'content' => "Hi {{contact.first_name}},\n\nThank you for your purchase!\n\nOrder: {{order.number}}\nTotal: {{order.total}}",
            'editor_type' => 'text',
            'category' => fake()->randomElement(['purchase', 'cart', 'welcome', 'followup', 'upsell', 'general']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function visual(): static
    {
        return $this->state(fn (array $attributes) => [
            'editor_type' => 'visual',
            'design_json' => ['blocks' => []],
            'html_content' => '<html><body><h1>Hello {{contact.first_name}}</h1></body></html>',
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }
}
