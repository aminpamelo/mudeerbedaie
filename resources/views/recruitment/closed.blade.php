<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Applications closed &mdash; {{ $campaign->title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|instrument-serif:400,400i&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --rec-bg: #FAF7F2;
            --rec-surface: #FFFFFF;
            --rec-ink: #1A1612;
            --rec-ink-2: #3F3A33;
            --rec-muted: #78716C;
            --rec-border: #E7E2D9;
            --rec-border-2: #EFEAE0;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --rec-bg: #0B0A09;
                --rec-surface: #14110F;
                --rec-ink: #F5F2EC;
                --rec-ink-2: #D6D0C5;
                --rec-muted: #A8A29E;
                --rec-border: #2A2622;
                --rec-border-2: #1F1C19;
            }
        }
        html, body {
            background-color: var(--rec-bg);
            color: var(--rec-ink);
        }
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .grain::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='a'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' seed='7'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23a)' opacity='0.6'/%3E%3C/svg%3E");
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 999px;
            font-size: 12px;
            color: var(--rec-muted);
        }
    </style>
</head>
<body class="grain">
<div class="relative z-10 mx-auto flex min-h-screen w-full max-w-[640px] flex-col items-center justify-center px-6 py-16 text-center">
    <div class="pill">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Applications closed
    </div>

    <h1 style="font-family: 'Instrument Serif', serif; color: var(--rec-ink); margin-top: 1.5rem; font-size: 48px; line-height: 1.08; letter-spacing: -0.02em;">
        {{ $campaign->title }}
    </h1>

    <p class="mt-5 max-w-md text-[15.5px] leading-[1.6]" style="color: var(--rec-ink-2);">
        This campaign is no longer accepting applications. Thank you for your interest — we'll share future opportunities as they come up.
    </p>
</div>
</body>
</html>
