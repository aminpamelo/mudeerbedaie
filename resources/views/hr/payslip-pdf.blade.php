<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payslip - {{ $payslip->employee->employee_id ?? 'N/A' }} - {{ $payslip->year }}/{{ str_pad($payslip->month, 2, '0', STR_PAD_LEFT) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #333333;
            background: #ffffff;
            padding: 20px;
        }

        .header {
            border-bottom: 2px solid #1a56db;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a56db;
            margin-bottom: 2px;
        }

        .company-details {
            font-size: 10px;
            color: #666666;
            margin-bottom: 2px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            padding: 0;
        }

        .payslip-title {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #1a56db;
        }

        .payslip-period {
            text-align: right;
            font-size: 12px;
            font-weight: normal;
            color: #374151;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 14px;
        }

        .section-title {
            background-color: #1a56db;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 4px 8px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .employee-info-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-info-grid td {
            padding: 3px 6px;
            vertical-align: top;
            font-size: 10px;
        }

        .employee-info-grid .label {
            color: #666666;
            width: 120px;
            font-weight: bold;
        }

        .employee-info-grid .value {
            color: #333333;
        }

        .earnings-deductions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .earnings-deductions-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: bold;
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .earnings-deductions-table th.amount-col {
            text-align: right;
        }

        .earnings-deductions-table td {
            padding: 4px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }

        .earnings-deductions-table td.amount-col {
            text-align: right;
        }

        .earnings-deductions-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .earnings-deductions-table .subtotal-row td {
            background-color: #eff6ff;
            font-weight: bold;
            border-top: 2px solid #1a56db;
        }

        .two-col-layout {
            width: 100%;
            border-collapse: collapse;
        }

        .two-col-layout td {
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .two-col-layout td:last-child {
            padding-right: 0;
            padding-left: 10px;
        }

        .statutory-table {
            width: 100%;
            border-collapse: collapse;
        }

        .statutory-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            text-align: left;
        }

        .statutory-table th.amount-col {
            text-align: right;
        }

        .statutory-table td {
            padding: 3px 6px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }

        .statutory-table td.amount-col {
            text-align: right;
        }

        .statutory-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .net-pay-box {
            background-color: #1a56db;
            color: #ffffff;
            padding: 12px 16px;
            margin-top: 14px;
            text-align: right;
        }

        .net-pay-label {
            font-size: 12px;
            font-weight: normal;
            margin-bottom: 2px;
        }

        .net-pay-amount {
            font-size: 22px;
            font-weight: bold;
        }

        .net-pay-currency {
            font-size: 14px;
            margin-right: 4px;
        }

        .summary-row {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .summary-row td {
            padding: 4px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }

        .summary-row .summary-label {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
            width: 50%;
        }

        .summary-row .summary-value {
            text-align: right;
            width: 50%;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }

        .confidential-notice {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            padding: 6px 10px;
            font-size: 9px;
            color: #92400e;
            margin-bottom: 14px;
            text-align: center;
        }

        .page-number {
            text-align: right;
            font-size: 9px;
            color: #9ca3af;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

    {{-- Company Header --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="company-name">{{ $settings['company_name'] ?? config('app.name') }}</div>
                    @if(!empty($settings['company_address']))
                        <div class="company-details">{{ $settings['company_address'] }}</div>
                    @endif
                    @if(!empty($settings['company_epf_number']))
                        <div class="company-details">EPF No: {{ $settings['company_epf_number'] }}</div>
                    @endif
                    @if(!empty($settings['company_socso_number']))
                        <div class="company-details">SOCSO No: {{ $settings['company_socso_number'] }}</div>
                    @endif
                </td>
                <td style="width: 40%;">
                    <div class="payslip-title">PAYSLIP</div>
                    <div class="payslip-period">{{ date('F Y', mktime(0, 0, 0, $payslip->month, 1, $payslip->year)) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Confidential Notice --}}
    <div class="confidential-notice">
        CONFIDENTIAL — This payslip contains private information. Please keep it secure.
    </div>

    {{-- Employee Information --}}
    <div class="section">
        <div class="section-title">Employee Information</div>
        <table class="employee-info-grid">
            <tr>
                <td class="label">Employee ID</td>
                <td class="value">{{ $payslip->employee->employee_id ?? 'N/A' }}</td>
                <td class="label">Pay Period</td>
                <td class="value">{{ date('F Y', mktime(0, 0, 0, $payslip->month, 1, $payslip->year)) }}</td>
            </tr>
            <tr>
                <td class="label">Name</td>
                <td class="value">{{ $payslip->employee->full_name ?? 'N/A' }}</td>
                <td class="label">Department</td>
                <td class="value">{{ $payslip->employee->department->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">IC Number</td>
                <td class="value">{{ $payslip->employee->ic_number ?? 'N/A' }}</td>
                <td class="label">Position</td>
                <td class="value">{{ $payslip->employee->position->title ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Bank</td>
                <td class="value">{{ $payslip->employee->bank_name ?? 'N/A' }}</td>
                <td class="label">Bank Account</td>
                <td class="value">
                    @php
                        $bankAcc = $payslip->employee->bank_account_number ?? '';
                        echo $bankAcc ? '****' . substr($bankAcc, -4) : 'N/A';
                    @endphp
                </td>
            </tr>
            <tr>
                <td class="label">EPF Number</td>
                <td class="value">{{ $payslip->employee->epf_number ?? 'N/A' }}</td>
                <td class="label">SOCSO Number</td>
                <td class="value">{{ $payslip->employee->socso_number ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    {{-- Earnings & Deductions Side by Side --}}
    <table class="two-col-layout">
        <tr>
            {{-- Earnings Column --}}
            <td>
                <div class="section-title">Earnings</div>
                <table class="earnings-deductions-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $earnings = ($items ?? collect())->where('type', 'earning');
                            $totalEarnings = 0;
                        @endphp
                        @forelse($earnings as $item)
                            @php $totalEarnings += $item->amount; @endphp
                            <tr>
                                <td>{{ $item->component_name }}</td>
                                <td class="amount-col">{{ number_format($item->amount, 2) }}</td>
                            </tr>
                        @empty
                            @if((float) $payslip->gross_salary > 0)
                                <tr>
                                    <td>Basic Salary</td>
                                    <td class="amount-col">{{ number_format($payslip->gross_salary, 2) }}</td>
                                </tr>
                                @php $totalEarnings = $payslip->gross_salary; @endphp
                            @else
                                <tr>
                                    <td colspan="2" style="text-align: center; color: #9ca3af;">No earnings recorded</td>
                                </tr>
                            @endif
                        @endforelse

                        @if((float) ($payslip->unpaid_leave_deduction ?? 0) > 0)
                            <tr>
                                <td style="color: #dc2626;">Unpaid Leave ({{ $payslip->unpaid_leave_days ?? 0 }} days)</td>
                                <td class="amount-col" style="color: #dc2626;">-{{ number_format($payslip->unpaid_leave_deduction, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="subtotal-row">
                            <td>Gross Salary</td>
                            <td class="amount-col">{{ number_format($payslip->gross_salary, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Deductions Column --}}
            <td>
                <div class="section-title">Deductions</div>
                <table class="earnings-deductions-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $deductions = ($items ?? collect())->where('type', 'deduction')->where('is_statutory', false);
                        @endphp

                        @forelse($deductions as $item)
                            <tr>
                                <td>{{ $item->component_name }}</td>
                                <td class="amount-col">{{ number_format($item->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="text-align: center; color: #9ca3af;">No additional deductions</td>
                            </tr>
                        @endforelse

                        {{-- Statutory Deductions --}}
                        @if((float) $payslip->epf_employee > 0)
                            <tr>
                                <td>EPF (Employee 11%)</td>
                                <td class="amount-col">{{ number_format($payslip->epf_employee, 2) }}</td>
                            </tr>
                        @endif
                        @if((float) $payslip->socso_employee > 0)
                            <tr>
                                <td>SOCSO (Employee)</td>
                                <td class="amount-col">{{ number_format($payslip->socso_employee, 2) }}</td>
                            </tr>
                        @endif
                        @if((float) $payslip->eis_employee > 0)
                            <tr>
                                <td>EIS (Employee 0.2%)</td>
                                <td class="amount-col">{{ number_format($payslip->eis_employee, 2) }}</td>
                            </tr>
                        @endif
                        @if((float) $payslip->pcb_amount > 0)
                            <tr>
                                <td>PCB / MTD (Tax)</td>
                                <td class="amount-col">{{ number_format($payslip->pcb_amount, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="subtotal-row">
                            <td>Total Deductions</td>
                            <td class="amount-col">{{ number_format($payslip->total_deductions, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- Net Pay Box --}}
    <div class="net-pay-box">
        <div class="net-pay-label">NET PAY FOR {{ strtoupper(date('F Y', mktime(0, 0, 0, $payslip->month, 1, $payslip->year))) }}</div>
        <div class="net-pay-amount">
            <span class="net-pay-currency">RM</span>{{ number_format($payslip->net_salary, 2) }}
        </div>
    </div>

    {{-- Employer Contributions --}}
    <div class="section" style="margin-top: 14px;">
        <div class="section-title">Employer Contributions (Not Deducted from Salary)</div>
        <table class="statutory-table">
            <thead>
                <tr>
                    <th>Contribution</th>
                    <th class="amount-col">Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>EPF Employer Contribution (12%/13%)</td>
                    <td class="amount-col">{{ number_format($payslip->epf_employer ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td>SOCSO Employer Contribution</td>
                    <td class="amount-col">{{ number_format($payslip->socso_employer ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td>EIS Employer Contribution (0.2%)</td>
                    <td class="amount-col">{{ number_format($payslip->eis_employer ?? 0, 2) }}</td>
                </tr>
                <tr style="background-color: #eff6ff; font-weight: bold;">
                    <td>Total Employer Cost</td>
                    <td class="amount-col">
                        {{ number_format(
                            (float)($payslip->epf_employer ?? 0) +
                            (float)($payslip->socso_employer ?? 0) +
                            (float)($payslip->eis_employer ?? 0),
                            2
                        ) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Summary Table --}}
    <div class="section">
        <table class="summary-row">
            <tr>
                <td class="summary-label">Gross Salary</td>
                <td class="summary-value">RM {{ number_format($payslip->gross_salary, 2) }}</td>
                <td class="summary-label">EPF (Employee)</td>
                <td class="summary-value">RM {{ number_format($payslip->epf_employee ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Deductions</td>
                <td class="summary-value">RM {{ number_format($payslip->total_deductions, 2) }}</td>
                <td class="summary-label">EPF (Employer)</td>
                <td class="summary-value">RM {{ number_format($payslip->epf_employer ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label" style="background-color: #dbeafe; color: #1e40af; font-size: 11px;">NET PAY</td>
                <td class="summary-value" style="background-color: #dbeafe; color: #1e40af; font-weight: bold; font-size: 11px;">RM {{ number_format($payslip->net_salary, 2) }}</td>
                <td class="summary-label">SOCSO + EIS</td>
                <td class="summary-value">
                    RM {{ number_format(
                        (float)($payslip->socso_employee ?? 0) +
                        (float)($payslip->eis_employee ?? 0),
                        2
                    ) }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>This payslip is computer generated and does not require a signature.</p>
        <p style="margin-top: 4px;">
            Generated on {{ now()->format('d M Y, h:i A') }} |
            {{ $settings['company_name'] ?? config('app.name') }}
        </p>
    </div>

</body>
</html>
