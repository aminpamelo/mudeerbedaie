<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .receipt-title {
            font-size: 16px;
            color: #666;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        .two-column {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }

        .column:last-child {
            padding-right: 0;
            padding-left: 20px;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .label {
            font-weight: bold;
            width: 40%;
            display: inline-block;
        }

        .value {
            display: inline-block;
        }

        .course-info {
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items-table th,
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            border-bottom: 2px solid #333;
        }

        .items-table .text-center {
            text-align: center;
        }

        .items-table .text-right {
            text-align: right;
        }

        .total-row {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 14px;
        }

        .payment-info {
            background-color: #e8f5e8;
            border: 1px solid #4caf50;
            padding: 15px;
            margin-top: 20px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            font-size: 11px;
            color: #666;
        }

        .status-badge {
            background-color: #4caf50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ config('app.name') }}</div>
            <div class="receipt-title">Payment Receipt</div>
        </div>

        <!-- Receipt Details -->
        <div class="section">
            <div class="section-title">Receipt Details</div>
            <div class="two-column">
                <div class="column">
                    <div class="info-row">
                        <span class="label">Receipt Number:</span>
                        <span class="value">{{ $order->order_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date Issued:</span>
                        <span class="value">{{ $order->paid_at?->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Payment Status:</span>
                        <span class="value">
                            <span class="status-badge">{{ $order->status_label }}</span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Billing Period:</span>
                        <span class="value">{{ $order->getPeriodDescription() }}</span>
                    </div>
                </div>
                <div class="column">
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value">{{ $order->student->user->name }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value">{{ $order->student->user->email }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Student ID:</span>
                        <span class="value">{{ $order->student->student_id }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Information -->
        <div class="section">
            <div class="section-title">Course Information</div>
            <div class="course-info">
                <div class="info-row">
                    <span class="label">Course Name:</span>
                    <span class="value" style="font-size: 14px; font-weight: bold;">{{ $order->course->name }}</span>
                </div>
                @if($order->course->description)
                    <div class="info-row">
                        <span class="label">Description:</span>
                        <span class="value">{{ Str::limit($order->course->description, 200) }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment Details -->
        <div class="section">
            <div class="section-title">Payment Details</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->description }}</strong>
                                @if($item->stripe_line_item_id)
                                    <br><small>ID: {{ $item->stripe_line_item_id }}</small>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">{{ $item->formatted_unit_price }}</td>
                            <td class="text-right"><strong>{{ $item->formatted_total_price }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No items found</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Total Amount:</td>
                        <td class="text-right">{{ $order->formatted_amount }}</td>
                    </tr>
                    @if($order->stripe_fee)
                        <tr>
                            <td colspan="3" class="text-right">Processing Fee:</td>
                            <td class="text-right">-RM {{ number_format($order->stripe_fee, 2) }}</td>
                        </tr>
                    @endif
                    @if($order->net_amount && $order->net_amount != $order->amount)
                        <tr>
                            <td colspan="3" class="text-right"><strong>Net Amount:</strong></td>
                            <td class="text-right"><strong>RM {{ number_format($order->net_amount, 2) }}</strong></td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>

        <!-- Payment Information -->
        <div class="section">
            <div class="section-title">Payment Information</div>
            <div class="payment-info">
                <div class="two-column">
                    <div class="column">
                        @if($order->stripe_charge_id)
                            <div class="info-row">
                                <span class="label">Transaction ID:</span>
                                <span class="value" style="font-family: 'Courier New', monospace; font-size: 10px;">{{ $order->stripe_charge_id }}</span>
                            </div>
                        @endif
                        @if($order->stripe_payment_intent_id)
                            <div class="info-row">
                                <span class="label">Payment Intent ID:</span>
                                <span class="value" style="font-family: 'Courier New', monospace; font-size: 10px;">{{ $order->stripe_payment_intent_id }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="column">
                        <div class="info-row">
                            <span class="label">Payment Method:</span>
                            <span class="value">Credit/Debit Card</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Currency:</span>
                            <span class="value">{{ strtoupper($order->currency) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your payment. This receipt serves as proof of your transaction.</strong></p>
            <p>For any questions regarding this receipt, please contact our support team.</p>
            <p style="margin-top: 20px;">Generated on {{ now()->format('M j, Y g:i A') }}</p>
        </div>
    </div>
</body>
</html>