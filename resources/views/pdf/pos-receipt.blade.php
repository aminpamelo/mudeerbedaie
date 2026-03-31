<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        @php
            $date = $order->order_date ?? $order->created_at;
            $yearMonth = $date->format('y/m');
            $sequence = str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $receiptNumber = "RCP{$yearMonth}-{$sequence}";
        @endphp

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
            background: white;
        }

        .container { width: 100%; padding: 0; }

        .company-header {
            background: white;
            padding: 15px 20px;
            text-align: center;
            border-bottom: 3px solid #2563eb;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .company-registration { font-size: 9px; color: #6b7280; }
        .company-address { font-size: 9px; color: #4b5563; margin-top: 4px; }

        .main-content { padding: 15px 20px; }

        .header-section { width: 100%; margin-bottom: 12px; }
        .header-section table { width: 100%; }

        .customer-column { width: 50%; vertical-align: top; }
        .receipt-column { width: 50%; vertical-align: top; text-align: right; }

        .section-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .customer-name {
            font-weight: bold;
            font-size: 11px;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .customer-detail { font-size: 9px; color: #4b5563; line-height: 1.4; }

        .receipt-title {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .receipt-details table { margin-left: auto; }
        .receipt-details td { padding: 2px 0; font-size: 9px; }
        .receipt-details .label { color: #666; padding-right: 8px; }
        .receipt-details .value { font-weight: 600; color: #1f2937; }

        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }

        .items-table th {
            background-color: #2563eb;
            color: white;
            padding: 6px 5px;
            font-size: 9px;
            font-weight: 600;
        }

        .items-table th.text-left { text-align: left; }
        .items-table th.text-center { text-align: center; }
        .items-table th.text-right { text-align: right; }

        .items-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .items-table td.text-center { text-align: center; }
        .items-table td.text-right { text-align: right; }

        .items-table .item-name { font-weight: 600; }
        .items-table .item-variant { font-size: 8px; color: #6b7280; }

        .totals-section {
            margin-top: 8px;
            width: 100%;
        }

        .totals-section table { width: 100%; }

        .totals-left { width: 60%; vertical-align: top; }
        .totals-right { width: 40%; vertical-align: top; }

        .totals-right table { width: 100%; }
        .totals-right td { padding: 3px 0; font-size: 9px; }
        .totals-right .label { text-align: right; color: #4b5563; padding-right: 10px; }
        .totals-right .value { text-align: right; font-weight: 600; color: #1f2937; }

        .grand-total td {
            border-top: 2px solid #d1d5db;
            padding-top: 6px !important;
            font-size: 12px !important;
        }

        .grand-total .label { font-weight: 700; color: #1f2937; }
        .grand-total .value { font-weight: 700; color: #2563eb; font-size: 14px !important; }

        .payment-info {
            margin-top: 15px;
            padding: 10px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 4px;
        }

        .payment-info .title {
            font-size: 9px;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 4px;
        }

        .payment-info .detail {
            font-size: 9px;
            color: #4b5563;
            line-height: 1.5;
        }

        .notes-section { margin-top: 15px; }
        .notes-section table { width: 100%; }

        .notes-column { width: 60%; vertical-align: top; }
        .signature-column { width: 40%; vertical-align: bottom; text-align: right; }

        .notes-title { font-weight: 600; font-size: 9px; color: #1f2937; margin-bottom: 4px; }
        .notes-content { font-size: 8px; color: #4b5563; line-height: 1.5; }

        .bank-info { margin-top: 8px; font-size: 8px; }
        .bank-info .label { color: #4b5563; }
        .bank-info .value { font-weight: 600; color: #1f2937; }

        .signature-text { font-size: 8px; font-style: italic; color: #6b7280; }

        .footer { margin-top: 15px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
        .footer table { width: 100%; }

        .footer-badge {
            background: #2563eb;
            color: white;
            padding: 5px 15px;
            font-size: 8px;
            font-weight: 600;
            border-radius: 0 15px 15px 0;
            display: inline-block;
        }

        .footer-page { text-align: right; font-size: 9px; color: #6b7280; vertical-align: bottom; }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8px;
            font-weight: 600;
            border-radius: 10px;
            text-transform: uppercase;
        }

        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="company-header">
            <div class="company-name">{{ config('app.company.name', config('app.name')) }}</div>
            @if(config('app.company.registration'))
                <div class="company-registration">({{ config('app.company.registration') }})</div>
            @endif
            @if(config('app.company.address_line_1'))
                <div class="company-address">
                    {{ config('app.company.address_line_1') }}{{ config('app.company.address_line_2') ? ', ' . config('app.company.address_line_2') : '' }}<br>
                    @if(config('app.company.phone'))Phone: {{ config('app.company.phone') }} &nbsp;&nbsp;@endif
                    @if(config('app.company.email'))Email: {{ config('app.company.email') }}@endif
                </div>
            @endif
        </div>

        <div class="main-content">
            <div class="header-section">
                <table>
                    <tr>
                        <td class="customer-column">
                            <div class="section-label">Customer</div>
                            <div class="customer-name">
                                {{ $order->customer?->name ?? $order->customer_name ?? 'Walk-in Customer' }}
                            </div>
                            @if($order->customer_phone ?? $order->customer?->phone)
                                <div class="customer-detail">Tel: {{ $order->customer_phone ?? $order->customer?->phone }}</div>
                            @endif
                            @if($order->guest_email ?? $order->customer?->email)
                                <div class="customer-detail">Email: {{ $order->guest_email ?? $order->customer?->email }}</div>
                            @endif
                            @if($order->shipping_address)
                                <div class="customer-detail" style="margin-top: 4px;">
                                    @if(is_string($order->shipping_address))
                                        {{ $order->shipping_address }}
                                    @elseif(is_array($order->shipping_address) || is_object($order->shipping_address))
                                        {{ data_get($order->shipping_address, 'full_address', '') }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="receipt-column">
                            <div class="receipt-title">RECEIPT</div>
                            <div class="receipt-details">
                                <table>
                                    <tr>
                                        <td class="label">Receipt No. :</td>
                                        <td class="value">{{ $receiptNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Date :</td>
                                        <td class="value">{{ ($order->order_date ?? $order->created_at)->format('d/m/Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Time :</td>
                                        <td class="value">{{ ($order->order_date ?? $order->created_at)->format('h:i A') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Order Ref :</td>
                                        <td class="value">{{ $order->order_number }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Status :</td>
                                        <td class="value">
                                            @php
                                                $status = $order->paid_time ? 'paid' : ($order->status === 'cancelled' ? 'cancelled' : 'pending');
                                            @endphp
                                            <span class="status-badge status-{{ $status }}">{{ ucfirst($status) }}</span>
                                        </td>
                                    </tr>
                                    @if($order->metadata['salesperson_name'] ?? null)
                                        <tr>
                                            <td class="label">Served by :</td>
                                            <td class="value">{{ $order->metadata['salesperson_name'] }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th class="text-left" style="width: 25px;">No</th>
                        <th class="text-left">Description</th>
                        <th class="text-center" style="width: 50px;">Qty</th>
                        <th class="text-right" style="width: 80px;">Price (RM)</th>
                        <th class="text-right" style="width: 80px;">Total (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <span class="item-name">{{ $item->product_name ?? $item->itemable?->name ?? 'Item' }}</span>
                                @if($item->variant_name)
                                    <div class="item-variant">{{ $item->variant_name }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ (int) ($item->quantity_ordered ?? $item->quantity ?? 1) }}</td>
                            <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-right" style="font-weight: 600;">{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals-section">
                <table>
                    <tr>
                        <td class="totals-left"></td>
                        <td class="totals-right">
                            <table>
                                <tr>
                                    <td class="label">Subtotal</td>
                                    <td class="value">{{ number_format($order->subtotal ?? $order->total_amount + ($order->discount_amount ?? 0), 2) }}</td>
                                </tr>
                                @if(($order->discount_amount ?? 0) > 0)
                                    <tr>
                                        <td class="label">Discount</td>
                                        <td class="value" style="color: #dc2626;">- {{ number_format($order->discount_amount, 2) }}</td>
                                    </tr>
                                @endif
                                @if(($order->shipping_cost ?? 0) > 0)
                                    <tr>
                                        <td class="label">Shipping</td>
                                        <td class="value">{{ number_format($order->shipping_cost, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="grand-total">
                                    <td class="label">Total</td>
                                    <td class="value">RM {{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="payment-info">
                <div class="title">Payment Information</div>
                <div class="detail">
                    <strong>Method:</strong> {{ ucwords(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}
                    @if($order->paid_time)
                        &nbsp;&nbsp;|&nbsp;&nbsp;<strong>Paid:</strong> {{ \Carbon\Carbon::parse($order->paid_time)->format('d/m/Y h:i A') }}
                    @endif
                    @if($order->metadata['payment_reference'] ?? null)
                        &nbsp;&nbsp;|&nbsp;&nbsp;<strong>Ref:</strong> {{ $order->metadata['payment_reference'] }}
                    @endif
                </div>
            </div>

            @if($order->tracking_id || $order->internal_notes)
                <div class="notes-section">
                    <table>
                        <tr>
                            <td class="notes-column">
                                @if($order->internal_notes)
                                    <div class="notes-title">Notes</div>
                                    <div class="notes-content">{{ $order->internal_notes }}</div>
                                @endif
                            </td>
                            <td class="signature-column">
                                @if($order->tracking_id)
                                    <div class="notes-title">Tracking</div>
                                    <div class="notes-content">{{ $order->tracking_id }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            @endif

            <div class="notes-section" style="margin-top: 10px;">
                <table>
                    <tr>
                        <td class="notes-column">
                            @if(config('app.company.bank_name'))
                                <div class="bank-info">
                                    <span class="label">Bank Account:</span><br>
                                    <span class="value">{{ config('app.company.bank_name') }} {{ config('app.company.bank_account') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="signature-column">
                            <div class="signature-text">Computer generated, no signature required</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <table>
                    <tr>
                        <td>
                            <span class="footer-badge">
                                {{ config('app.company.name', config('app.name')) }}
                                @if(config('app.company.registration'))
                                    ({{ config('app.company.registration') }})
                                @endif
                            </span>
                        </td>
                        <td class="footer-page">1 of 1</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
