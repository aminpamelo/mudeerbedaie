<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificateIssue>
 */
class CertificateIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certificate_id' => \App\Models\Certificate::factory(),
            'student_id' => \App\Models\Student::factory(),
            'enrollment_id' => null,
            'class_id' => null,
            'certificate_number' => 'CERT-'.date('Y').'-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now()->toDateString(),
            'issued_by' => \App\Models\User::factory(),
            'status' => 'issued',
            'file_path' => 'certificates/issued/'.fake()->uuid().'.pdf',
            'data_snapshot' => [
                'completion_date' => now()->toDateString(),
                'grade' => fake()->randomElement(['A', 'B', 'C']),
                'student_name' => fake()->name(),
            ],
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
