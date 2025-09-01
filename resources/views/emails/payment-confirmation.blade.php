<x-mail::message>
# Payment Confirmed

Dear {{ $payment->user->name }},

Your payment has been successfully processed! Here are the details:

<x-mail::panel>
**Payment Details**
- Amount: {{ $payment->formatted_amount }}
- Payment Method: {{ $payment->type_label }}
- Transaction Date: {{ $payment->created_at->format('M d, Y \a\t H:i') }}
- Reference: #{{ $payment->id }}
</x-mail::panel>

**Invoice Information**
- Invoice Number: {{ $payment->invoice->invoice_number }}
- Course: {{ $payment->invoice->course->name }}
- Billing Period: {{ $payment->invoice->billing_period_start->format('M d') }} - {{ $payment->invoice->billing_period_end->format('M d, Y') }}

@if($payment->invoice->isPaid())
Your invoice has been paid in full. Thank you for your prompt payment!
@else
@php
$remainingAmount = $payment->invoice->amount - $payment->invoice->payments()->successful()->sum('amount');
@endphp
This payment has reduced your remaining balance to **RM {{ number_format($remainingAmount, 2) }}**.
@endif

<x-mail::button :url="route('student.invoices.show', $payment->invoice)">
View Invoice
</x-mail::button>

If you have any questions about this payment, please don't hesitate to contact us.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>