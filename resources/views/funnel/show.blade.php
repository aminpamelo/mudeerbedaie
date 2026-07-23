<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $metaTitle }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    <!-- Favicon -->
    @if($funnel->settings['favicon'] ?? null)
        <link rel="icon" href="{{ $funnel->settings['favicon'] }}">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css'])

    <!-- Funnel Base Styles -->
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #111827;
            background-color: {{ $funnel->settings['background_color'] ?? '#ffffff' }};
        }

        .puck-page {
            min-height: {{ $step->type === 'checkout' ? 'auto' : '100vh' }};
        }

        /* Responsive images */
        img {
            max-width: 100%;
            height: auto;
        }

        /* Form styling */
        input, textarea, select {
            font-family: inherit;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Button hover effects */
        .puck-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Countdown animation */
        .puck-countdown [class^="countdown-"] {
            transition: all 0.3s ease;
        }

        /* Loading state */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Error message */
        .form-error {
            color: #dc2626;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Success message */
        .form-success {
            color: #059669;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Checkout form scoped styles - prevent Puck/LadiPage CSS conflicts */
        /* Force checkout above LadiPage BODY_BACKGROUND overlay */
        .puck-checkout,
        #funnel-checkout-form,
        #funnel-checkout-container {
            position: relative !important;
            z-index: 100 !important;
        }

        .funnel-checkout {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 16px;
            background-color: #f9fafb;
            color: #111827;
            line-height: 1.5;
            box-sizing: border-box;
            position: relative;
            z-index: 100;
        }

        .funnel-checkout *, .funnel-checkout *::before, .funnel-checkout *::after {
            box-sizing: border-box !important;
        }

        /* Force base styles on ALL descendants to prevent LadiPage/Puck CSS interference */
        .funnel-checkout * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            visibility: visible !important;
        }

        /* Default text color for elements without explicit color classes */
        .funnel-checkout h1, .funnel-checkout h2, .funnel-checkout h3,
        .funnel-checkout h4, .funnel-checkout h5, .funnel-checkout h6 {
            color: #111827 !important;
            margin: 0 !important;
        }

        .funnel-checkout p, .funnel-checkout span, .funnel-checkout div,
        .funnel-checkout label, .funnel-checkout a {
            color: #111827;
        }

        .funnel-checkout button {
            color: #111827;
        }

        /* Display */
        .funnel-checkout .flex { display: flex !important; }
        .funnel-checkout .grid { display: grid !important; }
        .funnel-checkout .inline-block { display: inline-block !important; }
        .funnel-checkout .inline-flex { display: inline-flex !important; }
        .funnel-checkout .block { display: block !important; }
        .funnel-checkout .hidden { display: none !important; }

        /* Flexbox */
        .funnel-checkout .items-center { align-items: center !important; }
        .funnel-checkout .items-start { align-items: flex-start !important; }
        .funnel-checkout .justify-center { justify-content: center !important; }
        .funnel-checkout .justify-between { justify-content: space-between !important; }
        .funnel-checkout .flex-1 { flex: 1 1 0% !important; }
        .funnel-checkout .flex-shrink-0 { flex-shrink: 0 !important; }
        .funnel-checkout .min-w-0 { min-width: 0 !important; }

        /* Grid */
        .funnel-checkout .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)) !important; }
        .funnel-checkout .gap-8 { gap: 32px !important; }
        .funnel-checkout .gap-4 { gap: 16px !important; }
        .funnel-checkout .gap-2 { gap: 8px !important; }
        .funnel-checkout .gap-1\.5 { gap: 6px !important; }

        /* Spacing utilities */
        .funnel-checkout .space-y-6 > * + * { margin-top: 24px !important; }
        .funnel-checkout .space-y-4 > * + * { margin-top: 16px !important; }
        .funnel-checkout .space-y-3 > * + * { margin-top: 12px !important; }
        .funnel-checkout .space-y-1 > * + * { margin-top: 4px !important; }
        .funnel-checkout .space-x-4 > * + * { margin-left: 16px !important; }
        .funnel-checkout .space-x-6 > * + * { margin-left: 24px !important; }

        /* Margin */
        .funnel-checkout .mb-1 { margin-bottom: 4px !important; }
        .funnel-checkout .mb-2 { margin-bottom: 8px !important; }
        .funnel-checkout .mb-4 { margin-bottom: 16px !important; }
        .funnel-checkout .mb-6 { margin-bottom: 24px !important; }
        .funnel-checkout .mb-8 { margin-bottom: 32px !important; }
        .funnel-checkout .mt-1 { margin-top: 4px !important; }
        .funnel-checkout .mt-2 { margin-top: 8px !important; }
        .funnel-checkout .mt-3 { margin-top: 12px !important; }
        .funnel-checkout .mt-4 { margin-top: 16px !important; }
        .funnel-checkout .mt-6 { margin-top: 24px !important; }
        .funnel-checkout .mt-8 { margin-top: 32px !important; }
        .funnel-checkout .ml-1 { margin-left: 4px !important; }
        .funnel-checkout .ml-2 { margin-left: 8px !important; }
        .funnel-checkout .ml-4 { margin-left: 16px !important; }
        .funnel-checkout .mr-1 { margin-right: 4px !important; }
        .funnel-checkout .mr-2 { margin-right: 8px !important; }
        .funnel-checkout .mr-3 { margin-right: 12px !important; }

        /* Padding */
        .funnel-checkout .p-2 { padding: 8px !important; }
        .funnel-checkout .p-3 { padding: 12px !important; }
        .funnel-checkout .p-4 { padding: 16px !important; }
        .funnel-checkout .p-6 { padding: 24px !important; }
        .funnel-checkout .px-2 { padding-left: 8px !important; padding-right: 8px !important; }
        .funnel-checkout .px-3 { padding-left: 12px !important; padding-right: 12px !important; }
        .funnel-checkout .px-4 { padding-left: 16px !important; padding-right: 16px !important; }
        .funnel-checkout .px-6 { padding-left: 24px !important; padding-right: 24px !important; }
        .funnel-checkout .px-8 { padding-left: 32px !important; padding-right: 32px !important; }
        .funnel-checkout .py-1 { padding-top: 4px !important; padding-bottom: 4px !important; }
        .funnel-checkout .py-1\.5 { padding-top: 6px !important; padding-bottom: 6px !important; }
        .funnel-checkout .py-2 { padding-top: 8px !important; padding-bottom: 8px !important; }
        .funnel-checkout .py-3 { padding-top: 12px !important; padding-bottom: 12px !important; }
        .funnel-checkout .py-8 { padding-top: 32px !important; padding-bottom: 32px !important; }
        .funnel-checkout .pt-2 { padding-top: 8px !important; }
        .funnel-checkout .pt-3 { padding-top: 12px !important; }
        .funnel-checkout .pt-4 { padding-top: 16px !important; }

        /* Width & Height */
        .funnel-checkout .w-full { width: 100% !important; }
        .funnel-checkout .w-8 { width: 32px !important; }
        .funnel-checkout .w-5 { width: 20px !important; }
        .funnel-checkout .w-4 { width: 16px !important; }
        .funnel-checkout .w-3\.5 { width: 14px !important; }
        .funnel-checkout .w-3 { width: 12px !important; }
        .funnel-checkout .w-2\.5 { width: 10px !important; }
        .funnel-checkout .w-1 { width: 4px !important; }
        .funnel-checkout .w-64 { width: 256px !important; }
        .funnel-checkout .h-8 { height: 32px !important; }
        .funnel-checkout .h-5 { height: 20px !important; }
        .funnel-checkout .h-4 { height: 16px !important; }
        .funnel-checkout .h-3\.5 { height: 14px !important; }
        .funnel-checkout .h-3 { height: 12px !important; }
        .funnel-checkout .h-2\.5 { height: 10px !important; }
        .funnel-checkout .h-1 { height: 4px !important; }
        .funnel-checkout .h-px { height: 1px !important; }
        .funnel-checkout .max-w-16 { max-width: 64px !important; }
        .funnel-checkout .max-h-48 { max-height: 192px !important; }

        /* Typography */
        .funnel-checkout .text-lg { font-size: 18px !important; line-height: 28px !important; }
        .funnel-checkout .text-sm { font-size: 14px !important; line-height: 20px !important; }
        .funnel-checkout .text-xs { font-size: 12px !important; line-height: 16px !important; }
        .funnel-checkout .font-semibold { font-weight: 600 !important; }
        .funnel-checkout .font-bold { font-weight: 700 !important; }
        .funnel-checkout .font-medium { font-weight: 500 !important; }
        .funnel-checkout .line-through { text-decoration: line-through !important; }
        .funnel-checkout .uppercase { text-transform: uppercase !important; }
        .funnel-checkout .whitespace-nowrap { white-space: nowrap !important; }
        .funnel-checkout .text-center { text-align: center !important; }
        .funnel-checkout .text-right { text-align: right !important; }
        .funnel-checkout .text-left { text-align: left !important; }

        /* Colors - Text */
        .funnel-checkout .text-white { color: #ffffff !important; }
        .funnel-checkout .text-gray-900 { color: #111827 !important; }
        .funnel-checkout .text-gray-700 { color: #374151 !important; }
        .funnel-checkout .text-gray-600 { color: #4b5563 !important; }
        .funnel-checkout .text-gray-500 { color: #6b7280 !important; }
        .funnel-checkout .text-gray-400 { color: #9ca3af !important; }
        .funnel-checkout .text-blue-600 { color: #2563eb !important; }
        .funnel-checkout .text-blue-700 { color: #1d4ed8 !important; }
        .funnel-checkout .text-blue-800 { color: #1e40af !important; }
        .funnel-checkout .text-green-500 { color: #22c55e !important; }
        .funnel-checkout .text-green-600 { color: #16a34a !important; }
        .funnel-checkout .text-green-700 { color: #15803d !important; }
        .funnel-checkout .text-green-800 { color: #166534 !important; }
        .funnel-checkout .text-yellow-900 { color: #713f12 !important; }
        .funnel-checkout .text-yellow-800 { color: #854d0e !important; }
        .funnel-checkout .text-red-500 { color: #ef4444 !important; }
        .funnel-checkout .text-red-700 { color: #b91c1c !important; }
        .funnel-checkout .text-purple-800 { color: #6b21a8 !important; }

        /* Colors - Background */
        .funnel-checkout .bg-white { background-color: #ffffff !important; }
        .funnel-checkout .bg-blue-600 { background-color: #2563eb !important; }
        .funnel-checkout .bg-blue-50 { background-color: #eff6ff !important; }
        .funnel-checkout .bg-gray-200 { background-color: #e5e7eb !important; }
        .funnel-checkout .bg-gray-50 { background-color: #f9fafb !important; }
        .funnel-checkout .bg-gray-100 { background-color: #f3f4f6 !important; }
        .funnel-checkout .bg-yellow-50 { background-color: #fefce8 !important; }
        .funnel-checkout .bg-yellow-400 { background-color: #facc15 !important; }
        .funnel-checkout .bg-red-50 { background-color: #fef2f2 !important; }
        .funnel-checkout .bg-green-50 { background-color: #f0fdf4 !important; }
        .funnel-checkout .bg-green-100 { background-color: #dcfce7 !important; }
        .funnel-checkout .bg-green-600 { background-color: #16a34a !important; }
        .funnel-checkout .bg-purple-100 { background-color: #f3e8ff !important; }

        /* Border */
        .funnel-checkout .border { border: 1px solid #e5e7eb !important; }
        .funnel-checkout .border-2 { border-width: 2px !important; border-style: solid !important; }
        .funnel-checkout .border-t { border-top: 1px solid #e5e7eb !important; }
        .funnel-checkout .border-b { border-bottom: 1px solid #e5e7eb !important; }
        .funnel-checkout .border-r-0 { border-right-width: 0 !important; }
        .funnel-checkout .border-dashed { border-style: dashed !important; }
        .funnel-checkout .border-gray-200 { border-color: #e5e7eb !important; }
        .funnel-checkout .border-gray-300 { border-color: #d1d5db !important; }
        .funnel-checkout .border-gray-100 { border-color: #f3f4f6 !important; }
        .funnel-checkout .border-blue-600 { border-color: #2563eb !important; }
        .funnel-checkout .border-blue-200 { border-color: #bfdbfe !important; }
        .funnel-checkout .border-green-600 { border-color: #16a34a !important; }
        .funnel-checkout .border-green-200 { border-color: #bbf7d0 !important; }
        .funnel-checkout .border-red-200 { border-color: #fecaca !important; }
        .funnel-checkout .border-yellow-400 { border-color: #facc15 !important; }
        .funnel-checkout .border-yellow-200 { border-color: #fef08a !important; }

        /* Border Radius */
        .funnel-checkout .rounded-lg { border-radius: 8px !important; }
        .funnel-checkout .rounded-full { border-radius: 9999px !important; }
        .funnel-checkout .rounded { border-radius: 4px !important; }
        .funnel-checkout .rounded-md { border-radius: 6px !important; }
        .funnel-checkout .rounded-l-lg { border-radius: 8px 0 0 8px !important; }
        .funnel-checkout .rounded-r-lg { border-radius: 0 8px 8px 0 !important; }

        /* Shadow & Ring */
        .funnel-checkout .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; }
        .funnel-checkout .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1) !important; }
        .funnel-checkout .ring-2 { box-shadow: 0 0 0 2px var(--ring-color, #2563eb) !important; }
        .funnel-checkout .ring-blue-600 { --ring-color: #2563eb !important; }
        .funnel-checkout .ring-green-600 { --ring-color: #16a34a !important; }
        .funnel-checkout .ring-blue-500 { --ring-color: #3b82f6 !important; }

        /* Position */
        .funnel-checkout .sticky { position: sticky !important; }
        .funnel-checkout .absolute { position: absolute !important; }
        .funnel-checkout .relative { position: relative !important; }
        .funnel-checkout .top-6 { top: 24px !important; }
        .funnel-checkout .z-50 { z-index: 50 !important; }

        /* Overflow */
        .funnel-checkout .overflow-hidden { overflow: hidden !important; }
        .funnel-checkout .overflow-y-auto { overflow-y: auto !important; }

        /* Interactivity */
        .funnel-checkout .cursor-pointer { cursor: pointer !important; }
        .funnel-checkout .cursor-not-allowed { cursor: not-allowed !important; }
        .funnel-checkout .transition-all { transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1) !important; }
        .funnel-checkout .transition-colors { transition: color 150ms, background-color 150ms, border-color 150ms !important; }
        .funnel-checkout .transition-transform { transition: transform 150ms !important; }
        .funnel-checkout .opacity-75 { opacity: 0.75 !important; }
        .funnel-checkout button { cursor: pointer; font-family: inherit; }

        /* Form inputs */
        .funnel-checkout input[type="text"],
        .funnel-checkout input[type="email"],
        .funnel-checkout input[type="tel"],
        .funnel-checkout select {
            width: 100% !important;
            padding: 8px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            line-height: 20px !important;
            background-color: #ffffff !important;
            color: #111827 !important;
            font-family: inherit !important;
            appearance: auto !important;
        }
        .funnel-checkout input[type="text"]:focus,
        .funnel-checkout input[type="email"]:focus,
        .funnel-checkout input[type="tel"]:focus,
        .funnel-checkout select:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        .funnel-checkout input[type="radio"],
        .funnel-checkout input[type="checkbox"] {
            width: 20px !important;
            height: 20px !important;
            accent-color: #2563eb !important;
            cursor: pointer !important;
        }
        .funnel-checkout label { display: block; cursor: default; }

        /* Hover states */
        .funnel-checkout .hover\:bg-blue-700:hover { background-color: #1d4ed8 !important; }
        .funnel-checkout .hover\:bg-green-700:hover { background-color: #15803d !important; }
        .funnel-checkout .hover\:bg-gray-50:hover { background-color: #f9fafb !important; }
        .funnel-checkout .hover\:bg-gray-100:hover { background-color: #f3f4f6 !important; }
        .funnel-checkout .hover\:bg-blue-50:hover { background-color: #eff6ff !important; }
        .funnel-checkout .hover\:border-gray-300:hover { border-color: #d1d5db !important; }

        /* Responsive */
        @media (min-width: 768px) {
            .funnel-checkout .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
            .funnel-checkout .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .funnel-checkout .md\:col-span-2 { grid-column: span 2 / span 2 !important; }
        }

        @media (min-width: 1024px) {
            .funnel-checkout .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
            .funnel-checkout .lg\:col-span-2 { grid-column: span 2 / span 2 !important; }
            .funnel-checkout .lg\:col-span-1 { grid-column: span 1 / span 1 !important; }
        }

        /* SVG icons */
        .funnel-checkout svg { display: inline-block; vertical-align: middle; }

        /* Responsive grid */
        @media (max-width: 768px) {
            .puck-columns {
                flex-direction: column !important;
            }

            .puck-column {
                flex: 1 1 100% !important;
            }

            .puck-features {
                grid-template-columns: 1fr !important;
            }

            .puck-hero h1 {
                font-size: 2rem !important;
            }

            .puck-countdown {
                gap: 8px !important;
            }

            .puck-countdown [class^="countdown-"] {
                font-size: 24px !important;
            }
        }
    </style>

    <!-- Modern single-page checkout styles (Clean & Trustworthy: indigo + emerald) -->
    <style>
        /* Layout shell */
        .funnel-checkout { max-width: 1080px !important; }
        .funnel-checkout .fc-main { align-items: start !important; }

        /* Extra utilities used by the modern checkout */
        .funnel-checkout .gap-3 { gap: 12px !important; }
        .funnel-checkout .flex-wrap { flex-wrap: wrap !important; }

        /* Indigo focus ring on inputs (matches accent) */
        .funnel-checkout input[type="text"]:focus,
        .funnel-checkout input[type="email"]:focus,
        .funnel-checkout input[type="tel"]:focus,
        .funnel-checkout select:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18) !important;
        }

        /* Secure header strip */
        .funnel-checkout .fc-topbar {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            margin: 0 0 24px !important;
            font-size: 13px !important;
            color: #64748b !important;
        }
        .funnel-checkout .fc-topbar-lock { display: inline-flex !important; color: #4f46e5 !important; }
        .funnel-checkout .fc-topbar-text { font-weight: 600 !important; color: #334155 !important; }
        .funnel-checkout .fc-topbar-sep { width: 4px !important; height: 4px !important; border-radius: 9999px !important; background: #cbd5e1 !important; }
        .funnel-checkout .fc-topbar-muted { color: #94a3b8 !important; }

        /* Cards & section headers */
        .funnel-checkout .fc-card {
            background: #ffffff !important;
            border: 1px solid #e9edf3 !important;
            border-radius: 16px !important;
            padding: 24px !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 12px 28px -16px rgba(15, 23, 42, 0.12) !important;
        }
        .funnel-checkout .fc-section-head { display: flex !important; align-items: center !important; gap: 12px !important; margin-bottom: 20px !important; }
        .funnel-checkout .fc-step {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 34px !important; height: 34px !important;
            flex-shrink: 0 !important;
            border-radius: 9999px !important;
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
            color: #ffffff !important;
            font-size: 15px !important; font-weight: 700 !important;
            box-shadow: 0 6px 14px -6px rgba(79, 70, 229, 0.6) !important;
        }
        .funnel-checkout .fc-section-heading { min-width: 0 !important; }
        .funnel-checkout .fc-section-title { font-size: 17px !important; font-weight: 700 !important; color: #0f172a !important; line-height: 1.3 !important; }
        .funnel-checkout .fc-section-sub { font-size: 13px !important; color: #64748b !important; margin-top: 2px !important; }
        .funnel-checkout .fc-subhead { font-size: 14px !important; font-weight: 700 !important; color: #1e293b !important; margin-bottom: 12px !important; }

        /* Labels, errors, alerts */
        .funnel-checkout .fc-label { display: block !important; font-size: 13px !important; font-weight: 600 !important; color: #334155 !important; margin-bottom: 6px !important; }
        .funnel-checkout .fc-error { display: block !important; font-size: 13px !important; color: #dc2626 !important; margin-top: 6px !important; }
        .funnel-checkout .fc-alert { display: flex !important; align-items: center !important; gap: 10px !important; padding: 12px 14px !important; border-radius: 12px !important; font-size: 13px !important; font-weight: 500 !important; margin-bottom: 16px !important; }
        .funnel-checkout .fc-alert-error { background: #fef2f2 !important; border: 1px solid #fecaca !important; color: #b91c1c !important; }
        .funnel-checkout .fc-alert-warn { background: #fffbeb !important; border: 1px solid #fde68a !important; color: #92400e !important; }

        /* Package selection cards */
        .funnel-checkout .fc-pkg {
            position: relative !important;
            display: flex !important;
            gap: 14px !important;
            padding: 16px !important;
            background: #ffffff !important;
            border: 2px solid #e2e8f0 !important;
            border-radius: 14px !important;
            cursor: pointer !important;
            transition: border-color 160ms, background-color 160ms, box-shadow 160ms, transform 160ms !important;
        }
        .funnel-checkout .fc-pkg:hover { border-color: #c7d2fe !important; box-shadow: 0 10px 24px -16px rgba(15, 23, 42, 0.28) !important; }
        .funnel-checkout .fc-pkg.is-active {
            border-color: #4f46e5 !important;
            background: #eef2ff !important;
            box-shadow: 0 0 0 1px #4f46e5, 0 10px 24px -12px rgba(79, 70, 229, 0.4) !important;
        }
        .funnel-checkout .fc-badge-popular {
            position: absolute !important;
            top: -10px !important; right: 14px !important;
            display: inline-flex !important; align-items: center !important; gap: 4px !important;
            padding: 3px 10px !important;
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
            color: #ffffff !important;
            font-size: 11px !important; font-weight: 700 !important;
            border-radius: 9999px !important;
            box-shadow: 0 4px 10px -3px rgba(79, 70, 229, 0.5) !important;
        }
        .funnel-checkout .fc-pkg-select { flex-shrink: 0 !important; padding-top: 2px !important; }
        .funnel-checkout .fc-pkg-body { flex: 1 1 0% !important; min-width: 0 !important; }
        .funnel-checkout .fc-pkg-toprow { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; }
        .funnel-checkout .fc-pkg-name-wrap { min-width: 0 !important; }
        .funnel-checkout .fc-pkg-name { font-size: 15px !important; font-weight: 700 !important; color: #0f172a !important; line-height: 1.35 !important; }
        .funnel-checkout .fc-pkg-desc { font-size: 13px !important; color: #64748b !important; margin-top: 3px !important; }
        .funnel-checkout .fc-pkg-pricing { text-align: right !important; flex-shrink: 0 !important; }
        .funnel-checkout .fc-pkg-price { font-size: 18px !important; font-weight: 800 !important; color: #0f172a !important; font-variant-numeric: tabular-nums !important; }
        .funnel-checkout .fc-pkg-compare { font-size: 13px !important; color: #94a3b8 !important; text-decoration: line-through !important; }
        .funnel-checkout .fc-pkg-tags { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; margin-top: 10px !important; }
        .funnel-checkout .fc-save { display: inline-flex !important; align-items: center !important; padding: 3px 10px !important; background: #ecfdf5 !important; color: #047857 !important; font-size: 12px !important; font-weight: 600 !important; border-radius: 9999px !important; }
        .funnel-checkout .fc-tag-sub { display: inline-flex !important; align-items: center !important; padding: 3px 10px !important; background: #eef2ff !important; color: #4338ca !important; font-size: 12px !important; font-weight: 600 !important; border-radius: 9999px !important; }
        .funnel-checkout .fc-pkg-includes { margin-top: 12px !important; padding-top: 12px !important; border-top: 1px solid #e2e8f0 !important; }
        .funnel-checkout .fc-pkg.is-active .fc-pkg-includes { border-top-color: #c7d2fe !important; }
        .funnel-checkout .fc-includes-label { font-size: 12px !important; font-weight: 600 !important; color: #64748b !important; margin-bottom: 6px !important; }
        .funnel-checkout .fc-include-item { display: flex !important; align-items: center !important; gap: 8px !important; font-size: 13px !important; color: #475569 !important; }
        .funnel-checkout .fc-include-check { color: #10b981 !important; flex-shrink: 0 !important; }

        /* Selection indicators (radio / check) */
        .funnel-checkout .fc-radio, .funnel-checkout .fc-check {
            display: inline-flex !important; align-items: center !important; justify-content: center !important;
            width: 22px !important; height: 22px !important;
            border: 2px solid #cbd5e1 !important; background: #ffffff !important;
            transition: background-color 150ms, border-color 150ms !important;
        }
        .funnel-checkout .fc-radio { border-radius: 9999px !important; }
        .funnel-checkout .fc-check { border-radius: 7px !important; }
        .funnel-checkout .fc-radio-dot { width: 10px !important; height: 10px !important; border-radius: 9999px !important; background: transparent !important; transition: background-color 150ms !important; }
        .funnel-checkout .fc-radio.is-active { border-color: #4f46e5 !important; }
        .funnel-checkout .fc-radio.is-active .fc-radio-dot { background: #4f46e5 !important; }
        .funnel-checkout .fc-check svg { opacity: 0 !important; color: #ffffff !important; transition: opacity 150ms !important; }
        .funnel-checkout .fc-check.is-active { background: #4f46e5 !important; border-color: #4f46e5 !important; }
        .funnel-checkout .fc-check.is-active svg { opacity: 1 !important; }

        /* Order bumps */
        .funnel-checkout .fc-bump-wrap { background: #fffbeb !important; border: 2px dashed #fcd34d !important; border-radius: 16px !important; padding: 18px !important; }
        .funnel-checkout .fc-bump-head { display: flex !important; align-items: center !important; gap: 10px !important; margin-bottom: 14px !important; }
        .funnel-checkout .fc-bump-flag { display: inline-flex !important; align-items: center !important; padding: 4px 9px !important; background: #f59e0b !important; color: #ffffff !important; font-size: 11px !important; font-weight: 800 !important; letter-spacing: 0.04em !important; border-radius: 6px !important; animation: fc-pulse 1.8s ease-in-out infinite !important; }
        .funnel-checkout .fc-bump-title { font-size: 16px !important; font-weight: 700 !important; color: #92400e !important; line-height: 1.3 !important; }
        .funnel-checkout .fc-bump {
            display: flex !important; gap: 14px !important;
            padding: 14px !important; background: #ffffff !important;
            border: 2px solid #e2e8f0 !important; border-radius: 12px !important;
            cursor: pointer !important;
            transition: border-color 160ms, background-color 160ms, box-shadow 160ms !important;
        }
        .funnel-checkout .fc-bump:hover { border-color: #a7f3d0 !important; }
        .funnel-checkout .fc-bump.is-active { border-color: #10b981 !important; background: #ecfdf5 !important; box-shadow: 0 0 0 1px #10b981 !important; }
        .funnel-checkout .fc-bump .fc-check.is-active { background: #059669 !important; border-color: #059669 !important; }
        .funnel-checkout .fc-bump-select { flex-shrink: 0 !important; padding-top: 2px !important; }
        .funnel-checkout .fc-bump-body { flex: 1 1 0% !important; min-width: 0 !important; }
        .funnel-checkout .fc-bump-toprow { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; }
        .funnel-checkout .fc-bump-name-wrap { min-width: 0 !important; }
        .funnel-checkout .fc-bump-kicker { display: block !important; font-size: 11px !important; font-weight: 700 !important; color: #059669 !important; text-transform: uppercase !important; letter-spacing: 0.04em !important; }
        .funnel-checkout .fc-bump-name { font-size: 14px !important; font-weight: 700 !important; color: #0f172a !important; margin-top: 2px !important; line-height: 1.35 !important; }
        .funnel-checkout .fc-bump-desc { font-size: 13px !important; color: #64748b !important; margin-top: 2px !important; white-space: pre-line !important; }
        .funnel-checkout .fc-bump-pricing { text-align: right !important; flex-shrink: 0 !important; }
        .funnel-checkout .fc-bump-price { font-size: 16px !important; font-weight: 800 !important; color: #059669 !important; font-variant-numeric: tabular-nums !important; }

        /* Shipping zone */
        .funnel-checkout .fc-zone { display: flex !important; align-items: center !important; gap: 12px !important; padding: 14px !important; border: 2px solid #e2e8f0 !important; border-radius: 12px !important; cursor: pointer !important; transition: border-color 160ms, background-color 160ms !important; }
        .funnel-checkout .fc-zone:hover { border-color: #c7d2fe !important; }
        .funnel-checkout .fc-zone.is-active { border-color: #4f46e5 !important; background: #eef2ff !important; }
        .funnel-checkout .fc-zone-radio { width: 20px !important; height: 20px !important; accent-color: #4f46e5 !important; flex-shrink: 0 !important; }
        .funnel-checkout .fc-zone-info { flex: 1 1 0% !important; min-width: 0 !important; }
        .funnel-checkout .fc-zone-name { font-size: 14px !important; font-weight: 600 !important; color: #0f172a !important; }
        .funnel-checkout .fc-zone-sub { font-size: 12px !important; color: #64748b !important; }
        .funnel-checkout .fc-zone-price { font-size: 14px !important; font-weight: 700 !important; color: #1e293b !important; flex-shrink: 0 !important; }

        /* Payment methods */
        .funnel-checkout .fc-pay { display: flex !important; align-items: center !important; gap: 12px !important; padding: 14px !important; background: #ffffff !important; border: 2px solid #e2e8f0 !important; border-radius: 12px !important; cursor: pointer !important; transition: border-color 160ms, background-color 160ms, box-shadow 160ms !important; }
        .funnel-checkout .fc-pay:hover { border-color: #c7d2fe !important; }
        .funnel-checkout .fc-pay.is-active { border-color: #4f46e5 !important; background: #eef2ff !important; box-shadow: 0 0 0 1px #4f46e5 !important; }
        .funnel-checkout .fc-pay-icon { display: inline-flex !important; flex-shrink: 0 !important; color: #64748b !important; }
        .funnel-checkout .fc-pay.is-active .fc-pay-icon { color: #4f46e5 !important; }
        .funnel-checkout .fc-pay-body { display: flex !important; flex-direction: column !important; flex: 1 1 0% !important; min-width: 0 !important; }
        .funnel-checkout .fc-pay-name { font-size: 14px !important; font-weight: 600 !important; color: #0f172a !important; }
        .funnel-checkout .fc-pay-desc { font-size: 12px !important; color: #64748b !important; }
        .funnel-checkout .fc-pay-badge { display: inline-flex !important; align-items: center !important; padding: 3px 8px !important; background: #ecfdf5 !important; color: #047857 !important; font-size: 11px !important; font-weight: 700 !important; border-radius: 9999px !important; flex-shrink: 0 !important; }
        .funnel-checkout .fc-pay-panel { margin-top: 14px !important; padding: 16px !important; background: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; }
        .funnel-checkout .fc-card-element { padding: 12px !important; background: #ffffff !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; min-height: 44px !important; }
        .funnel-checkout .fc-card-errors { margin-top: 8px !important; font-size: 13px !important; color: #dc2626 !important; }
        .funnel-checkout .fc-pay-note { margin-top: 8px !important; font-size: 12px !important; color: #64748b !important; }
        .funnel-checkout .fc-pay-notice { display: flex !important; align-items: center !important; gap: 10px !important; margin-top: 14px !important; padding: 14px !important; background: #eef2ff !important; border: 1px solid #c7d2fe !important; border-radius: 12px !important; font-size: 13px !important; color: #3730a3 !important; }
        .funnel-checkout .fc-pay-notice svg { color: #4f46e5 !important; }

        /* Trust badges */
        .funnel-checkout .fc-trust { display: flex !important; flex-wrap: wrap !important; align-items: center !important; justify-content: center !important; gap: 20px !important; padding: 4px 8px !important; }
        .funnel-checkout .fc-trust-item { display: flex !important; align-items: center !important; gap: 6px !important; font-size: 13px !important; font-weight: 500 !important; color: #64748b !important; }
        .funnel-checkout .fc-trust-item svg { color: #10b981 !important; }

        /* Sticky sidebar + order summary */
        .funnel-checkout .fc-sticky { position: sticky !important; top: 24px !important; }
        .funnel-checkout .fc-summary { background: #ffffff !important; border: 1px solid #e9edf3 !important; border-radius: 16px !important; padding: 22px !important; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 12px 28px -16px rgba(15, 23, 42, 0.12) !important; }
        .funnel-checkout .fc-summary-title { font-size: 16px !important; font-weight: 700 !important; color: #0f172a !important; margin-bottom: 16px !important; padding-bottom: 14px !important; border-bottom: 1px solid #e9edf3 !important; }
        .funnel-checkout .fc-summary-items { margin-bottom: 16px !important; }
        .funnel-checkout .fc-summary-line { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; font-size: 14px !important; margin-bottom: 10px !important; }
        .funnel-checkout .fc-summary-name { color: #475569 !important; }
        .funnel-checkout .fc-summary-amount { font-weight: 600 !important; color: #0f172a !important; font-variant-numeric: tabular-nums !important; white-space: nowrap !important; }
        .funnel-checkout .fc-summary-line.is-bump .fc-summary-name, .funnel-checkout .fc-summary-line.is-bump .fc-summary-amount { color: #047857 !important; }
        .funnel-checkout .fc-summary-sub { margin: -4px 0 10px 4px !important; }
        .funnel-checkout .fc-summary-subitem { display: flex !important; align-items: center !important; gap: 6px !important; font-size: 12px !important; color: #94a3b8 !important; }
        .funnel-checkout .fc-summary-dot { width: 5px !important; height: 5px !important; border-radius: 9999px !important; flex-shrink: 0 !important; }
        .funnel-checkout .fc-summary-dot.is-product { background: #6366f1 !important; }
        .funnel-checkout .fc-summary-dot.is-course { background: #a855f7 !important; }
        .funnel-checkout .fc-summary-empty { font-size: 13px !important; color: #94a3b8 !important; font-style: italic !important; }
        .funnel-checkout .fc-summary-breakdown { padding-top: 16px !important; border-top: 1px solid #e9edf3 !important; }
        .funnel-checkout .fc-summary-row { display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 12px !important; font-size: 14px !important; color: #475569 !important; margin-bottom: 8px !important; }
        .funnel-checkout .fc-summary-row span:last-child { font-weight: 600 !important; color: #334155 !important; font-variant-numeric: tabular-nums !important; }
        .funnel-checkout .fc-summary-row.is-bump, .funnel-checkout .fc-summary-row.is-bump span:last-child { color: #047857 !important; }
        .funnel-checkout .fc-summary-total { display: flex !important; align-items: center !important; justify-content: space-between !important; padding-top: 12px !important; margin-top: 4px !important; border-top: 1px solid #e9edf3 !important; font-size: 16px !important; font-weight: 800 !important; color: #0f172a !important; }
        .funnel-checkout .fc-summary-total-amount { font-size: 20px !important; font-weight: 800 !important; color: #0f172a !important; font-variant-numeric: tabular-nums !important; }
        .funnel-checkout .fc-summary-savings { display: flex !important; align-items: center !important; gap: 6px !important; margin-top: 12px !important; padding: 8px 12px !important; background: #ecfdf5 !important; border-radius: 10px !important; font-size: 13px !important; font-weight: 600 !important; color: #047857 !important; }
        .funnel-checkout .fc-summary-savings svg { color: #10b981 !important; flex-shrink: 0 !important; }

        /* Primary CTA */
        .funnel-checkout .fc-cta {
            display: flex !important; align-items: center !important; justify-content: center !important;
            width: 100% !important; padding: 15px 20px !important;
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: #ffffff !important; border: none !important;
            border-radius: 12px !important;
            font-size: 16px !important; font-weight: 700 !important; font-family: inherit !important;
            cursor: pointer !important;
            box-shadow: 0 10px 22px -8px rgba(5, 150, 105, 0.55) !important;
            transition: transform 150ms, box-shadow 150ms, background-color 150ms, opacity 150ms !important;
        }
        .funnel-checkout .fc-cta:hover { background: linear-gradient(135deg, #059669, #047857) !important; transform: translateY(-1px) !important; box-shadow: 0 14px 26px -8px rgba(5, 150, 105, 0.6) !important; }
        .funnel-checkout .fc-cta:active { transform: translateY(0) !important; }
        .funnel-checkout .fc-cta:disabled { opacity: 0.55 !important; cursor: not-allowed !important; transform: none !important; box-shadow: none !important; }
        .funnel-checkout .fc-cta-inner { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; }
        /* Loading toggle (own CSS so it isn't defeated by the !important display rules above) */
        .funnel-checkout .fc-cta .fc-cta-loading { display: none !important; }
        .funnel-checkout .fc-cta.is-loading .fc-cta-default { display: none !important; }
        .funnel-checkout .fc-cta.is-loading .fc-cta-loading { display: inline-flex !important; }
        .funnel-checkout .fc-cta-desktop { margin-top: 16px !important; }
        .funnel-checkout .fc-guarantee { display: flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; margin-top: 12px !important; font-size: 13px !important; color: #64748b !important; }
        .funnel-checkout .fc-guarantee svg { color: #10b981 !important; }
        .funnel-checkout .fc-spin { animation: fc-spin 0.7s linear infinite !important; }

        @media (max-width: 1023px) {
            .funnel-checkout .fc-sticky { position: static !important; }
        }
        @media (max-width: 767px) {
            .funnel-checkout .fc-card, .funnel-checkout .fc-summary { padding: 18px !important; }
            .funnel-checkout input[type="text"],
            .funnel-checkout input[type="email"],
            .funnel-checkout input[type="tel"] { font-size: 16px !important; }
        }

        @keyframes fc-spin { to { transform: rotate(360deg); } }
        @keyframes fc-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }
        @media (prefers-reduced-motion: reduce) {
            .funnel-checkout .fc-spin, .funnel-checkout .fc-bump-flag { animation: none !important; }
            .funnel-checkout .fc-pkg, .funnel-checkout .fc-bump, .funnel-checkout .fc-pay, .funnel-checkout .fc-zone, .funnel-checkout .fc-cta { transition: none !important; }
        }
    </style>

    <!-- Custom CSS -->
    @if($customCss)
        <style>{!! $customCss !!}</style>
    @endif

    <!-- Tracking Codes (Head) -->
    @if($funnel->settings['tracking_codes']['head'] ?? null)
        {!! $funnel->settings['tracking_codes']['head'] !!}
    @endif

    <!-- Facebook Pixel -->
    @include('funnel.partials.facebook-pixel')

    <!-- Google (GA4 + Google Ads) -->
    @include('funnel.partials.google-tracking')
</head>
<body>
    <!-- Progress Bar (if enabled) -->
    @if($step->settings['show_progress'] ?? false)
        @php
            $totalSteps = $funnel->steps()->where('is_active', true)->count();
            $currentStepOrder = $step->sort_order + 1;
            $progressPercent = ($currentStepOrder / $totalSteps) * 100;
        @endphp
        <div class="funnel-progress" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000;">
            <div style="height: 4px; background: #e5e7eb;">
                <div style="height: 100%; width: {{ $progressPercent }}%; background: linear-gradient(to right, #3b82f6, #8b5cf6); transition: width 0.3s ease;"></div>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <main class="funnel-content">
        @if($renderedContent)
            {!! $renderedContent !!}
        @else
            <!-- Fallback content when no Puck content -->
            <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px;">
                <div style="text-align: center; max-width: 600px;">
                    <h1 style="font-size: 2rem; color: #111827; margin-bottom: 16px;">{{ $step->name }}</h1>
                    <p style="color: #6b7280;">This page is being built. Check back soon!</p>
                </div>
            </div>
        @endif
    </main>

    <!-- Livewire Checkout Component (for checkout steps, or when a [checkout_form] tag / Checkout Form block is present) -->
    @php
        $hasCheckoutMarker = $renderedContent && str_contains((string) $renderedContent, 'id="funnel-checkout-form"');
    @endphp
    @if($step->type === 'checkout' || $hasCheckoutMarker)
        <div id="funnel-checkout-container" style="max-width: 1200px; margin: 0 auto; padding: 32px 16px;">
            @livewire('funnel.checkout-form', [
                'funnel' => $funnel,
                'step' => $step,
                'session' => $session,
            ])
        </div>

        {{-- Move checkout form into the Puck placeholder if it exists --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const placeholder = document.getElementById('funnel-checkout-form');
                const checkoutContainer = document.getElementById('funnel-checkout-container');
                if (placeholder && checkoutContainer) {
                    placeholder.appendChild(checkoutContainer);
                    checkoutContainer.style.padding = '0';
                    checkoutContainer.style.maxWidth = '100%';
                }
            });
        </script>
    @endif

    <!-- Exit Intent Popup (if enabled) -->
    @if($step->settings['exit_popup'] ?? false)
        <div id="exit-popup" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 16px; padding: 40px; max-width: 500px; margin: 20px; text-align: center;">
                <h2 style="font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 12px;">Wait! Don't Leave Yet!</h2>
                <p style="color: #6b7280; margin-bottom: 24px;">{{ $step->settings['exit_popup_message'] ?? 'You might miss out on this special offer.' }}</p>
                <button onclick="document.getElementById('exit-popup').style.display='none'" style="padding: 12px 32px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Stay on Page</button>
                <div style="margin-top: 16px;">
                    <a href="#" onclick="document.getElementById('exit-popup').style.display='none'; return false;" style="color: #6b7280; font-size: 14px;">No thanks, I'll pass</a>
                </div>
            </div>
        </div>
    @endif

    <!-- Scripts -->
    @vite(['resources/js/app.js'])

    <!-- Funnel Scripts -->
    <script>
        // Configuration
        window.funnelConfig = {
            funnelId: {{ $funnel->id }},
            funnelUuid: '{{ $funnel->uuid }}',
            funnelSlug: '{{ $funnel->slug }}',
            stepId: {{ $step->id }},
            stepSlug: '{{ $step->slug }}',
            stepType: '{{ $step->type }}',
            sessionUuid: '{{ $session->uuid }}',
            isCustomDomain: {{ !empty($isCustomDomain) ? 'true' : 'false' }},
            nextStepUrl: '{{ $nextStep ? (!empty($isCustomDomain) ? "/{$nextStep->slug}" : "/f/{$funnel->slug}/{$nextStep->slug}") : '' }}',
            csrfToken: '{{ csrf_token() }}',
        };

        // Track time on page
        let pageStartTime = Date.now();
        let timeOnPage = 0;

        setInterval(() => {
            timeOnPage = Math.floor((Date.now() - pageStartTime) / 1000);
        }, 1000);

        // Send time on page when leaving
        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon('/api/funnel-events', JSON.stringify({
                type: 'time_on_page',
                funnel_id: window.funnelConfig.funnelId,
                step_id: window.funnelConfig.stepId,
                session_uuid: window.funnelConfig.sessionUuid,
                data: { seconds: timeOnPage }
            }));
        });

        // Handle funnel actions
        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-funnel-action]');
            if (!action) return;

            const actionType = action.dataset.funnelAction;

            switch (actionType) {
                case 'next':
                    e.preventDefault();
                    if (window.funnelConfig.nextStepUrl) {
                        window.location.href = window.funnelConfig.nextStepUrl;
                    }
                    break;

                case 'add-to-cart':
                    e.preventDefault();
                    const productId = action.dataset.productId;
                    addToCart(productId);
                    break;

                case 'checkout':
                    e.preventDefault();
                    goToCheckout();
                    break;
            }
        });

        // Handle opt-in forms
        document.querySelectorAll('.funnel-optin-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);

                form.classList.add('loading');

                try {
                    const optinUrl = window.funnelConfig.isCustomDomain ? '/optin' : `/f/${window.funnelConfig.funnelSlug}/optin`;
                    const response = await fetch(optinUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': window.funnelConfig.csrfToken,
                        },
                        body: JSON.stringify({
                            email: formData.get('email'),
                            name: formData.get('name'),
                            phone: formData.get('phone'),
                            step_id: window.funnelConfig.stepId,
                        }),
                    });

                    const data = await response.json();

                    if (data.success && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    }
                } catch (error) {
                    console.error('Opt-in error:', error);
                    form.classList.remove('loading');
                }
            });
        });

        // Countdown timer
        document.querySelectorAll('.puck-countdown').forEach(countdown => {
            const endDate = new Date(countdown.dataset.endDate);

            function updateCountdown() {
                const now = new Date();
                const diff = endDate - now;

                if (diff <= 0) {
                    countdown.innerHTML = '<div style="text-align: center; color: inherit;">Offer Expired</div>';
                    return;
                }

                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                const daysEl = countdown.querySelector('.countdown-days');
                const hoursEl = countdown.querySelector('.countdown-hours');
                const minutesEl = countdown.querySelector('.countdown-minutes');
                const secondsEl = countdown.querySelector('.countdown-seconds');

                if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
                if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
                if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        });

        // Exit intent popup
        @if($step->settings['exit_popup'] ?? false)
        let exitPopupShown = false;
        document.addEventListener('mouseout', (e) => {
            if (e.clientY < 0 && !exitPopupShown) {
                document.getElementById('exit-popup').style.display = 'flex';
                exitPopupShown = true;
            }
        });
        @endif

        // Timer redirect (if enabled)
        @if($step->settings['timer_enabled'] ?? false)
        setTimeout(() => {
            if (window.funnelConfig.nextStepUrl) {
                window.location.href = window.funnelConfig.nextStepUrl;
            }
        }, {{ ($step->settings['timer_duration'] ?? 30) * 1000 }});
        @endif

        // Helper functions
        async function addToCart(productId) {
            try {
                const response = await fetch('/api/funnel-cart/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.funnelConfig.csrfToken,
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        funnel_id: window.funnelConfig.funnelId,
                        session_uuid: window.funnelConfig.sessionUuid,
                    }),
                });

                const data = await response.json();
                if (data.success) {
                    // Show success notification or update cart count
                    console.log('Added to cart');
                }
            } catch (error) {
                console.error('Add to cart error:', error);
            }
        }

        function goToCheckout() {
            // Find checkout step
            window.location.href = window.funnelConfig.isCustomDomain ? '/checkout' : `/f/${window.funnelConfig.funnelSlug}/checkout`;
        }

        // Track ANY link clicks on thank you pages
        @if($step->type === 'thankyou')
        let tyClickTracked = false; // Track only once per page session

        document.addEventListener('click', (e) => {
            // Find closest anchor tag (covers clicks on child elements like SVGs, spans, etc.)
            const link = e.target.closest('a[href]');
            if (!link) return;

            // Skip empty hrefs, javascript: links, and anchor-only links
            const href = link.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript:')) return;

            // Only track once per page session (first click counts)
            if (tyClickTracked) return;
            tyClickTracked = true;

            const sessionUuid = link.dataset.sessionUuid || window.funnelConfig.sessionUuid;
            const stepId = link.dataset.stepId || window.funnelConfig.stepId;

            // Send tracking event via beacon with proper JSON content type
            const data = JSON.stringify({
                session_uuid: sessionUuid,
                step_id: stepId,
                button_url: href,
                button_text: link.textContent?.trim().substring(0, 100) || '',
            });
            const blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon('/api/funnel-events/button-click', blob);
        });
        @endif
    </script>

    <!-- Custom JS -->
    @if($customJs)
        <script>{!! $customJs !!}</script>
    @endif

    <!-- Tracking Codes (Body) -->
    @if($funnel->settings['tracking_codes']['body'] ?? null)
        {!! $funnel->settings['tracking_codes']['body'] !!}
    @endif
</body>
</html>
