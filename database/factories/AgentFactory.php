<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['agent', 'company']);
        $isCompany = $type === 'company';

        return [
            'agent_code' => Agent::generateAgentCode(),
            'name' => $isCompany
                ? fake()->company().' Sdn Bhd'
                : 'Kedai Buku '.fake()->lastName(),
            'type' => $type,
            'company_name' => $isCompany ? fake()->company().' Sdn Bhd' : null,
            'registration_number' => $isCompany
                ? fake()->numerify('20##01######').' ('.fake()->numerify('######').'-'.fake()->randomLetter().')'
                : fake()->optional()->regexify('[A-Z]{2}[0-9]{7}-[A-Z]'),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->randomElement(['012', '013', '014', '016', '017', '018', '019']).'-'.fake()->numerify('#######'),
            'address' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->randomElement(['Kuala Lumpur', 'Petaling Jaya', 'Shah Alam', 'Subang Jaya', 'Johor Bahru', 'Georgetown', 'Kota Bharu', 'Kuching', 'Kota Kinabalu']),
                'state' => fake()->randomElement(['Selangor', 'Wilayah Persekutuan', 'Johor', 'Pulau Pinang', 'Kelantan', 'Sarawak', 'Sabah', 'Perak', 'Kedah']),
                'postal_code' => fake()->numerify('#####'),
                'country' => 'Malaysia',
            ],
            'payment_terms' => fake()->randomElement(['COD', 'Net 7 days', 'Net 14 days', 'Net 30 days']),
            'bank_details' => [
                'bank_name' => fake()->randomElement(['Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank', 'Bank Islam', 'AmBank']),
                'account_number' => fake()->numerify('##########'),
                'account_name' => fake()->name(),
            ],
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the agent is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the agent is a company type.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'company',
            'company_name' => fake()->company().' Sdn Bhd',
            'registration_number' => fake()->numerify('20##01######').' ('.fake()->numerify('######').'-'.fake()->randomLetter().')',
        ]);
    }

    /**
     * Indicate that the agent is an individual agent type.
     */
    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'agent',
            'company_name' => null,
        ]);
    }
}
