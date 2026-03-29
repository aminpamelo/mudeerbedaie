<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Final Settlement - {{ $employee->full_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 14px; margin: 5px 0; color: #555; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 4px 8px; }
        .info-label { font-weight: bold; width: 200px; }
        .breakdown { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .breakdown th, .breakdown td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .breakdown th { background-color: #f5f5f5; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; background-color: #f0f0f0; }
        .net-row { font-weight: bold; background-color: #e8f5e9; font-size: 14px; }
        .footer { margin-top: 40px; }
        .signature-line { border-top: 1px solid #000; width: 200px; margin-top: 60px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <h2>FINAL SETTLEMENT STATEMENT</h2>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Employee Name:</td>
            <td>{{ $employee->full_name }}</td>
            <td class="info-label">Employee ID:</td>
            <td>{{ $employee->employee_id }}</td>
        </tr>
        <tr>
            <td class="info-label">Department:</td>
            <td>{{ $employee->department?->name ?? 'N/A' }}</td>
            <td class="info-label">Position:</td>
            <td>{{ $employee->position?->title ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Settlement Date:</td>
            <td>{{ now()->format('d/m/Y') }}</td>
            <td class="info-label">Status:</td>
            <td>{{ ucfirst($settlement->status) }}</td>
        </tr>
    </table>

    <table class="breakdown">
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="2" style="background-color: #e3f2fd;"><strong>Earnings</strong></td></tr>
            <tr>
                <td>Prorated Salary</td>
                <td class="amount">{{ number_format($settlement->prorated_salary, 2) }}</td>
            </tr>
            <tr>
                <td>Leave Encashment ({{ $settlement->leave_encashment_days }} days)</td>
                <td class="amount">{{ number_format($settlement->leave_encashment, 2) }}</td>
            </tr>
            @if($settlement->other_earnings > 0)
            <tr>
                <td>Other Earnings</td>
                <td class="amount">{{ number_format($settlement->other_earnings, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Gross</td>
                <td class="amount">{{ number_format($settlement->total_gross, 2) }}</td>
            </tr>

            <tr><td colspan="2" style="background-color: #fce4ec;"><strong>Deductions</strong></td></tr>
            <tr>
                <td>EPF (Employee)</td>
                <td class="amount">{{ number_format($settlement->epf_employee, 2) }}</td>
            </tr>
            <tr>
                <td>SOCSO (Employee)</td>
                <td class="amount">{{ number_format($settlement->socso_employee, 2) }}</td>
            </tr>
            <tr>
                <td>EIS (Employee)</td>
                <td class="amount">{{ number_format($settlement->eis_employee, 2) }}</td>
            </tr>
            <tr>
                <td>PCB (Tax)</td>
                <td class="amount">{{ number_format($settlement->pcb_amount, 2) }}</td>
            </tr>
            @if($settlement->other_deductions > 0)
            <tr>
                <td>Other Deductions</td>
                <td class="amount">{{ number_format($settlement->other_deductions, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Deductions</td>
                <td class="amount">{{ number_format($settlement->total_deductions, 2) }}</td>
            </tr>

            <tr class="net-row">
                <td>NET PAYABLE</td>
                <td class="amount">RM {{ number_format($settlement->net_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <p><strong>EPF Employer Contribution:</strong> RM {{ number_format($settlement->epf_employer, 2) }}</p>

    @if($settlement->notes)
    <p><strong>Notes:</strong> {{ $settlement->notes }}</p>
    @endif

    <div class="footer">
        <div style="display: inline-block; width: 45%;">
            <div class="signature-line"></div>
            <p>Prepared by (HR)</p>
        </div>
        <div style="display: inline-block; width: 45%; float: right;">
            <div class="signature-line"></div>
            <p>Approved by</p>
        </div>
    </div>
</body>
</html>
