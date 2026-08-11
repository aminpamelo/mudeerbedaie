<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Form>
 */
class FormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'uuid' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'form_category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => $this->faker->optional()->paragraph(),
            'status' => Form::STATUS_DRAFT,
            'fields' => [
                [
                    'id' => 'fld_'.Str::lower(Str::random(8)),
                    'type' => 'short_text',
                    'label' => 'Nama penuh',
                    'help' => null,
                    'required' => true,
                    'options' => [],
                    'settings' => [],
                ],
                [
                    'id' => 'fld_'.Str::lower(Str::random(8)),
                    'type' => 'email',
                    'label' => 'Emel',
                    'help' => null,
                    'required' => true,
                    'options' => [],
                    'settings' => [],
                ],
            ],
            'settings' => [
                'confirmation_message' => 'Terima kasih atas maklum balas anda.',
                'allow_multiple' => true,
            ],
            'submissions_count' => 0,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => Form::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => Form::STATUS_CLOSED,
        ]);
    }
}
