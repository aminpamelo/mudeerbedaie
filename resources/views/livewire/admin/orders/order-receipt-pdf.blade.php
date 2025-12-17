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
            font-size: 14px;
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
            display: inline-block;
            width: 40%;
        }

        .value {
            display: inline-block;
        }

        .address-box {
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
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #cce5ff; color: #004085; }
        .status-shipped { background-color: #e2d4f0; color: #6f42c1; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ config('app.name') }}</div>
            <div class="receipt-title">Order Receipt / Invoice</div>
        </div>

        <!-- Order & Customer Details -->
        <div class="section">
            <div class="two-column">
                <div class="column">
                    <div class="section-title">Order Details</div>
                    <div class="info-row">
                        <span class="label">Order Number:</span>
                        <span class="value">{{ $order->order_number }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Order Date:</span>
                        <span class="value">{{ $order->order_date?->format('M j, Y') ?? $order->created_at->format('M j, Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Order Status:</span>
                        <span class="value">
                            <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                        </span>
                    </div>
                    @php
                        $latestPayment = $order->payments()->latest()->first();
                    @endphp
                    <div class="info-row">
                        <span class="label">Payment Status:</span>
                        <span class="value">
                            <span class="status-badge status-{{ $latestPayment?->status ?? 'pending' }}">{{ ucfirst($latestPayment?->status ?? 'Pending') }}</span>
                        </span>
                    </div>
                </div>
                <div class="column">
                    <div class="section-title">Customer Information</div>
                    <div class="info-row">
                        <span class="label">Name:</span>
                        <span class="value">{{ $order->getCustomerName() }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value">{{ $order->getCustomerEmail() }}</span>
                    </div>
                    @php
                        $phone = $order->customer_phone ?? $order->billingAddress()?->phone ?? null;
                    @endphp
                    @if($phone)
                        <div class="info-row">
                            <span class="label">Phone:</span>
                            <span class="value">{{ $phone }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Addresses -->
        @php
            $billingAddress = $order->billingAddress();
            $shippingAddress = $order->shippingAddress();
        @endphp
        @if($billingAddress || $shippingAddress)
            <div class="section">
                <div class="two-column">
                    @if($billingAddress)
                        <div class="column">
                            <div class="section-title">Billing Address</div>
                            <div class="address-box">
                                <div>{{ $billingAddress->first_name }} {{ $billingAddress->last_name }}</div>
                                @if($billingAddress->company)
                                    <div>{{ $billingAddress->company }}</div>
                                @endif
                                <div>{{ $billingAddress->address_line_1 }}</div>
                                @if($billingAddress->address_line_2)
                                    <div>{{ $billingAddress->address_line_2 }}</div>
                                @endif
                                <div>{{ $billingAddress->city }}, {{ $billingAddress->state }} {{ $billingAddress->postal_code }}</div>
                                <div>{{ $billingAddress->country }}</div>
                            </div>
                        </div>
                    @endif

                    @if($shippingAddress)
                        <div class="column">
                            <div class="section-title">Shipping Address</div>
                            <div class="address-box">
                                <div>{{ $shippingAddress->first_name }} {{ $shippingAddress->last_name }}</div>
                                @if($shippingAddress->company)
                                    <div>{{ $shippingAddress->company }}</div>
                                @endif
                                <div>{{ $shippingAddress->address_line_1 }}</div>
                                @if($shippingAddress->address_line_2)
                                    <div>{{ $shippingAddress->address_line_2 }}</div>
                                @endif
                                <div>{{ $shippingAddress->city }}, {{ $shippingAddress->state }} {{ $shippingAddress->postal_code }}</div>
                                <div>{{ $shippingAddress->country }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Order Items -->
        <div class="section">
            <div class="section-title">Order Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->product?->name ?? $item->product_name ?? 'Unknown Product' }}</strong>
                                @if($item->warehouse)
                                    <br><small>Warehouse: {{ $item->warehouse->name }}</small>
                                @endif
                            </td>
                            <td>{{ $item->sku ?? $item->product?->sku ?? '-' }}</td>
                            <td class="text-center">{{ $item->quantity_ordered }}</td>
                            <td class="text-right">{{ $order->currency }} {{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-right"><strong>{{ $order->currency }} {{ number_format($item->total_price, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right">Subtotal:</td>
                        <td class="text-right">{{ $order->currency }} {{ number_format($order->subtotal, 2) }}</td>
                    </tr>
                    @if($order->shipping_cost > 0)
                        <tr>
                            <td colspan="4" class="text-right">Shipping:</td>
                            <td class="text-right">{{ $order->currency }} {{ number_format($order->shipping_cost, 2) }}</td>
                        </tr>
                    @endif
                    @if($order->tax_amount > 0)
                        <tr>
                            <td colspan="4" class="text-right">Tax (GST):</td>
                            <td class="text-right">{{ $order->currency }} {{ number_format($order->tax_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if($order->discount_amount > 0)
                        <tr>
                            <td colspan="4" class="text-right" style="color: green;">Discount:</td>
                            <td class="text-right" style="color: green;">-{{ $order->currency }} {{ number_format($order->discount_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="total-row">
                        <td colspan="4" class="text-right">Total Amount:</td>
                        <td class="text-right">{{ $order->currency }} {{ number_format($order->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payment Information -->
        @if($latestPayment)
            <div class="section">
                <div class="section-title">Payment Information</div>
                <div class="payment-info">
                    <div class="two-column">
                        <div class="column">
                            <div class="info-row">
                                <span class="label">Payment Method:</span>
                                <span class="value" style="text-transform: capitalize;">{{ str_replace('_', ' ', $latestPayment->payment_method) }}</span>
                            </div>
                            @if($latestPayment->paid_at)
                                <div class="info-row">
                                    <span class="label">Paid At:</span>
                                    <span class="value">{{ $latestPayment->paid_at->format('M j, Y g:i A') }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="column">
                            <div class="info-row">
                                <span class="label">Currency:</span>
                                <span class="value">{{ strtoupper($order->currency) }}</span>
                            </div>
                            @if($latestPayment->transaction_id)
                                <div class="info-row">
                                    <span class="label">Transaction ID:</span>
                                    <span class="value" style="font-family: 'Courier New', monospace; font-size: 10px;">{{ $latestPayment->transaction_id }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p><strong>Thank you for your order!</strong></p>
            <p>For any questions regarding this order, please contact our support team.</p>
            <p style="margin-top: 20px;">Generated on {{ now()->format('M j, Y g:i A') }}</p>
        </div>
    </div>
</body>
</html>
