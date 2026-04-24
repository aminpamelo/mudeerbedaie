<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Thank you &mdash; {{ $campaign->title }}</title>

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
            --rec-muted-2: #A8A29E;
            --rec-border: #E7E2D9;
            --rec-border-2: #EFEAE0;
            --rec-accent: #10B981;
            --rec-accent-soft: #ECFDF5;
            --rec-accent-ink: #047857;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --rec-bg: #0B0A09;
                --rec-surface: #14110F;
                --rec-ink: #F5F2EC;
                --rec-ink-2: #D6D0C5;
                --rec-muted: #A8A29E;
                --rec-muted-2: #78716C;
                --rec-border: #2A2622;
                --rec-border-2: #1F1C19;
                --rec-accent: #34D399;
                --rec-accent-soft: rgba(52,211,153,0.08);
                --rec-accent-ink: #6EE7B7;
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
        .font-serif {
            font-family: 'Instrument Serif', ui-serif, Georgia, serif;
        }

        .grain::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.035;
            z-index: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='a'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' seed='7'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23a)' opacity='0.6'/%3E%3C/svg%3E");
        }

        /* Celebratory hero glow */
        .hero-glow {
            position: relative;
        }
        .hero-glow::before {
            content: '';
            position: absolute;
            left: 50%;
            top: -120px;
            width: 600px;
            height: 600px;
            transform: translateX(-50%);
            background: radial-gradient(circle, color-mix(in oklab, var(--rec-accent) 14%, transparent) 0%, transparent 55%);
            pointer-events: none;
            z-index: -1;
        }

        .check-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 999px;
            background-color: var(--rec-accent-soft);
            border: 1px solid color-mix(in oklab, var(--rec-accent) 30%, transparent);
            color: var(--rec-accent-ink);
            margin: 0 auto 1.75rem;
            position: relative;
        }
        .check-circle svg {
            width: 28px;
            height: 28px;
            stroke-width: 2;
        }
        .check-circle::before {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklab, var(--rec-accent) 15%, transparent);
        }
        .check-circle::after {
            content: '';
            position: absolute;
            inset: -22px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklab, var(--rec-accent) 8%, transparent);
        }

        .reference-card {
            display: inline-flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1.25rem;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 12px;
            text-align: left;
        }
        .reference-card .label {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--rec-muted);
        }
        .reference-card .value {
            font-family: 'Instrument Serif', serif;
            font-size: 20px;
            color: var(--rec-ink);
            letter-spacing: 0.02em;
            margin-top: 2px;
        }

        .timeline {
            padding: 1.5rem 1.5rem;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 16px;
        }
        .timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 0.875rem 0;
            position: relative;
        }
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 15px;
            top: 36px;
            bottom: -8px;
            width: 1px;
            background: linear-gradient(to bottom, var(--rec-border) 0%, var(--rec-border) 70%, transparent 100%);
        }
        .timeline-dot {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            background-color: var(--rec-bg);
            border: 1px solid var(--rec-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Instrument Serif', serif;
            font-size: 14px;
            font-style: italic;
            color: var(--rec-muted);
            position: relative;
            z-index: 1;
        }
        .timeline-step:first-child .timeline-dot {
            background-color: var(--rec-ink);
            border-color: var(--rec-ink);
            color: var(--rec-bg);
        }
        .timeline-content h3 {
            font-size: 14.5px;
            font-weight: 500;
            color: var(--rec-ink);
            letter-spacing: -0.01em;
        }
        .timeline-content p {
            font-size: 13px;
            color: var(--rec-muted);
            margin-top: 2px;
            line-height: 1.55;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.85); }
            70% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }
        .rise { animation: riseIn 600ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
        .rise-1 { animation-delay: 60ms; }
        .rise-2 { animation-delay: 180ms; }
        .rise-3 { animation-delay: 300ms; }
        .rise-4 { animation-delay: 420ms; }
        .pop { animation: popIn 500ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
    </style>
</head>
<body class="grain">
<div class="relative z-10 mx-auto w-full max-w-[640px] px-6 pb-20 pt-16 sm:px-8 sm:pt-28">

    <div class="hero-glow text-center">
        <div class="pop check-circle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>

        <h1 class="rise rise-1 font-serif text-[42px] leading-[1.05] tracking-[-0.02em] sm:text-[56px]" style="color: var(--rec-ink);">
            @if ($applicantName ?? null)
                Thank you,<br>
                <span style="font-style: italic;">{{ trim(explode(' ', (string) $applicantName)[0]) }}</span>.
            @else
                Thank you for <span style="font-style: italic;">applying</span>.
            @endif
        </h1>

        <p class="rise rise-2 mx-auto mt-5 max-w-md text-[15.5px] leading-[1.6]" style="color: var(--rec-ink-2);">
            Your application for <span class="font-serif italic" style="color: var(--rec-ink);">{{ $campaign->title }}</span> is in. Our team will review it and reach out soon.
        </p>

        @if ($applicantNumber ?? null)
            <div class="rise rise-3 mt-8 flex justify-center">
                <div class="reference-card">
                    <div>
                        <div class="label">Reference</div>
                        <div class="value">{{ $applicantNumber }}</div>
                    </div>
                    @if ($applicantEmail ?? null)
                        <div class="h-10 w-px" style="background-color: var(--rec-border-2);"></div>
                        <div>
                            <div class="label">Sent to</div>
                            <div class="text-[13.5px]" style="color: var(--rec-ink-2); margin-top: 2px;">{{ $applicantEmail }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="rise rise-4 mt-12">
        <div class="mb-4 flex items-center gap-3">
            <span class="font-serif text-[18px] italic" style="color: var(--rec-ink-2);">What happens next</span>
            <span class="h-px flex-1" style="background-color: var(--rec-border-2);"></span>
        </div>

        <div class="timeline">
            <div class="timeline-step">
                <div class="timeline-dot">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="timeline-content">
                    <h3>We received your application</h3>
                    <p>Check your inbox for a confirmation — if you don't see it within a few minutes, peek in the spam folder.</p>
                </div>
            </div>
            <div class="timeline-step">
                <div class="timeline-dot">2</div>
                <div class="timeline-content">
                    <h3>We review your details</h3>
                    <p>Our team reads every application. This usually takes a few working days.</p>
                </div>
            </div>
            <div class="timeline-step">
                <div class="timeline-dot">3</div>
                <div class="timeline-content">
                    <h3>We reach out</h3>
                    <p>If it's a fit, we'll email or WhatsApp you to set up the next step.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
