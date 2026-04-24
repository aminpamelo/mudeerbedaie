<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you &mdash; {{ $campaign->title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
<div class="mx-auto max-w-2xl p-6">
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold tracking-tight">Thanks for applying!</h1>
        <p class="mt-3 text-gray-700">
            The <strong>{{ $campaign->title }}</strong> team will review your application shortly.
        </p>
        <p class="mt-3 text-sm text-gray-500">
            We&rsquo;ve sent a confirmation to the email address you provided. Please check your inbox (and spam folder) for next steps.
        </p>
    </div>
</div>
</body>
</html>
