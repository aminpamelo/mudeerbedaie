<?php

namespace App\Services\Hr;

use App\Models\EmployeeTaxProfile;
use App\Models\PcbRate;
use App\Models\StatutoryRate;

class StatutoryCalculationService
{
    /**
     * Calculate EPF employee contribution.
     * Default: 11% of wages, rounded to nearest RM.
     */
    public function calculateEpfEmployee(float $wages, int $employeeAge = 30): float
    {
        if ($employeeAge >= 60) {
            return 0.00;
        }

        // Check statutory_rates table for custom rate
        $rate = StatutoryRate::forType('epf_employee')
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        $percentage = $rate ? $rate->rate_percentage : 11.00;
        $maxWage = 20000.00; // EPF max contribution wage

        $applicableWage = min($wages, $maxWage);
        $amount = $applicableWage * ($percentage / 100);

        return $this->roundToNearestRinggit($amount);
    }

    /**
     * Calculate EPF employer contribution.
     * <= RM5,000: 13%, > RM5,000: 12%, Age 60+: 4%.
     */
    public function calculateEpfEmployer(float $wages, int $employeeAge = 30): float
    {
        $maxWage = 20000.00;
        $applicableWage = min($wages, $maxWage);

        if ($employeeAge >= 60) {
            $percentage = 4.00;
        } elseif ($applicableWage <= 5000) {
            $percentage = 13.00;
        } else {
            $percentage = 12.00;
        }

        // Check statutory_rates table for custom rate
        $rate = StatutoryRate::forType('epf_employer')
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        if ($rate) {
            $percentage = $rate->rate_percentage;
        }

        $amount = $applicableWage * ($percentage / 100);

        return $this->roundToNearestRinggit($amount);
    }

    /**
     * Calculate SOCSO employee contribution (fixed amount from bracket table).
     */
    public function calculateSocsoEmployee(float $wages, int $employeeAge = 30): float
    {
        $type = $employeeAge >= 60 ? 'socso_employee' : 'socso_employee';

        $rate = StatutoryRate::forType($type)
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        return $rate ? (float) $rate->fixed_amount : 0.00;
    }

    /**
     * Calculate SOCSO employer contribution (fixed amount from bracket table).
     */
    public function calculateSocsoEmployer(float $wages, int $employeeAge = 30): float
    {
        $type = $employeeAge >= 60 ? 'socso_employer' : 'socso_employer';

        $rate = StatutoryRate::forType($type)
            ->current()
            ->where('min_salary', '<=', $wages)
            ->where(function ($q) use ($wages) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $wages);
            })
            ->first();

        return $rate ? (float) $rate->fixed_amount : 0.00;
    }

    /**
     * Calculate EIS employee contribution.
     * 0.2% of salary, max salary RM6,000, max contribution RM12.
     */
    public function calculateEisEmployee(float $wages): float
    {
        $maxWage = 6000.00;
        $applicableWage = min($wages, $maxWage);
        $amount = $applicableWage * 0.002;

        return round(min($amount, 12.00), 2);
    }

    /**
     * Calculate EIS employer contribution (same as employee).
     */
    public function calculateEisEmployer(float $wages): float
    {
        return $this->calculateEisEmployee($wages);
    }

    /**
     * Calculate PCB (Monthly Tax Deduction).
     */
    public function calculatePcb(
        float $grossRemuneration,
        float $epfEmployee,
        EmployeeTaxProfile $taxProfile,
        int $year
    ): float {
        // Manual override
        if ($taxProfile->is_pcb_manual && $taxProfile->manual_pcb_amount !== null) {
            return (float) $taxProfile->manual_pcb_amount;
        }

        // Taxable income = gross - EPF
        $taxableIncome = $grossRemuneration - $epfEmployee;

        if ($taxableIncome <= 0) {
            return 0.00;
        }

        // Map marital status to PCB category
        $category = $taxProfile->marital_status;

        // Lookup PCB table
        $pcbRate = PcbRate::forYear($year)
            ->forCategory($category)
            ->where('num_children', $taxProfile->num_children)
            ->where('min_monthly_income', '<=', $taxableIncome)
            ->where(function ($q) use ($taxableIncome) {
                $q->whereNull('max_monthly_income')
                    ->orWhere('max_monthly_income', '>=', $taxableIncome);
            })
            ->first();

        $pcbAmount = $pcbRate ? (float) $pcbRate->pcb_amount : 0.00;

        // Additional relief deductions
        if ($taxProfile->disabled_individual) {
            $pcbAmount -= 100.00;
        }

        if ($taxProfile->disabled_spouse) {
            $pcbAmount -= 29.17;
        }

        if ($taxProfile->num_children_studying > 0) {
            $pcbAmount -= ($taxProfile->num_children_studying * 66.67);
        }

        return max(0, round($pcbAmount, 2));
    }

    /**
     * Calculate all statutory deductions for an employee.
     *
     * @return array{epf_ee: float, epf_er: float, socso_ee: float, socso_er: float, eis_ee: float, eis_er: float, pcb: float}
     */
    public function calculateAll(
        float $epfApplicableWages,
        float $socsoApplicableWages,
        float $eisApplicableWages,
        float $grossRemuneration,
        EmployeeTaxProfile $taxProfile,
        int $employeeAge,
        int $year
    ): array {
        $epfEe = $this->calculateEpfEmployee($epfApplicableWages, $employeeAge);
        $epfEr = $this->calculateEpfEmployer($epfApplicableWages, $employeeAge);
        $socsoEe = $this->calculateSocsoEmployee($socsoApplicableWages, $employeeAge);
        $socsoEr = $this->calculateSocsoEmployer($socsoApplicableWages, $employeeAge);
        $eisEe = $this->calculateEisEmployee($eisApplicableWages);
        $eisEr = $this->calculateEisEmployer($eisApplicableWages);
        $pcb = $this->calculatePcb($grossRemuneration, $epfEe, $taxProfile, $year);

        return [
            'epf_ee' => $epfEe,
            'epf_er' => $epfEr,
            'socso_ee' => $socsoEe,
            'socso_er' => $socsoEr,
            'eis_ee' => $eisEe,
            'eis_er' => $eisEr,
            'pcb' => $pcb,
        ];
    }

    /**
     * Round to nearest ringgit (Malaysian EPF rounding rule).
     * 50 sen or more rounds up.
     */
    private function roundToNearestRinggit(float $amount): float
    {
        return round($amount, 0);
    }
}
