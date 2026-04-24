<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Applications closed &mdash; {{ $campaign->title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --rec-bg: #FAFAFA;
            --rec-surface: #FFFFFF;
            --rec-ink: #0A0A0A;
            --rec-ink-2: #262626;
            --rec-muted: #525252;
            --rec-muted-2: #737373;
            --rec-border: #E5E5E5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --rec-bg: #0A0A0A;
                --rec-surface: #121212;
                --rec-ink: #FAFAFA;
                --rec-ink-2: #D4D4D4;
                --rec-muted: #A3A3A3;
                --rec-muted-2: #737373;
                --rec-border: #262626;
            }
        }
        html, body {
            background-color: var(--rec-bg);
            color: var(--rec-ink);
        }
        body {
            font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-feature-settings: "ss01", "cv11";
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .dots {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: radial-gradient(color-mix(in oklab, var(--rec-ink) 6%, transparent) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: radial-gradient(ellipse at top, black 0%, transparent 55%);
            opacity: 0.4;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.875rem;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--rec-muted);
        }
    </style>
</head>
<body>
<div class="dots"></div>
<div class="relative z-10 mx-auto flex min-h-screen w-full max-w-[600px] flex-col items-center justify-center px-6 py-16 text-center">
    <span class="pill">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
        Applications closed
    </span>
    <h1 class="mt-6 text-[40px] font-semibold leading-[1.08] tracking-[-0.03em] sm:text-[48px]" style="color: var(--rec-ink);">
        {{ $campaign->title }}
    </h1>
    <p class="mt-4 max-w-md text-[15.5px] leading-[1.6]" style="color: var(--rec-muted);">
        This campaign is no longer accepting applications. Thank you for your interest — we'll share new opportunities as they come up.
    </p>
</div>
</body>
</html>
