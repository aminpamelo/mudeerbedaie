<?php

namespace Database\Seeders;

use App\Models\PayrollSetting;
use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class PayrollSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default salary components
        $components = [
            ['name' => 'Basic Salary', 'code' => 'BASIC', 'type' => 'earning', 'category' => 'basic', 'is_taxable' => true, 'is_epf_applicable' => true, 'is_socso_applicable' => true, 'is_eis_applicable' => true, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'EPF (Employee)', 'code' => 'EPF_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 10],
            ['name' => 'EPF (Employer)', 'code' => 'EPF_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 11],
            ['name' => 'SOCSO (Employee)', 'code' => 'SOCSO_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 12],
            ['name' => 'SOCSO (Employer)', 'code' => 'SOCSO_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 13],
            ['name' => 'EIS (Employee)', 'code' => 'EIS_EE', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 14],
            ['name' => 'EIS (Employer)', 'code' => 'EIS_ER', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 15],
            ['name' => 'PCB / MTD', 'code' => 'PCB', 'type' => 'deduction', 'category' => 'fixed_deduction', 'is_taxable' => false, 'is_epf_applicable' => false, 'is_socso_applicable' => false, 'is_eis_applicable' => false, 'is_system' => true, 'sort_order' => 16],
        ];

        foreach ($components as $component) {
            SalaryComponent::firstOrCreate(
                ['code' => $component['code']],
                array_merge($component, ['is_active' => true])
            );
        }

        // Default payroll settings
        $settings = [
            ['key' => 'unpaid_leave_divisor', 'value' => '26', 'description' => 'Days divisor for unpaid leave daily rate (26 or 30)'],
            ['key' => 'pay_day', 'value' => '25', 'description' => 'Salary payment day of month'],
            ['key' => 'epf_employee_default_rate', 'value' => '11', 'description' => 'Default EPF employee percentage'],
            ['key' => 'company_name', 'value' => 'Mudeer Bedaie Sdn Bhd', 'description' => 'Company name for payslip header'],
            ['key' => 'company_address', 'value' => '', 'description' => 'Company address for payslip header'],
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
    }
}
