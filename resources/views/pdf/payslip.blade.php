<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $monthName }} {{ $payslip->year }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.5; }
        .container { max-width: 700px; margin: 0 auto; padding: 30px; }
        .header { border-bottom: 2px solid #18181b; padding-bottom: 16px; margin-bottom: 20px; }
        .company-name { font-size: 18px; font-weight: bold; color: #18181b; }
        .payslip-title { font-size: 14px; color: #71717a; margin-top: 2px; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-row { display: table-row; }
        .info-left, .info-right { display: table-cell; width: 50%; vertical-align: top; }
        .info-label { color: #71717a; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 12px; font-weight: 600; color: #18181b; margin-bottom: 8px; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #71717a; border-bottom: 1px solid #e4e4e7; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #71717a; padding: 6px 0; border-bottom: 1px solid #e4e4e7; }
        th.amount { text-align: right; }
        td { padding: 5px 0; font-size: 12px; }
        td.amount { text-align: right; font-weight: 500; }
        td.deduction { text-align: right; font-weight: 500; color: #dc2626; }
        .total-row td { font-weight: 700; border-top: 1px solid #e4e4e7; padding-top: 8px; }
        .net-pay-box { background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 12px 16px; margin-top: 20px; }
        .net-pay-label { font-size: 12px; color: #065f46; }
        .net-pay-amount { font-size: 20px; font-weight: 700; color: #065f46; }
        .employer-section { background-color: #f4f4f5; border-radius: 6px; padding: 12px 16px; margin-top: 16px; }
        .employer-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #71717a; margin-bottom: 8px; }
        .employer-row { display: table; width: 100%; }
        .employer-label, .employer-amount { display: table-cell; font-size: 11px; padding: 3px 0; }
        .employer-label { color: #52525b; }
        .employer-amount { text-align: right; color: #52525b; font-weight: 500; }
        .footer { margin-top: 30px; border-top: 1px solid #e4e4e7; padding-top: 12px; font-size: 10px; color: #a1a1aa; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-name">{{ $companyName }}</div>
            <div class="payslip-title">Payslip for {{ $monthName }} {{ $payslip->year }}</div>
        </div>

        <div class="info-grid">
            <div class="info-row">
                <div class="info-left">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value">{{ $employee->full_name }}</div>
                    <div class="info-label">Employee ID</div>
                    <div class="info-value">{{ $employee->employee_id ?? '-' }}</div>
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $employee->department?->name ?? '-' }}</div>
                </div>
                <div class="info-right">
                    <div class="info-label">Position</div>
                    <div class="info-value">{{ $employee->position?->name ?? '-' }}</div>
                    <div class="info-label">Pay Period</div>
                    <div class="info-value">{{ $monthName }} {{ $payslip->year }}</div>
                    <div class="info-label">Payment Date</div>
                    <div class="info-value">{{ $payslip->created_at?->format('d M Y') ?? '-' }}</div>
                </div>
            </div>
        </div>

        {{-- Earnings --}}
        <div class="section">
            <div class="section-title">Earnings</div>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($earnings as $item)
                        <tr>
                            <td>{{ $item->component_name ?? $item->salary_component?->name ?? 'Earning' }}</td>
                            <td class="amount">{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>Basic Salary</td>
                            <td class="amount">{{ number_format($payslip->gross_salary, 2) }}</td>
                        </tr>
                    @endforelse
                    <tr class="total-row">
                        <td>Gross Salary</td>
                        <td class="amount">{{ number_format($payslip->gross_salary, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Statutory Deductions --}}
        <div class="section">
            <div class="section-title">Statutory Deductions</div>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>EPF (Employee 11%)</td>
                        <td class="deduction">{{ number_format($payslip->epf_employee, 2) }}</td>
                    </tr>
                    <tr>
                        <td>SOCSO (Employee)</td>
                        <td class="deduction">{{ number_format($payslip->socso_employee, 2) }}</td>
                    </tr>
                    <tr>
                        <td>EIS (Employee)</td>
                        <td class="deduction">{{ number_format($payslip->eis_employee, 2) }}</td>
                    </tr>
                    <tr>
                        <td>PCB (Monthly Tax Deduction)</td>
                        <td class="deduction">{{ number_format($payslip->pcb_amount, 2) }}</td>
                    </tr>
                    @foreach($deductions as $item)
                        <tr>
                            <td>{{ $item->component_name ?? $item->salary_component?->name ?? 'Deduction' }}</td>
                            <td class="deduction">{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    @if($payslip->unpaid_leave_deduction > 0)
                        <tr>
                            <td>Unpaid Leave ({{ $payslip->unpaid_leave_days }} day{{ $payslip->unpaid_leave_days > 1 ? 's' : '' }})</td>
                            <td class="deduction">{{ number_format($payslip->unpaid_leave_deduction, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="deduction">{{ number_format($payslip->total_deductions, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Net Pay --}}
        <div class="net-pay-box">
            <table>
                <tr>
                    <td class="net-pay-label">Net Pay</td>
                    <td class="net-pay-amount" style="text-align: right;">RM {{ number_format($payslip->net_salary, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Employer Contributions --}}
        <div class="employer-section">
            <div class="employer-title">Employer Contributions (Not deducted from salary)</div>
            <div class="employer-row">
                <span class="employer-label">EPF (Employer 13%)</span>
                <span class="employer-amount">RM {{ number_format($payslip->epf_employer, 2) }}</span>
            </div>
            <div class="employer-row">
                <span class="employer-label">SOCSO (Employer)</span>
                <span class="employer-amount">RM {{ number_format($payslip->socso_employer, 2) }}</span>
            </div>
            <div class="employer-row">
                <span class="employer-label">EIS (Employer)</span>
                <span class="employer-amount">RM {{ number_format($payslip->eis_employer, 2) }}</span>
            </div>
        </div>

        <div class="footer">
            This is a computer-generated payslip. No signature is required.
        </div>
    </div>
</body>
</html>
