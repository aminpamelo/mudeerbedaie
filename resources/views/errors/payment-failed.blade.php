<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center">
        <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-3">Payment Unsuccessful</h1>

        <p class="text-gray-600 mb-6">
            {{ $error ?? 'Your payment could not be processed. Please try again or contact support if the issue persists.' }}
        </p>

        <div class="space-y-3">
            <button onclick="window.history.back()" class="w-full px-6 py-3 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition-colors">
                Try Again
            </button>

            <a href="/" class="block w-full px-6 py-3 text-gray-600 font-medium rounded-xl border border-gray-300 hover:bg-gray-50 transition-colors">
                Go to Homepage
            </a>
        </div>

        <p class="text-sm text-gray-400 mt-6">
            If you were charged, please contact support with your order details.
        </p>
    </div>
</body>
</html>
