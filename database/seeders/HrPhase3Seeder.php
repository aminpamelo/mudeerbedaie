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
use App\Models\StatutoryRate;
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
        $this->seedStatutoryRates();
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
     * Seed Malaysian statutory contribution rates (EPF, SOCSO, EIS).
     * Based on 2024/2025 KWSP, PERKESO schedules.
     */
    private function seedStatutoryRates(): void
    {
        $this->command?->info('  → Seeding statutory rates (EPF, SOCSO, EIS)...');

        $effectiveFrom = '2024-01-01';

        // ── EPF Employee Rates ──
        // Standard rate: 11% for wages up to RM20,000
        $epfEmployeeRates = [
            ['min' => 0, 'max' => 5000, 'rate' => 11.00],
            ['min' => 5000.01, 'max' => 20000, 'rate' => 11.00],
            ['min' => 20000.01, 'max' => null, 'rate' => 11.00],
        ];

        foreach ($epfEmployeeRates as $bracket) {
            StatutoryRate::updateOrCreate(
                ['type' => 'epf_employee', 'min_salary' => $bracket['min'], 'effective_from' => $effectiveFrom],
                [
                    'max_salary' => $bracket['max'],
                    'rate_percentage' => $bracket['rate'],
                    'fixed_amount' => null,
                    'effective_to' => null,
                ]
            );
        }

        // ── EPF Employer Rates ──
        // <= RM5,000: 13%, > RM5,000: 12%
        $epfEmployerRates = [
            ['min' => 0, 'max' => 5000, 'rate' => 13.00],
            ['min' => 5000.01, 'max' => 20000, 'rate' => 12.00],
            ['min' => 20000.01, 'max' => null, 'rate' => 12.00],
        ];

        foreach ($epfEmployerRates as $bracket) {
            StatutoryRate::updateOrCreate(
                ['type' => 'epf_employer', 'min_salary' => $bracket['min'], 'effective_from' => $effectiveFrom],
                [
                    'max_salary' => $bracket['max'],
                    'rate_percentage' => $bracket['rate'],
                    'fixed_amount' => null,
                    'effective_to' => null,
                ]
            );
        }

        // ── SOCSO Employee Contribution (Employment Injury + Invalidity) ──
        // Fixed amounts based on wage brackets (2024 schedule)
        $socsoEmployeeBrackets = [
            ['min' => 0, 'max' => 30, 'amount' => 0.10],
            ['min' => 30.01, 'max' => 50, 'amount' => 0.20],
            ['min' => 50.01, 'max' => 70, 'amount' => 0.30],
            ['min' => 70.01, 'max' => 100, 'amount' => 0.40],
            ['min' => 100.01, 'max' => 140, 'amount' => 0.60],
            ['min' => 140.01, 'max' => 200, 'amount' => 0.85],
            ['min' => 200.01, 'max' => 300, 'amount' => 1.25],
            ['min' => 300.01, 'max' => 400, 'amount' => 1.75],
            ['min' => 400.01, 'max' => 500, 'amount' => 2.25],
            ['min' => 500.01, 'max' => 600, 'amount' => 2.75],
            ['min' => 600.01, 'max' => 700, 'amount' => 3.25],
            ['min' => 700.01, 'max' => 800, 'amount' => 3.75],
            ['min' => 800.01, 'max' => 900, 'amount' => 4.25],
            ['min' => 900.01, 'max' => 1000, 'amount' => 4.75],
            ['min' => 1000.01, 'max' => 1100, 'amount' => 5.25],
            ['min' => 1100.01, 'max' => 1200, 'amount' => 5.75],
            ['min' => 1200.01, 'max' => 1300, 'amount' => 6.25],
            ['min' => 1300.01, 'max' => 1400, 'amount' => 6.75],
            ['min' => 1400.01, 'max' => 1500, 'amount' => 7.25],
            ['min' => 1500.01, 'max' => 1600, 'amount' => 7.75],
            ['min' => 1600.01, 'max' => 1700, 'amount' => 8.25],
            ['min' => 1700.01, 'max' => 1800, 'amount' => 8.75],
            ['min' => 1800.01, 'max' => 1900, 'amount' => 9.25],
            ['min' => 1900.01, 'max' => 2000, 'amount' => 9.75],
            ['min' => 2000.01, 'max' => 2100, 'amount' => 10.25],
            ['min' => 2100.01, 'max' => 2200, 'amount' => 10.75],
            ['min' => 2200.01, 'max' => 2300, 'amount' => 11.25],
            ['min' => 2300.01, 'max' => 2400, 'amount' => 11.75],
            ['min' => 2400.01, 'max' => 2500, 'amount' => 12.25],
            ['min' => 2500.01, 'max' => 2600, 'amount' => 12.75],
            ['min' => 2600.01, 'max' => 2700, 'amount' => 13.25],
            ['min' => 2700.01, 'max' => 2800, 'amount' => 13.75],
            ['min' => 2800.01, 'max' => 2900, 'amount' => 14.25],
            ['min' => 2900.01, 'max' => 3000, 'amount' => 14.75],
            ['min' => 3000.01, 'max' => 3100, 'amount' => 15.25],
            ['min' => 3100.01, 'max' => 3200, 'amount' => 15.75],
            ['min' => 3200.01, 'max' => 3300, 'amount' => 16.25],
            ['min' => 3300.01, 'max' => 3400, 'amount' => 16.75],
            ['min' => 3400.01, 'max' => 3500, 'amount' => 17.25],
            ['min' => 3500.01, 'max' => 3600, 'amount' => 17.75],
            ['min' => 3600.01, 'max' => 3700, 'amount' => 18.25],
            ['min' => 3700.01, 'max' => 3800, 'amount' => 18.75],
            ['min' => 3800.01, 'max' => 3900, 'amount' => 19.25],
            ['min' => 3900.01, 'max' => 4000, 'amount' => 19.75],
            ['min' => 4000.01, 'max' => null, 'amount' => 19.75],
        ];

        foreach ($socsoEmployeeBrackets as $bracket) {
            StatutoryRate::updateOrCreate(
                ['type' => 'socso_employee', 'min_salary' => $bracket['min'], 'effective_from' => $effectiveFrom],
                [
                    'max_salary' => $bracket['max'],
                    'rate_percentage' => null,
                    'fixed_amount' => $bracket['amount'],
                    'effective_to' => null,
                ]
            );
        }

        // ── SOCSO Employer Contribution ──
        // Employer pays roughly 1.75x the employee amount
        $socsoEmployerBrackets = [
            ['min' => 0, 'max' => 30, 'amount' => 0.40],
            ['min' => 30.01, 'max' => 50, 'amount' => 0.70],
            ['min' => 50.01, 'max' => 70, 'amount' => 1.10],
            ['min' => 70.01, 'max' => 100, 'amount' => 1.50],
            ['min' => 100.01, 'max' => 140, 'amount' => 2.10],
            ['min' => 140.01, 'max' => 200, 'amount' => 2.95],
            ['min' => 200.01, 'max' => 300, 'amount' => 4.35],
            ['min' => 300.01, 'max' => 400, 'amount' => 6.15],
            ['min' => 400.01, 'max' => 500, 'amount' => 7.85],
            ['min' => 500.01, 'max' => 600, 'amount' => 9.65],
            ['min' => 600.01, 'max' => 700, 'amount' => 11.35],
            ['min' => 700.01, 'max' => 800, 'amount' => 13.15],
            ['min' => 800.01, 'max' => 900, 'amount' => 14.85],
            ['min' => 900.01, 'max' => 1000, 'amount' => 16.65],
            ['min' => 1000.01, 'max' => 1100, 'amount' => 18.35],
            ['min' => 1100.01, 'max' => 1200, 'amount' => 20.15],
            ['min' => 1200.01, 'max' => 1300, 'amount' => 21.85],
            ['min' => 1300.01, 'max' => 1400, 'amount' => 23.65],
            ['min' => 1400.01, 'max' => 1500, 'amount' => 25.35],
            ['min' => 1500.01, 'max' => 1600, 'amount' => 27.15],
            ['min' => 1600.01, 'max' => 1700, 'amount' => 28.85],
            ['min' => 1700.01, 'max' => 1800, 'amount' => 30.65],
            ['min' => 1800.01, 'max' => 1900, 'amount' => 32.35],
            ['min' => 1900.01, 'max' => 2000, 'amount' => 34.15],
            ['min' => 2000.01, 'max' => 2100, 'amount' => 35.85],
            ['min' => 2100.01, 'max' => 2200, 'amount' => 37.65],
            ['min' => 2200.01, 'max' => 2300, 'amount' => 39.35],
            ['min' => 2300.01, 'max' => 2400, 'amount' => 41.15],
            ['min' => 2400.01, 'max' => 2500, 'amount' => 42.85],
            ['min' => 2500.01, 'max' => 2600, 'amount' => 44.65],
            ['min' => 2600.01, 'max' => 2700, 'amount' => 46.35],
            ['min' => 2700.01, 'max' => 2800, 'amount' => 48.15],
            ['min' => 2800.01, 'max' => 2900, 'amount' => 49.85],
            ['min' => 2900.01, 'max' => 3000, 'amount' => 51.65],
            ['min' => 3000.01, 'max' => 3100, 'amount' => 53.35],
            ['min' => 3100.01, 'max' => 3200, 'amount' => 55.15],
            ['min' => 3200.01, 'max' => 3300, 'amount' => 56.85],
            ['min' => 3300.01, 'max' => 3400, 'amount' => 58.65],
            ['min' => 3400.01, 'max' => 3500, 'amount' => 60.35],
            ['min' => 3500.01, 'max' => 3600, 'amount' => 62.15],
            ['min' => 3600.01, 'max' => 3700, 'amount' => 63.85],
            ['min' => 3700.01, 'max' => 3800, 'amount' => 65.65],
            ['min' => 3800.01, 'max' => 3900, 'amount' => 67.35],
            ['min' => 3900.01, 'max' => 4000, 'amount' => 69.15],
            ['min' => 4000.01, 'max' => null, 'amount' => 69.15],
        ];

        foreach ($socsoEmployerBrackets as $bracket) {
            StatutoryRate::updateOrCreate(
                ['type' => 'socso_employer', 'min_salary' => $bracket['min'], 'effective_from' => $effectiveFrom],
                [
                    'max_salary' => $bracket['max'],
                    'rate_percentage' => null,
                    'fixed_amount' => $bracket['amount'],
                    'effective_to' => null,
                ]
            );
        }

        // ── EIS Employee & Employer Rates ──
        // 0.2% each, max insurable salary RM5,000
        $eisBrackets = [
            ['min' => 0, 'max' => 5000, 'rate' => 0.20],
            ['min' => 5000.01, 'max' => null, 'rate' => 0.20],
        ];

        foreach (['eis_employee', 'eis_employer'] as $type) {
            foreach ($eisBrackets as $bracket) {
                StatutoryRate::updateOrCreate(
                    ['type' => $type, 'min_salary' => $bracket['min'], 'effective_from' => $effectiveFrom],
                    [
                        'max_salary' => $bracket['max'],
                        'rate_percentage' => $bracket['rate'],
                        'fixed_amount' => null,
                        'effective_to' => null,
                    ]
                );
            }
        }

        $this->command?->info('    ✓ Seeded '.StatutoryRate::count().' statutory rate brackets');
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
