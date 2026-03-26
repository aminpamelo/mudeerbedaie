<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    private static int $employeeCounter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$employeeCounter++;

        $gender = fake()->randomElement(['male', 'female']);
        $joinDate = fake()->dateTimeBetween('-5 years', 'now');

        // Generate Malaysian IC format: YYMMDD-SS-NNNN
        $dob = fake()->dateTimeBetween('-50 years', '-20 years');
        $icPrefix = $dob->format('ymd');
        $icState = str_pad(fake()->numberBetween(1, 16), 2, '0', STR_PAD_LEFT);
        $icSuffix = str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        $malaysianStates = [
            'Selangor', 'Kuala Lumpur', 'Johor', 'Penang', 'Perak',
            'Sabah', 'Sarawak', 'Kedah', 'Kelantan', 'Melaka',
            'Negeri Sembilan', 'Pahang', 'Perlis', 'Terengganu', 'Putrajaya',
        ];

        $malaysianCities = [
            'Shah Alam', 'Petaling Jaya', 'Subang Jaya', 'Kuala Lumpur',
            'Johor Bahru', 'George Town', 'Ipoh', 'Kota Kinabalu',
            'Kuching', 'Melaka', 'Seremban', 'Kuantan',
        ];

        return [
            'user_id' => User::factory(),
            'employee_id' => 'BDE-'.str_pad(self::$employeeCounter, 4, '0', STR_PAD_LEFT),
            'full_name' => fake()->name($gender),
            'ic_number' => $icPrefix.$icState.$icSuffix,
            'date_of_birth' => $dob,
            'gender' => $gender,
            'religion' => fake()->randomElement(['islam', 'christian', 'buddhist', 'hindu', 'sikh', 'other']),
            'race' => fake()->randomElement(['malay', 'chinese', 'indian', 'other']),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'phone' => fake()->phoneNumber(),
            'personal_email' => fake()->safeEmail(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->randomElement($malaysianCities),
            'state' => fake()->randomElement($malaysianStates),
            'postcode' => fake()->numerify('#####'),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract', 'intern']),
            'join_date' => $joinDate,
            'status' => fake()->randomElement(['active', 'probation']),
            'bank_name' => fake()->randomElement(['Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank']),
            'bank_account_number' => fake()->numerify('################'),
            'epf_number' => fake()->optional()->numerify('########'),
            'socso_number' => fake()->optional()->numerify('########'),
            'tax_number' => fake()->optional()->regexify('SG\d{10}'),
        ];
    }

    /**
     * Set status as active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Set status as probation
     */
    public function probation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'probation',
            'probation_end_date' => now()->addMonths(3),
        ]);
    }

    /**
     * Set status as resigned
     */
    public function resigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resigned',
            'resignation_date' => now()->subMonth(),
            'last_working_date' => now(),
        ]);
    }
}
