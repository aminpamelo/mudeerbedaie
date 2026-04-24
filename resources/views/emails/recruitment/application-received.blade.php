<x-mail::message>
# Thanks, {{ $applicant->full_name }}!

We've received your application for **{{ $applicant->campaign->title }}**.

Your reference number is **{{ $applicant->applicant_number }}**. Please keep this for future correspondence.

We'll be in touch with next steps shortly. If you have any questions in the meantime, just reply to this email.

Thanks,<br>
The {{ config('app.name') }} Team
</x-mail::message>
