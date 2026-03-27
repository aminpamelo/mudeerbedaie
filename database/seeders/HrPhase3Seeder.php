<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\BenefitType;
use App\Models\ClaimType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeTaxProfile;
use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HrPhase3Seeder extends Seeder
{
    /**
     * Run the database seeds. Idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        $this->command?->info('📦 Seeding HR Phase 3 data (Payroll, Claims, Benefits, Assets)...');

        $this->seedSalaryComponents();
        $this->seedPayrollSettings();
        $this->seedClaimTypes();
        $this->seedBenefitTypes();
        $this->seedAssetCategories();
        $this->seedEmployeeSalariesAndTaxProfiles();
        $this->seedSampleAssets();

        $this->command?->info('✅ HR Phase 3 seeding completed!');
    }

    /**
     * Seed default salary components (EPF, SOCSO, EIS, PCB, Basic, Allowances).
     */
    private function seedSalaryComponents(): void
    {
        $this->command?->info('  → Seeding salary components...');

        $components = [
            [
                'name' => 'Basic Salary',
                'code' => 'BASIC',
                'type' => 'earning',
                'category' => 'basic',
                'is_taxable' => true,
                'is_epf_applicable' => true,
                'is_socso_applicable' => true,
                'is_eis_applicable' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Housing Allowance',
                'code' => 'HOUSING',
                'type' => 'earning',
                'category' => 'fixed_allowance',
                'is_taxable' => true,
                'is_epf_applicable' => true,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Transport Allowance',
                'code' => 'TRANSPORT',
                'type' => 'earning',
                'category' => 'fixed_allowance',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Meal Allowance',
                'code' => 'MEAL',
                'type' => 'earning',
                'category' => 'fixed_allowance',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Performance Bonus',
                'code' => 'BONUS',
                'type' => 'earning',
                'category' => 'variable_allowance',
                'is_taxable' => true,
                'is_epf_applicable' => true,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'EPF (Employee)',
                'code' => 'EPF_EE',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'EPF (Employer)',
                'code' => 'EPF_ER',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'SOCSO (Employee)',
                'code' => 'SOCSO_EE',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'SOCSO (Employer)',
                'code' => 'SOCSO_ER',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'EIS (Employee)',
                'code' => 'EIS_EE',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'EIS (Employer)',
                'code' => 'EIS_ER',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 15,
            ],
            [
                'name' => 'PCB / MTD',
                'code' => 'PCB',
                'type' => 'deduction',
                'category' => 'fixed_deduction',
                'is_taxable' => false,
                'is_epf_applicable' => false,
                'is_socso_applicable' => false,
                'is_eis_applicable' => false,
                'is_system' => true,
                'sort_order' => 16,
            ],
        ];

        foreach ($components as $component) {
            SalaryComponent::firstOrCreate(
                ['code' => $component['code']],
                array_merge($component, ['is_active' => true])
            );
        }

        $this->command?->info('     ✓ Salary components seeded');
    }

    /**
     * Seed default payroll settings.
     */
    private function seedPayrollSettings(): void
    {
        $this->command?->info('  → Seeding payroll settings...');

        $settings = [
            ['key' => 'unpaid_leave_divisor', 'value' => '26', 'description' => 'Days divisor for unpaid leave daily rate (26 or 30)'],
            ['key' => 'pay_day', 'value' => '25', 'description' => 'Salary payment day of month'],
            ['key' => 'epf_employee_default_rate', 'value' => '11', 'description' => 'Default EPF employee percentage'],
            ['key' => 'company_name', 'value' => 'Mudeer Bedaie Sdn Bhd', 'description' => 'Company name for payslip header'],
            ['key' => 'company_address', 'value' => 'Kuala Lumpur, Malaysia', 'description' => 'Company address for payslip header'],
            ['key' => 'company_epf_number', 'value' => '', 'description' => 'Company EPF registration number'],
            ['key' => 'company_socso_number', 'value' => '', 'description' => 'Company SOCSO registration number'],
            ['key' => 'company_eis_number', 'value' => '', 'description' => 'Company EIS registration number'],
        ];

        foreach ($settings as $setting) {
            PayrollSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command?->info('     ✓ Payroll settings seeded');
    }

    /**
     * Seed default claim types.
     */
    private function seedClaimTypes(): void
    {
        $this->command?->info('  → Seeding claim types...');

        $claimTypes = [
            [
                'name' => 'Medical',
                'code' => 'MED',
                'description' => 'Medical and health-related expenses including outpatient visits and prescriptions.',
                'monthly_limit' => 500.00,
                'yearly_limit' => 3000.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Transport',
                'code' => 'TRANS',
                'description' => 'Work-related transport expenses including mileage claims and Grab/taxi rides.',
                'monthly_limit' => 300.00,
                'yearly_limit' => 2000.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Parking',
                'code' => 'PARK',
                'description' => 'Parking fees for work-related activities or client visits.',
                'monthly_limit' => 150.00,
                'yearly_limit' => 1000.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Meals & Entertainment',
                'code' => 'MEAL',
                'description' => 'Business meals and client entertainment expenses.',
                'monthly_limit' => 200.00,
                'yearly_limit' => 1500.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Training & Development',
                'code' => 'TRAIN',
                'description' => 'External training courses, seminars, certifications, and books.',
                'monthly_limit' => null,
                'yearly_limit' => 5000.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Office Supplies',
                'code' => 'OFFICE',
                'description' => 'Work-related office supplies and stationery.',
                'monthly_limit' => 100.00,
                'yearly_limit' => 500.00,
                'requires_receipt' => true,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Telephone & Internet',
                'code' => 'TEL',
                'description' => 'Work-related telephone and internet expenses.',
                'monthly_limit' => 150.00,
                'yearly_limit' => 1200.00,
                'requires_receipt' => false,
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($claimTypes as $claimType) {
            ClaimType::firstOrCreate(
                ['code' => $claimType['code']],
                $claimType
            );
        }

        $this->command?->info('     ✓ Claim types seeded');
    }

    /**
     * Seed default benefit types.
     */
    private function seedBenefitTypes(): void
    {
        $this->command?->info('  → Seeding benefit types...');

        $benefitTypes = [
            [
                'name' => 'Health Insurance',
                'code' => 'HEALTH_INS',
                'description' => 'Company-sponsored group health insurance covering outpatient and inpatient.',
                'category' => 'insurance',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Dental Insurance',
                'code' => 'DENTAL',
                'description' => 'Annual dental benefits for employee and dependents.',
                'category' => 'insurance',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Life Insurance',
                'code' => 'LIFE_INS',
                'description' => 'Group term life insurance coverage.',
                'category' => 'insurance',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Annual Leave Encashment',
                'code' => 'LEAVE_ENCASH',
                'description' => 'Option to encash unused annual leave days.',
                'category' => 'allowance',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Gym Membership',
                'code' => 'GYM',
                'description' => 'Monthly gym membership subsidy.',
                'category' => 'subsidy',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Employee Assistance Program',
                'code' => 'EAP',
                'description' => 'Mental health and counselling services.',
                'category' => 'other',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Company Vehicle',
                'code' => 'VEHICLE',
                'description' => 'Company car or vehicle allowance for eligible positions.',
                'category' => 'allowance',
                'is_active' => false,
                'sort_order' => 7,
            ],
        ];

        foreach ($benefitTypes as $benefitType) {
            BenefitType::firstOrCreate(
                ['code' => $benefitType['code']],
                $benefitType
            );
        }

        $this->command?->info('     ✓ Benefit types seeded');
    }

    /**
     * Seed default asset categories.
     */
    private function seedAssetCategories(): void
    {
        $this->command?->info('  → Seeding asset categories...');

        $assetCategories = [
            [
                'name' => 'Laptop',
                'code' => 'LAPTOP',
                'description' => 'Laptop computers and notebooks.',
                'requires_serial_number' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Desktop Computer',
                'code' => 'DESKTOP',
                'description' => 'Desktop computers and workstations.',
                'requires_serial_number' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Monitor',
                'code' => 'MONITOR',
                'description' => 'Computer monitors and displays.',
                'requires_serial_number' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mobile Phone',
                'code' => 'PHONE',
                'description' => 'Company mobile phones and smartphones.',
                'requires_serial_number' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Office Chair',
                'code' => 'CHAIR',
                'description' => 'Ergonomic and standard office chairs.',
                'requires_serial_number' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Office Desk',
                'code' => 'DESK',
                'description' => 'Standing desks and standard office desks.',
                'requires_serial_number' => false,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Access Card',
                'code' => 'ACCESS_CARD',
                'description' => 'Building and office access cards.',
                'requires_serial_number' => false,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Headset',
                'code' => 'HEADSET',
                'description' => 'Headsets and headphones for office use.',
                'requires_serial_number' => false,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($assetCategories as $assetCategory) {
            AssetCategory::firstOrCreate(
                ['code' => $assetCategory['code']],
                $assetCategory
            );
        }

        $this->command?->info('     ✓ Asset categories seeded');
    }

    /**
     * Seed employee salaries and tax profiles for existing employees.
     */
    private function seedEmployeeSalariesAndTaxProfiles(): void
    {
        $this->command?->info('  → Seeding employee salaries and tax profiles...');

        $basicComponent = SalaryComponent::where('code', 'BASIC')->first();
        $transportComponent = SalaryComponent::where('code', 'TRANSPORT')->first();

        if (! $basicComponent) {
            $this->command?->warn('     ⚠ Basic salary component not found, skipping employee salaries.');

            return;
        }

        $employees = Employee::where('status', 'active')->orWhere('status', 'probation')->take(10)->get();

        if ($employees->isEmpty()) {
            $this->command?->warn('     ⚠ No employees found, skipping employee salaries.');

            return;
        }

        /** @var array<string, int[]> $salariesByLevel */
        $salariesByLevel = [
            1 => [2500, 3500],
            2 => [3500, 5500],
            3 => [5500, 8000],
            4 => [8000, 12000],
            5 => [12000, 20000],
        ];

        $transportAmount = 200.00;
        $effectiveFrom = Carbon::now()->subYear()->startOfMonth()->toDateString();

        foreach ($employees as $employee) {
            $positionLevel = $employee->position?->level ?? 1;
            $range = $salariesByLevel[$positionLevel] ?? $salariesByLevel[1];
            $basicAmount = fake()->numberBetween($range[0], $range[1]);

            // Seed basic salary (idempotent)
            $existingBasic = EmployeeSalary::where('employee_id', $employee->id)
                ->where('salary_component_id', $basicComponent->id)
                ->whereNull('effective_to')
                ->first();

            if (! $existingBasic) {
                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'salary_component_id' => $basicComponent->id,
                    'amount' => $basicAmount,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                ]);
            }

            // Seed transport allowance for some employees
            if ($transportComponent && fake()->boolean(70)) {
                $existingTransport = EmployeeSalary::where('employee_id', $employee->id)
                    ->where('salary_component_id', $transportComponent->id)
                    ->whereNull('effective_to')
                    ->first();

                if (! $existingTransport) {
                    EmployeeSalary::create([
                        'employee_id' => $employee->id,
                        'salary_component_id' => $transportComponent->id,
                        'amount' => $transportAmount,
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                    ]);
                }
            }

            // Seed tax profile (idempotent)
            $maritalStatuses = ['single', 'married_spouse_not_working', 'married_spouse_working'];
            $maritalStatus = fake()->randomElement($maritalStatuses);

            EmployeeTaxProfile::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'marital_status' => $maritalStatus,
                    'num_children' => $maritalStatus === 'single' ? 0 : fake()->numberBetween(0, 3),
                    'num_children_studying' => 0,
                    'disabled_individual' => false,
                    'disabled_spouse' => false,
                    'is_pcb_manual' => false,
                    'manual_pcb_amount' => null,
                ]
            );
        }

        $this->command?->info("     ✓ Employee salaries and tax profiles seeded for {$employees->count()} employees");
    }

    /**
     * Seed sample assets (unassigned) for each category.
     */
    private function seedSampleAssets(): void
    {
        $this->command?->info('  → Seeding sample assets...');

        /** @var array<string, array{brand: string, name: string, price: int}[]> $assetsByCategory */
        $assetsByCategory = [
            'LAPTOP' => [
                ['brand' => 'Apple', 'name' => 'MacBook Pro 14"', 'price' => 7999],
                ['brand' => 'Lenovo', 'name' => 'ThinkPad X1 Carbon', 'price' => 5499],
                ['brand' => 'Dell', 'name' => 'XPS 15', 'price' => 6299],
            ],
            'MONITOR' => [
                ['brand' => 'LG', 'name' => '27" 4K Monitor', 'price' => 1599],
                ['brand' => 'Dell', 'name' => '24" FHD Monitor', 'price' => 899],
            ],
            'PHONE' => [
                ['brand' => 'Apple', 'name' => 'iPhone 15', 'price' => 4299],
                ['brand' => 'Samsung', 'name' => 'Galaxy S24', 'price' => 3799],
            ],
            'CHAIR' => [
                ['brand' => 'Herman Miller', 'name' => 'Aeron Office Chair', 'price' => 3500],
                ['brand' => 'Haworth', 'name' => 'Fern Chair', 'price' => 2800],
            ],
            'ACCESS_CARD' => [
                ['brand' => 'Genetec', 'name' => 'Office Access Card', 'price' => 50],
                ['brand' => 'Genetec', 'name' => 'Office Access Card', 'price' => 50],
                ['brand' => 'Genetec', 'name' => 'Office Access Card', 'price' => 50],
            ],
        ];

        $purchaseDate = Carbon::now()->subMonths(6)->toDateString();

        foreach ($assetsByCategory as $categoryCode => $assets) {
            $category = AssetCategory::where('code', $categoryCode)->first();

            if (! $category) {
                continue;
            }

            foreach ($assets as $assetData) {
                // Check if an identical asset already exists (by name and category)
                $existingCount = Asset::where('asset_category_id', $category->id)
                    ->where('name', $assetData['name'])
                    ->count();

                if ($existingCount >= count($assets)) {
                    continue;
                }

                Asset::create([
                    'asset_tag' => Asset::generateAssetTag(),
                    'asset_category_id' => $category->id,
                    'name' => $assetData['name'],
                    'brand' => $assetData['brand'],
                    'purchase_date' => $purchaseDate,
                    'purchase_price' => $assetData['price'],
                    'condition' => 'good',
                    'status' => 'available',
                    'notes' => null,
                ]);
            }
        }

        $this->command?->info('     ✓ Sample assets seeded');
    }
}
