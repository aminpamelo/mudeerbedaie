<x-mail::message>
# Your Certificate

Dear {{ $certificateIssue->student->user->name ?? 'Student' }},

{!! nl2br(e($customMessage)) !!}

<x-mail::panel>
**Certificate Details**
- Certificate: {{ $certificateIssue->getCertificateName() }}
- Certificate Number: {{ $certificateIssue->certificate_number }}
- Issue Date: {{ $certificateIssue->issue_date->format('M d, Y') }}
</x-mail::panel>

The certificate PDF is attached to this email.

@php
    $verificationUrl = null;
    try {
        $verificationUrl = $certificateIssue->getVerificationUrl();
    } catch (\Exception $e) {
        // Verification URL not available
    }
@endphp

@if($verificationUrl)
<x-mail::button :url="$verificationUrl">
Verify Certificate
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }} Team
</x-mail::message>
