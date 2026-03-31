<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentType = fake()->randomElement(['ic', 'offer_letter', 'contract', 'bank_statement', 'epf_form', 'socso_form']);

        return [
            'employee_id' => Employee::factory(),
            'document_type' => $documentType,
            'file_name' => $documentType.'_'.fake()->uuid().'.pdf',
            'file_path' => 'employee-documents/'.$documentType.'_'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(50000, 5000000),
            'mime_type' => 'application/pdf',
            'uploaded_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'expiry_date' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
