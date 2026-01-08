<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note - {{ $order->order_number }}</title>
    <style>
        @php
            $date = $order->order_date ?? $order->created_at;
            $sequence = str_pad($order->id, 5, '0', STR_PAD_LEFT);
            $deliveryNoteNumber = "DO-{$sequence}";
        @endphp

        @page {
            margin: 30mm 25mm 30mm 25mm;
            size: A4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
            background: white;
            padding: 15px;
        }

        /* Company Header */
        .company-header {
            text-align: center;
            padding-bottom: 15px;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #000;
            display: inline;
        }

        .company-registration {
            font-size: 11px;
            color: #333;
            display: inline;
        }

        .company-address {
            font-size: 9px;
            color: #333;
            margin-top: 3px;
        }

        /* Document Title */
        .document-title {
            font-size: 22px;
            font-weight: bold;
            color: #000;
            margin: 20px 0 15px 0;
        }

        /* Address Section */
        .address-section {
            width: 100%;
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-bottom: 10px;
        }

        .address-table {
            width: 100%;
            border-collapse: collapse;
        }

        .address-column {
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }

        .address-label {
            font-size: 8px;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }

        .address-company {
            font-size: 11px;
            font-weight: bold;
            color: #000;
            margin: 8px 0 5px 0;
        }

        .address-detail {
            font-size: 10px;
            color: #000;
            line-height: 1.6;
        }

        .contact-section {
            margin-top: 12px;
        }

        .contact-row {
            margin-bottom: 3px;
            font-size: 10px;
        }

        .contact-label {
            display: inline-block;
            width: 25px;
        }

        /* Info Header */
        .info-section {
            width: 100%;
            margin-top: 15px;
            border-top: 1px solid #000;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table th {
            font-size: 8px;
            font-weight: normal;
            color: #333;
            text-align: left;
            padding: 5px 5px;
            border-bottom: 1px solid #000;
        }

        .info-table td {
            font-size: 10px;
            font-weight: bold;
            color: #000;
            padding: 8px 5px;
        }

        /* Items Table */
        .items-section {
            margin-top: 15px;
            border-top: 1px solid #000;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            font-size: 9px;
            font-weight: normal;
            color: #333;
            text-align: left;
            padding: 8px 5px;
            border-bottom: 1px solid #ccc;
        }

        .items-table th.qty-col {
            text-align: right;
        }

        .items-table td {
            font-size: 10px;
            color: #000;
            padding: 10px 5px;
            vertical-align: top;
        }

        .items-table td.qty-col {
            text-align: right;
        }

        /* Total Section */
        .total-section {
            border-top: 2px solid #000;
            margin-top: 40px;
            padding-top: 10px;
        }

        .total-table {
            width: 100%;
            border-collapse: collapse;
        }

        .total-table td {
            padding: 5px;
        }

        .total-label {
            text-align: right;
            font-weight: bold;
            font-size: 11px;
        }

        .total-value {
            text-align: right;
            font-weight: bold;
            font-size: 11px;
            width: 100px;
        }

        /* Payment Terms */
        .payment-section {
            margin-top: 20px;
            border-top: 1px solid #000;
            padding-top: 8px;
        }

        .payment-label {
            font-size: 8px;
            color: #333;
        }

        .payment-value {
            font-size: 11px;
            font-weight: bold;
            color: #000;
            margin-top: 2px;
        }

        /* Confirmation */
        .confirmation-section {
            margin-top: 20px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        .confirmation-text {
            font-size: 9px;
            color: #333;
            line-height: 1.5;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 60px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-column {
            width: 50%;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 50px;
        }

        .signature-label {
            font-size: 9px;
            color: #333;
        }

        .company-signature {
            text-align: right;
        }

        .company-signature-label {
            font-size: 9px;
            color: #333;
            text-align: right;
        }

        .company-signature-name {
            font-size: 9px;
            font-weight: bold;
            color: #000;
            margin-top: 3px;
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="company-header">
        <span class="company-name">{{ config('app.company.name') }}</span>
        <span class="company-registration">({{ config('app.company.registration') }})</span>
        <div class="company-address">
            {{ config('app.company.address_line_1') }}, {{ config('app.company.address_line_2') }}<br>
            Phone: {{ config('app.company.phone') }} &nbsp;&nbsp; email: {{ config('app.company.email') }}
        </div>
    </div>

    <!-- Document Title -->
    <div class="document-title">Delivery Order</div>

    <!-- Address Section -->
    <div class="address-section">
        <table class="address-table">
            <tr>
                <!-- Billing Address -->
                <td class="address-column">
                    @php $billingAddress = $order->billingAddress(); @endphp
                    <div class="address-label">Billing Address</div>
                    <div class="address-company">
                        @if($billingAddress?->company)
                            {{ strtoupper($billingAddress->company) }}
                        @else
                            {{ strtoupper($billingAddress?->first_name ?? $order->getCustomerName()) }}
                            {{ strtoupper($billingAddress?->last_name ?? '') }}
                        @endif
                    </div>
                    @if($billingAddress)
                        <div class="address-detail">
                            {{ strtoupper($billingAddress->address_line_1) }},<br>
                            {{ strtoupper($billingAddress->postal_code) }} {{ strtoupper($billingAddress->city) }},<br>
                            {{ strtoupper($billingAddress->state) }}
                        </div>
                    @endif
                    <div class="contact-section">
                        <div class="contact-row"><span class="contact-label">Attn</span></div>
                        @php $phone = $order->customer_phone ?? $billingAddress?->phone ?? null; @endphp
                        <div class="contact-row"><span class="contact-label">Tel</span> {{ $phone ?? '' }}</div>
                        <div class="contact-row"><span class="contact-label">Fax</span></div>
                    </div>
                </td>

                <!-- Delivery Address -->
                <td class="address-column">
                    @php $shippingAddress = $order->shippingAddress() ?? $billingAddress; @endphp
                    <div class="address-label">Delivery Address</div>
                    <div class="address-company">
                        @if($shippingAddress?->company)
                            {{ strtoupper($shippingAddress->company) }}
                        @else
                            {{ strtoupper($shippingAddress?->first_name ?? $order->getCustomerName()) }}
                            {{ strtoupper($shippingAddress?->last_name ?? '') }}
                        @endif
                    </div>
                    @if($shippingAddress)
                        <div class="address-detail">
                            {{ strtoupper($shippingAddress->address_line_1) }},<br>
                            {{ strtoupper($shippingAddress->postal_code) }} {{ strtoupper($shippingAddress->city) }},<br>
                            {{ strtoupper($shippingAddress->state) }}
                        </div>
                    @endif
                    <div class="contact-section">
                        <div class="contact-row"><span class="contact-label">Attn</span></div>
                        @php $shippingPhone = $shippingAddress?->phone ?? $phone ?? null; @endphp
                        <div class="contact-row"><span class="contact-label">Tel</span> {{ $shippingPhone ?? '' }}</div>
                        <div class="contact-row"><span class="contact-label">Fax</span></div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Info Header -->
    <div class="info-section">
        <table class="info-table">
            <tr>
                <th style="width: 18%;">Customer Account</th>
                <th style="width: 15%;">Sales Executive</th>
                <th style="width: 12%;">Name</th>
                <th style="width: 12%;">Page No</th>
                <th style="width: 18%;">Doc No.</th>
                <th style="width: 15%;">Date</th>
            </tr>
            <tr>
                <td>{{ $order->customer?->customer_code ?? '-' }}</td>
                <td>{{ $order->agent?->name ? strtoupper(explode(' ', $order->agent->name)[0]) : '-' }}</td>
                <td>ADMIN</td>
                <td>1 of 1</td>
                <td>{{ $deliveryNoteNumber }}</td>
                <td>{{ ($order->order_date ?? $order->created_at)->format('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    <!-- Items Section -->
    @php $totalQty = 0; @endphp
    <div class="items-section">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="width: 72%;">Description</th>
                    <th style="width: 20%;" class="qty-col">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                    @php $totalQty += $item->quantity_ordered; @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ strtoupper($item->product?->name ?? $item->product_name ?? 'Product') }}</td>
                        <td class="qty-col">{{ number_format($item->quantity_ordered, 2) }} UNIT</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Total Section -->
    <div class="total-section">
        <table class="total-table">
            <tr>
                <td class="total-label">Total</td>
                <td class="total-value">{{ number_format($totalQty, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Payment Terms -->
    <div class="payment-section">
        <div class="payment-label">Payment Terms</div>
        <div class="payment-value">Immediate</div>
    </div>

    <!-- Confirmation -->
    <div class="confirmation-section">
        <div class="confirmation-text">
            I/We hereby confirmed and received to the above mentioned<br>
            goods in a good order & condition.
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td class="signature-column">
                    <div class="signature-line">
                        <div class="signature-label">Customer Company Stamp & Signature</div>
                    </div>
                </td>
                <td class="signature-column company-signature">
                    <div class="company-signature-label">Authorised Signature</div>
                    <div class="company-signature-name">{{ config('app.company.name') }} ({{ config('app.company.registration') }} ({{ config('app.company.tax_id') }}))</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
