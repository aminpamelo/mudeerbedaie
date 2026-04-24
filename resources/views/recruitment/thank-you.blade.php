<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Thank you &mdash; {{ $campaign->title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700&family=geist-mono:500&display=swap" rel="stylesheet" />

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
            --rec-border-2: #F0F0F0;
            --rec-accent: #10B981;
            --rec-accent-ink: #047857;
            --rec-accent-soft: #ECFDF5;
            --rec-accent-ring: rgba(16,185,129,0.14);
            --rec-shadow: 0 1px 2px rgba(10,10,10,0.04), 0 4px 16px -2px rgba(10,10,10,0.06);
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
                --rec-border-2: #1C1C1C;
                --rec-accent: #34D399;
                --rec-accent-ink: #6EE7B7;
                --rec-accent-soft: rgba(52,211,153,0.10);
                --rec-accent-ring: rgba(52,211,153,0.16);
                --rec-shadow: 0 1px 2px rgba(0,0,0,0.5), 0 4px 16px -2px rgba(0,0,0,0.4);
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
            overflow-x: hidden;
        }

        .glow {
            position: fixed;
            pointer-events: none;
            z-index: 0;
            inset: 0;
            overflow: hidden;
        }
        .glow::before {
            content: '';
            position: absolute;
            left: 50%;
            top: -100px;
            width: 720px;
            height: 720px;
            transform: translateX(-50%);
            border-radius: 9999px;
            background: radial-gradient(circle, var(--rec-accent-ring) 0%, transparent 60%);
            filter: blur(20px);
        }
        @media (prefers-color-scheme: dark) {
            .glow::before { opacity: 0.7; }
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

        .check-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--rec-accent) 0%, var(--rec-accent-ink) 100%);
            color: white;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 8px 24px -6px var(--rec-accent-ring), 0 4px 12px -2px rgba(16,185,129,0.3);
        }
        .check-circle::before,
        .check-circle::after {
            content: '';
            position: absolute;
            border-radius: 9999px;
            border: 1px solid color-mix(in oklab, var(--rec-accent) 18%, transparent);
        }
        .check-circle::before { inset: -14px; }
        .check-circle::after { inset: -28px; border-color: color-mix(in oklab, var(--rec-accent) 8%, transparent); }
        .check-circle svg {
            width: 32px;
            height: 32px;
            stroke-width: 2.5;
            position: relative;
            z-index: 1;
        }

        .card {
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 16px;
            box-shadow: var(--rec-shadow);
        }

        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid var(--rec-border-2);
        }
        .meta-cell {
            padding: 0.875rem 1.125rem;
        }
        .meta-cell + .meta-cell {
            border-left: 1px solid var(--rec-border-2);
        }
        .meta-label {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--rec-muted-2);
            margin-bottom: 3px;
        }
        .meta-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--rec-ink);
            font-feature-settings: "ss01";
        }
        .meta-value.mono {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 13.5px;
        }

        .timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 0.9375rem;
            padding: 0.75rem 0;
            position: relative;
        }
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 14px;
            top: 36px;
            bottom: -4px;
            width: 1px;
            background: linear-gradient(to bottom, var(--rec-border), transparent);
        }
        .timeline-dot {
            position: relative;
            z-index: 1;
            width: 28px;
            height: 28px;
            border-radius: 9999px;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            color: var(--rec-muted-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .timeline-step.done .timeline-dot {
            background: linear-gradient(135deg, var(--rec-accent), var(--rec-accent-ink));
            border-color: var(--rec-accent);
            color: white;
        }
        .timeline-content h3 {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--rec-ink);
            letter-spacing: -0.01em;
        }
        .timeline-content p {
            font-size: 13px;
            color: var(--rec-muted);
            margin-top: 3px;
            line-height: 1.55;
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 1rem;
        }
        .section-label-text {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--rec-muted-2);
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--rec-border-2);
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            0% { opacity: 0; transform: scale(0.7); }
            70% { opacity: 1; transform: scale(1.06); }
            100% { opacity: 1; transform: scale(1); }
        }
        .rise { animation: riseIn 600ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
        .rise-1 { animation-delay: 60ms; }
        .rise-2 { animation-delay: 160ms; }
        .rise-3 { animation-delay: 280ms; }
        .rise-4 { animation-delay: 400ms; }
        .pop { animation: popIn 550ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
    </style>
</head>
<body>
<div class="glow"></div>
<div class="dots"></div>

<div class="relative z-10 mx-auto w-full max-w-[600px] px-6 pb-20 pt-20 text-center sm:px-8 sm:pt-28">

    <div class="pop check-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
    </div>

    <h1 class="rise rise-1 mt-8 text-[36px] font-semibold leading-[1.1] tracking-[-0.03em] sm:text-[44px]" style="color: var(--rec-ink);">
        @if ($applicantName ?? null)
            Thanks, {{ trim(explode(' ', (string) $applicantName)[0]) }}.
        @else
            You're in.
        @endif
    </h1>

    <p class="rise rise-2 mx-auto mt-4 max-w-md text-[15.5px] leading-[1.6]" style="color: var(--rec-muted);">
        Your application for <span class="font-medium" style="color: var(--rec-ink-2);">{{ $campaign->title }}</span> is in. Our team will review it and reach out soon.
    </p>

    @if (($applicantNumber ?? null) || ($applicantEmail ?? null))
        <div class="rise rise-3 mt-8">
            <div class="card overflow-hidden">
                <div class="px-5 pt-4 pb-3 text-left">
                    <div class="text-[10.5px] font-semibold uppercase tracking-[0.12em]" style="color: var(--rec-muted-2);">Application summary</div>
                </div>
                <div class="meta-row">
                    @if ($applicantNumber ?? null)
                        <div class="meta-cell text-left">
                            <div class="meta-label">Reference</div>
                            <div class="meta-value mono">{{ $applicantNumber }}</div>
                        </div>
                    @endif
                    @if ($applicantEmail ?? null)
                        <div class="meta-cell text-left">
                            <div class="meta-label">Sent to</div>
                            <div class="meta-value">{{ $applicantEmail }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="rise rise-4 mt-10 text-left">
        <div class="section-label">
            <span class="section-label-text">What happens next</span>
        </div>

        <div class="card px-5 py-3">
            <div class="timeline-step done">
                <div class="timeline-dot">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="timeline-content">
                    <h3>Application received</h3>
                    <p>Check your inbox for a confirmation — if you don't see it in a few minutes, peek in the spam folder.</p>
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
