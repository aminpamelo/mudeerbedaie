<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applications closed &mdash; {{ $campaign->title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
<div class="mx-auto max-w-2xl p-6">
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-bold tracking-tight">{{ $campaign->title }}</h1>
        <p class="mt-4 text-gray-700">
            This campaign is no longer accepting applications.
        </p>
        <p class="mt-2 text-sm text-gray-500">
            Please check back later for new opportunities.
        </p>
    </div>
</div>
</body>
</html>
