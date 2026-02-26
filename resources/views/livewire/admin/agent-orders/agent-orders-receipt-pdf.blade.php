<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        @php
            function numberToWordsHelper($number) {
                $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
                $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

                $integer = floor($number);
                $decimal = round(($number - $integer) * 100);

                $words = '';

                if ($integer >= 1000) {
                    $thousands = floor($integer / 1000);
                    $words .= convertHundredsHelper($thousands, $ones, $tens) . ' THOUSAND ';
                    $integer %= 1000;
                }

                if ($integer >= 100) {
                    $words .= convertHundredsHelper($integer, $ones, $tens);
                } elseif ($integer > 0) {
                    $words .= convertTensHelper($integer, $ones, $tens);
                }

                $words = trim($words);

                if ($decimal > 0) {
                    $words .= ' AND CENTS ' . convertTensHelper($decimal, $ones, $tens);
                }

                return 'RINGGIT MALAYSIA : ' . $words . ' ONLY';
            }

            function convertHundredsHelper($number, $ones, $tens) {
                $result = '';
                if ($number >= 100) {
                    $result .= $ones[floor($number / 100)] . ' HUNDRED ';
                    $number %= 100;
                }
                $result .= convertTensHelper($number, $ones, $tens);
                return trim($result);
            }

            function convertTensHelper($number, $ones, $tens) {
                if ($number < 20) {
                    return $ones[$number];
                }
                return $tens[floor($number / 10)] . ' ' . $ones[$number % 10];
            }

            $date = $order->order_date ?? $order->created_at;
            $yearMonth = $date->format('y/m');
            $sequence = str_pad($order->id, 3, '0', STR_PAD_LEFT);
            $invoiceNumber = "INV{$yearMonth}-{$sequence}";
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
            border-bottom: 3px solid #6b21a8;
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

        .billing-column { width: 50%; vertical-align: top; }
        .invoice-column { width: 50%; vertical-align: top; text-align: right; }

        .section-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .billing-name {
            font-weight: bold;
            font-size: 11px;
            color: #1f2937;
            margin-bottom: 2px;
        }

        .billing-detail { font-size: 9px; color: #4b5563; line-height: 1.4; }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #6b21a8;
            margin-bottom: 8px;
        }

        .invoice-details table { margin-left: auto; }
        .invoice-details td { padding: 2px 0; font-size: 9px; }
        .invoice-details .label { color: #666; padding-right: 8px; }
        .invoice-details .value { font-weight: 600; color: #1f2937; }

        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }

        .items-table th {
            background-color: #6b21a8;
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

        .items-table .item-code { font-weight: 600; }
        .items-table .warehouse-info { font-size: 8px; color: #6b7280; }

        .total-section {
            border-top: 2px solid #d1d5db;
            padding-top: 10px;
            margin-top: 8px;
        }

        .total-section table { width: 100%; }

        .amount-words {
            font-size: 8px;
            color: #4b5563;
            text-transform: uppercase;
            vertical-align: middle;
            width: 60%;
        }

        .total-amount {
            text-align: right;
            vertical-align: middle;
            width: 40%;
        }

        .total-label { font-size: 10px; color: #4b5563; font-weight: 600; }
        .total-value { font-size: 18px; font-weight: bold; color: #1f2937; }

        .notes-section { margin-top: 15px; }
        .notes-section table { width: 100%; }

        .notes-column { width: 60%; vertical-align: top; }
        .signature-column { width: 40%; vertical-align: bottom; text-align: right; }

        .notes-title { font-weight: 600; font-size: 9px; color: #1f2937; margin-bottom: 4px; }
        .notes-list { font-size: 8px; color: #4b5563; line-height: 1.5; }
        .notes-list ol { padding-left: 12px; }

        .bank-info { margin-top: 8px; font-size: 8px; }
        .bank-info .label { color: #4b5563; }
        .bank-info .value { font-weight: 600; color: #1f2937; }

        .signature-text { font-size: 8px; font-style: italic; color: #6b7280; }

        .footer { margin-top: 15px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
        .footer table { width: 100%; }

        .footer-badge {
            background: #6b21a8;
            color: white;
            padding: 5px 15px;
            font-size: 8px;
            font-weight: 600;
            border-radius: 0 15px 15px 0;
            display: inline-block;
        }

        .footer-page { text-align: right; font-size: 9px; color: #6b7280; vertical-align: bottom; }
    </style>
</head>
<body>
    <div class="container">
        <div class="company-header">
            <div class="company-name">{{ config('app.company.name') }}</div>
            <div class="company-registration">({{ config('app.company.registration') }})</div>
            <div class="company-address">
                {{ config('app.company.address_line_1') }}, {{ config('app.company.address_line_2') }}<br>
                Phone: {{ config('app.company.phone') }} &nbsp;&nbsp; email: {{ config('app.company.email') }}
            </div>
        </div>

        <div class="main-content">
            <div class="header-section">
                <table>
                    <tr>
                        <td class="billing-column">
                            <div class="section-label">Bill To (Agent)</div>
                            <div class="billing-name">
                                {{ $order->agent?->company_name ?? $order->agent?->name ?? 'Agent' }}
                            </div>
                            @if($order->agent?->company_name && $order->agent?->name !== $order->agent?->company_name)
                                <div class="billing-detail">Attn: {{ $order->agent->contact_person ?? $order->agent->name }}</div>
                            @endif
                            @if($order->agent?->address)
                                @php $agentAddress = $order->agent->address; @endphp
                                @if(!empty($agentAddress['street']))
                                    <div class="billing-detail">{{ $agentAddress['street'] }}</div>
                                @endif
                                @php
                                    $addressLine = collect([
                                        $agentAddress['postal_code'] ?? null,
                                        $agentAddress['city'] ?? null,
                                        $agentAddress['state'] ?? null,
                                    ])->filter()->implode(', ');
                                @endphp
                                @if($addressLine)
                                    <div class="billing-detail">{{ $addressLine }}</div>
                                @endif
                                @if(!empty($agentAddress['country']))
                                    <div class="billing-detail">{{ $agentAddress['country'] }}</div>
                                @endif
                            @endif
                            @if($order->agent?->phone)
                                <div class="billing-detail" style="margin-top: 4px;">Tel: {{ $order->agent->phone }}</div>
                            @endif
                        </td>
                        <td class="invoice-column">
                            <div class="invoice-title">INVOICE</div>
                            <div class="invoice-details">
                                <table>
                                    <tr><td class="label">Doc No. :</td><td class="value">{{ $invoiceNumber }}</td></tr>
                                    <tr><td class="label">Date :</td><td class="value">{{ ($order->order_date ?? $order->created_at)->format('d/m/Y') }}</td></tr>
                                    <tr><td class="label">Payment Terms :</td><td class="value">{{ $order->agent?->payment_terms ?? 'Immediate' }}</td></tr>
                                    <tr><td class="label">Agent Code :</td><td class="value">{{ $order->agent?->agent_code ?? '-' }}</td></tr>
                                    <tr><td class="label">Order Ref :</td><td class="value">{{ $order->order_number }}</td></tr>
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
                        <th class="text-left" style="width: 65px;">Item Code</th>
                        <th class="text-left">Description</th>
                        <th class="text-center" style="width: 50px;">Qty</th>
                        <th class="text-right" style="width: 65px;">Price/Unit</th>
                        <th class="text-center" style="width: 40px;">Disc</th>
                        <th class="text-right" style="width: 80px;">Sub Total ({{ $order->currency }})</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="item-code">{{ strtoupper(substr($item->sku ?? $item->product?->sku ?? 'ITEM', 0, 10)) }}</td>
                            <td>
                                {{ strtoupper($item->product?->name ?? $item->product_name ?? 'Product') }}
                                @if($item->warehouse)
                                    <div class="warehouse-info">From: {{ $item->warehouse->name }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format($item->quantity_ordered, 2) }}</td>
                            <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-center">@if($item->discount_amount > 0){{ number_format($item->discount_amount, 2) }}@endif</td>
                            <td class="text-right" style="font-weight: 600;">{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                    @if($order->shipping_cost > 0)
                        <tr>
                            <td>{{ $order->items->count() + 1 }}</td>
                            <td class="item-code">SHIPPING</td>
                            <td>SHIPPING / DELIVERY CHARGE</td>
                            <td class="text-center">1.00</td>
                            <td class="text-right">{{ number_format($order->shipping_cost, 2) }}</td>
                            <td class="text-center"></td>
                            <td class="text-right" style="font-weight: 600;">{{ number_format($order->shipping_cost, 2) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="total-section">
                <table>
                    <tr>
                        <td class="amount-words">{{ numberToWordsHelper($order->total_amount) }}</td>
                        <td class="total-amount">
                            <span class="total-label">Total :</span>
                            <span class="total-value">{{ number_format($order->total_amount, 2) }}</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="notes-section">
                <table>
                    <tr>
                        <td class="notes-column">
                            <div class="notes-title">Note :</div>
                            <div class="notes-list">
                                <ol>
                                    <li>All cheques should be crossed and made payable to <strong>{{ config('app.company.name') }}</strong></li>
                                    <li>Good sold are neither returnable nor refundable.</li>
                                </ol>
                            </div>
                            <div class="bank-info">
                                <span class="label">Bank account No:</span><br>
                                <span class="value">{{ config('app.company.bank_name') }} {{ config('app.company.bank_account') }}</span>
                            </div>
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
                        <td><span class="footer-badge">{{ config('app.company.name') }} ({{ config('app.company.registration') }} ({{ config('app.company.tax_id') }}))</span></td>
                        <td class="footer-page">1 of 1</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
