<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeHistory;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create 19 departments with positions
        $departmentsData = [
            [
                'name' => 'Logistik',
                'code' => 'LOG',
                'description' => 'Pengurusan logistik dan penghantaran',
                'positions' => [
                    ['title' => 'Eksekutif Logistik', 'level' => 2],
                    ['title' => 'Koordinator Penghantaran', 'level' => 1],
                    ['title' => 'Ketua Logistik', 'level' => 3],
                ],
            ],
            [
                'name' => 'Admin Kelas',
                'code' => 'ADKLS',
                'description' => 'Pentadbiran kelas dan pengurusan pelajar',
                'positions' => [
                    ['title' => 'Admin Kelas', 'level' => 1],
                    ['title' => 'Senior Admin Kelas', 'level' => 2],
                    ['title' => 'Ketua Admin Kelas', 'level' => 3],
                ],
            ],
            [
                'name' => 'Editor Bahasa',
                'code' => 'EDBHS',
                'description' => 'Penyuntingan bahasa dan proofreading',
                'positions' => [
                    ['title' => 'Editor Bahasa', 'level' => 1],
                    ['title' => 'Senior Editor Bahasa', 'level' => 2],
                    ['title' => 'Ketua Editor', 'level' => 3],
                ],
            ],
            [
                'name' => 'Designer',
                'code' => 'DESGN',
                'description' => 'Reka bentuk grafik dan kreatif',
                'positions' => [
                    ['title' => 'Graphic Designer', 'level' => 1],
                    ['title' => 'Senior Designer', 'level' => 2],
                    ['title' => 'Ketua Designer', 'level' => 3],
                ],
            ],
            [
                'name' => 'Sistem',
                'code' => 'SIS',
                'description' => 'Pembangunan dan penyelenggaraan sistem IT',
                'positions' => [
                    ['title' => 'Developer', 'level' => 1],
                    ['title' => 'Senior Developer', 'level' => 2],
                    ['title' => 'System Administrator', 'level' => 2],
                    ['title' => 'Ketua Sistem', 'level' => 3],
                ],
            ],
            [
                'name' => 'Customer Service',
                'code' => 'CS',
                'description' => 'Perkhidmatan pelanggan dan sokongan',
                'positions' => [
                    ['title' => 'Customer Service Executive', 'level' => 1],
                    ['title' => 'Senior CS Executive', 'level' => 2],
                    ['title' => 'Ketua Customer Service', 'level' => 3],
                ],
            ],
            [
                'name' => 'Agent & Kedai Buku',
                'code' => 'AGENT',
                'description' => 'Pengurusan ejen dan kedai buku',
                'positions' => [
                    ['title' => 'Eksekutif Agent', 'level' => 1],
                    ['title' => 'Koordinator Kedai Buku', 'level' => 1],
                    ['title' => 'Ketua Agent & Kedai Buku', 'level' => 3],
                ],
            ],
            [
                'name' => 'Admin Hostlive',
                'code' => 'ADHL',
                'description' => 'Pentadbiran sesi live dan hosting',
                'positions' => [
                    ['title' => 'Admin Hostlive', 'level' => 1],
                    ['title' => 'Senior Admin Hostlive', 'level' => 2],
                    ['title' => 'Ketua Admin Hostlive', 'level' => 3],
                ],
            ],
            [
                'name' => 'Admin Tiktok',
                'code' => 'ADTK',
                'description' => 'Pengurusan akaun dan kedai TikTok',
                'positions' => [
                    ['title' => 'Admin TikTok', 'level' => 1],
                    ['title' => 'Senior Admin TikTok', 'level' => 2],
                    ['title' => 'Ketua Admin TikTok', 'level' => 3],
                ],
            ],
            [
                'name' => 'Team Sales',
                'code' => 'SALES',
                'description' => 'Pasukan jualan dan pemasaran langsung',
                'positions' => [
                    ['title' => 'Sales Executive', 'level' => 1],
                    ['title' => 'Senior Sales Executive', 'level' => 2],
                    ['title' => 'Sales Manager', 'level' => 3],
                ],
            ],
            [
                'name' => 'Marketer',
                'code' => 'MKT',
                'description' => 'Pemasaran digital dan strategi pemasaran',
                'positions' => [
                    ['title' => 'Digital Marketer', 'level' => 1],
                    ['title' => 'Senior Marketer', 'level' => 2],
                    ['title' => 'Ketua Marketing', 'level' => 3],
                ],
            ],
            [
                'name' => 'Affiliate',
                'code' => 'AFF',
                'description' => 'Pengurusan program affiliate',
                'positions' => [
                    ['title' => 'Eksekutif Affiliate', 'level' => 1],
                    ['title' => 'Ketua Affiliate', 'level' => 3],
                ],
            ],
            [
                'name' => 'Accountant',
                'code' => 'ACC',
                'description' => 'Perakaunan dan kewangan syarikat',
                'positions' => [
                    ['title' => 'Akauntan', 'level' => 1],
                    ['title' => 'Senior Akauntan', 'level' => 2],
                    ['title' => 'Ketua Kewangan', 'level' => 3],
                ],
            ],
            [
                'name' => 'Human Resource',
                'code' => 'HR',
                'description' => 'Pengurusan sumber manusia',
                'positions' => [
                    ['title' => 'HR Executive', 'level' => 1],
                    ['title' => 'Senior HR Executive', 'level' => 2],
                    ['title' => 'HR Manager', 'level' => 3],
                ],
            ],
            [
                'name' => 'Top Management',
                'code' => 'MGMT',
                'description' => 'Pengurusan tertinggi syarikat',
                'positions' => [
                    ['title' => 'CEO', 'level' => 5],
                    ['title' => 'COO', 'level' => 4],
                    ['title' => 'Director', 'level' => 4],
                    ['title' => 'General Manager', 'level' => 3],
                ],
            ],
            [
                'name' => 'Editor Video',
                'code' => 'EDVID',
                'description' => 'Penyuntingan video dan multimedia',
                'positions' => [
                    ['title' => 'Video Editor', 'level' => 1],
                    ['title' => 'Senior Video Editor', 'level' => 2],
                    ['title' => 'Ketua Editor Video', 'level' => 3],
                ],
            ],
            [
                'name' => 'OA (Office Admin)',
                'code' => 'OA',
                'description' => 'Pentadbiran pejabat am',
                'positions' => [
                    ['title' => 'Office Admin', 'level' => 1],
                    ['title' => 'Senior Office Admin', 'level' => 2],
                    ['title' => 'Ketua Pentadbiran', 'level' => 3],
                ],
            ],
            [
                'name' => 'Data Management',
                'code' => 'DATA',
                'description' => 'Pengurusan data dan analitik',
                'positions' => [
                    ['title' => 'Data Entry', 'level' => 1],
                    ['title' => 'Data Analyst', 'level' => 2],
                    ['title' => 'Ketua Data Management', 'level' => 3],
                ],
            ],
            [
                'name' => 'Admin Indonesia',
                'code' => 'ADIND',
                'description' => 'Pentadbiran operasi Indonesia',
                'positions' => [
                    ['title' => 'Admin Indonesia', 'level' => 1],
                    ['title' => 'Senior Admin Indonesia', 'level' => 2],
                    ['title' => 'Ketua Admin Indonesia', 'level' => 3],
                ],
            ],
        ];

        $allDepartments = collect();

        foreach ($departmentsData as $deptData) {
            $positions = $deptData['positions'];
            unset($deptData['positions']);

            $department = Department::create($deptData);
            $allDepartments->push($department);

            foreach ($positions as $posData) {
                Position::create([
                    'title' => $posData['title'],
                    'department_id' => $department->id,
                    'level' => $posData['level'],
                ]);
            }
        }

        // 2. Create sample employees (2-3 per department)
        $adminUser = User::where('email', 'admin@example.com')->first();
        $employeeCounter = 0;

        foreach ($allDepartments as $department) {
            $deptPositions = $department->positions;
            $employeesPerDept = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $employeesPerDept; $i++) {
                $employeeCounter++;
                $position = $deptPositions->random();
                $gender = fake()->randomElement(['male', 'female']);
                $dob = fake()->dateTimeBetween('-50 years', '-20 years');
                $icPrefix = $dob->format('ymd');
                $icState = str_pad(fake()->numberBetween(1, 16), 2, '0', STR_PAD_LEFT);
                $icSuffix = str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

                $user = User::factory()->create([
                    'role' => 'employee',
                    'status' => 'active',
                ]);

                $employee = Employee::create([
                    'user_id' => $user->id,
                    'employee_id' => 'BDE-'.str_pad($employeeCounter, 4, '0', STR_PAD_LEFT),
                    'full_name' => $user->name,
                    'ic_number' => $icPrefix.$icState.$icSuffix,
                    'date_of_birth' => $dob,
                    'gender' => $gender,
                    'religion' => fake()->randomElement(['islam', 'christian', 'buddhist', 'hindu', 'sikh', 'other']),
                    'race' => fake()->randomElement(['malay', 'chinese', 'indian', 'other']),
                    'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
                    'phone' => '+60 1'.fake()->numerify('#-### ####'),
                    'personal_email' => fake()->safeEmail(),
                    'address_line_1' => fake()->streetAddress(),
                    'address_line_2' => fake()->optional()->secondaryAddress(),
                    'city' => fake()->randomElement(['Shah Alam', 'Petaling Jaya', 'Kuala Lumpur', 'Subang Jaya', 'Johor Bahru', 'Bangi', 'Putrajaya', 'Cyberjaya']),
                    'state' => fake()->randomElement(['Selangor', 'W.P. Kuala Lumpur', 'Johor', 'W.P. Putrajaya', 'Perak']),
                    'postcode' => fake()->numerify('#####'),
                    'department_id' => $department->id,
                    'position_id' => $position->id,
                    'employment_type' => fake()->randomElement(['full_time', 'full_time', 'full_time', 'part_time', 'contract']),
                    'join_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
                    'probation_end_date' => fake()->optional(0.3)?->dateTimeBetween('+1 week', '+3 months'),
                    'status' => fake()->randomElement(['active', 'active', 'active', 'probation']),
                    'bank_name' => fake()->randomElement(['Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank', 'Bank Islam', 'Bank Rakyat']),
                    'bank_account_number' => fake()->numerify('################'),
                    'epf_number' => fake()->optional()->numerify('########'),
                    'socso_number' => fake()->optional()->numerify('########'),
                    'tax_number' => fake()->optional()->regexify('SG\d{10}'),
                ]);

                // Emergency contacts
                $contactCount = fake()->numberBetween(1, 2);
                for ($c = 0; $c < $contactCount; $c++) {
                    EmployeeEmergencyContact::create([
                        'employee_id' => $employee->id,
                        'name' => fake()->name(),
                        'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling', 'child', 'friend']),
                        'phone' => '+60 1'.fake()->numerify('#-### ####'),
                        'address' => fake()->optional()->address(),
                    ]);
                }

                // History entries for some employees
                if (fake()->boolean(60)) {
                    $changedBy = $adminUser?->id ?? $user->id;
                    EmployeeHistory::create([
                        'employee_id' => $employee->id,
                        'change_type' => 'status_change',
                        'field_name' => 'status',
                        'old_value' => 'probation',
                        'new_value' => 'active',
                        'effective_date' => fake()->dateTimeBetween($employee->join_date, 'now'),
                        'remarks' => 'Confirmed after probation period',
                        'changed_by' => $changedBy,
                    ]);
                }
            }
        }

        $this->call(HrPhase2Seeder::class);
    }
}
