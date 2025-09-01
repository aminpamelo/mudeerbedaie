<x-mail::message>
# Payment Failed

Dear {{ $payment->user->name }},

We were unable to process your payment. Here are the details:

<x-mail::panel>
**Payment Attempt Details**
- Amount: {{ $payment->formatted_amount }}
- Payment Method: {{ $payment->type_label }}
- Attempted Date: {{ $payment->created_at->format('M d, Y \a\t H:i') }}
- Reference: #{{ $payment->id }}
</x-mail::panel>

**Invoice Information**
- Invoice Number: {{ $payment->invoice->invoice_number }}
- Course: {{ $payment->invoice->course->name }}
- Due Date: {{ $payment->invoice->due_date->format('M d, Y') }}

## What's Next?

@if($payment->isStripePayment())
This could be due to insufficient funds, an expired card, or your bank declining the transaction. Please try one of the following:

1. **Try a different payment method** - Use another card or bank account
2. **Update your payment information** - Ensure your card details are current
3. **Contact your bank** - They may have declined the transaction for security reasons
@else
Your bank transfer was not verified. Please ensure:

1. **Correct amount** - Make sure you transferred the exact invoice amount
2. **Correct reference** - Include the invoice number in your transfer reference
3. **Upload proof** - Provide a clear image of your bank transfer receipt
@endif

<x-mail::button :url="route('student.invoices.pay', $payment->invoice)">
Try Payment Again
</x-mail::button>

If you continue to experience issues, please contact our support team for assistance.

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>