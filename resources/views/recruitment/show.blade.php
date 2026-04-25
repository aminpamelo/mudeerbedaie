<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>{{ $campaign->title }} &mdash; Apply</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=geist:400,500,600,700,800|geist-mono:500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --rec-bg: #07070A;
            --rec-bg-2: #0D0D12;
            --rec-surface: rgba(255, 255, 255, 0.025);
            --rec-surface-strong: rgba(255, 255, 255, 0.04);
            --rec-ink: #FAFAFA;
            --rec-ink-2: #E5E5E7;
            --rec-muted: #A1A1AA;
            --rec-muted-2: #71717A;
            --rec-muted-3: #52525B;
            --rec-border: rgba(255, 255, 255, 0.08);
            --rec-border-2: rgba(255, 255, 255, 0.05);
            --rec-border-strong: rgba(255, 255, 255, 0.12);

            --rec-emerald: #10B981;
            --rec-emerald-bright: #34D399;
            --rec-emerald-glow: rgba(52, 211, 153, 0.45);
            --rec-emerald-soft: rgba(52, 211, 153, 0.10);
            --rec-cyan: #06B6D4;
            --rec-violet: #8B5CF6;

            --rec-danger: #FB7185;
            --rec-danger-soft: rgba(251, 113, 133, 0.10);
        }

        * { box-sizing: border-box; }

        html, body {
            background-color: var(--rec-bg);
            color: var(--rec-ink);
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-feature-settings: "ss01", "cv11";
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }

        /* === Aurora background === */
        .aurora {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .aurora::before,
        .aurora::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
        }
        .aurora::before {
            top: -20%;
            left: -15%;
            width: 70vw;
            height: 70vw;
            max-width: 900px;
            max-height: 900px;
            background: radial-gradient(circle, var(--rec-emerald) 0%, rgba(16, 185, 129, 0) 60%);
            opacity: 0.18;
            animation: drift-1 22s ease-in-out infinite;
        }
        .aurora::after {
            top: -10%;
            right: -20%;
            width: 75vw;
            height: 75vw;
            max-width: 950px;
            max-height: 950px;
            background: radial-gradient(circle, var(--rec-violet) 0%, rgba(139, 92, 246, 0) 60%);
            opacity: 0.18;
            animation: drift-2 28s ease-in-out infinite;
        }
        .aurora-extra {
            position: fixed;
            top: 30%;
            left: 50%;
            transform: translate(-50%, 0);
            width: 80vw;
            height: 50vw;
            max-width: 1100px;
            max-height: 700px;
            background: radial-gradient(ellipse, var(--rec-cyan) 0%, rgba(6, 182, 212, 0) 65%);
            filter: blur(110px);
            opacity: 0.10;
            pointer-events: none;
            z-index: 0;
            animation: drift-3 30s ease-in-out infinite;
        }
        @keyframes drift-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(40px, 30px) scale(1.05); }
        }
        @keyframes drift-2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-50px, 40px) scale(1.08); }
        }
        @keyframes drift-3 {
            0%, 100% { transform: translate(-50%, 0) scale(1); }
            50% { transform: translate(-48%, 30px) scale(1.04); }
        }

        /* Subtle grid overlay */
        .grid-overlay {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
        }

        /* Subtle grain texture */
        .grain {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            opacity: 0.04;
            mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='a'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' seed='5'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23a)'/%3E%3C/svg%3E");
        }

        /* === Hero === */
        .hero {
            position: relative;
            text-align: center;
            padding-top: 5rem;
            padding-bottom: 3.5rem;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4375rem 0.875rem;
            background: rgba(16, 185, 129, 0.10);
            border: 1px solid rgba(52, 211, 153, 0.30);
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            color: var(--rec-emerald-bright);
            letter-spacing: 0.02em;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .live-pill .dot {
            position: relative;
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: var(--rec-emerald-bright);
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.20);
        }
        .live-pill .dot::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 9999px;
            background: var(--rec-emerald-bright);
            opacity: 0.4;
            animation: live-pulse 1.8s ease-in-out infinite;
        }
        @keyframes live-pulse {
            0%, 100% { transform: scale(0.6); opacity: 0.5; }
            50% { transform: scale(2.5); opacity: 0; }
        }

        .hero h1 {
            font-size: clamp(38px, 6.5vw, 72px);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -0.04em;
            margin: 1.75rem auto 0;
            max-width: 800px;
            color: var(--rec-ink);
        }
        .hero h1 .accent {
            background: linear-gradient(135deg, var(--rec-emerald-bright) 0%, var(--rec-cyan) 60%, var(--rec-violet) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            position: relative;
        }

        .hero p {
            margin: 1.5rem auto 0;
            max-width: 580px;
            font-size: 16px;
            line-height: 1.6;
            color: var(--rec-muted);
        }

        .meta-row {
            margin-top: 1.75rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4375rem;
            padding: 0.375rem 0.75rem;
            background: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 9999px;
            font-size: 12.5px;
            color: var(--rec-muted);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .meta-pill svg { color: var(--rec-emerald-bright); }

        /* === Form card === */
        .form-card {
            position: relative;
            background: var(--rec-surface-strong);
            border: 1px solid var(--rec-border);
            border-radius: 24px;
            padding: 2.25rem 1.875rem;
            backdrop-filter: blur(40px) saturate(140%);
            -webkit-backdrop-filter: blur(40px) saturate(140%);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.05) inset,
                0 30px 60px -20px rgba(0, 0, 0, 0.5),
                0 8px 24px -8px rgba(0, 0, 0, 0.3);
        }
        @media (min-width: 640px) {
            .form-card { padding: 3rem 2.75rem; }
        }
        .form-card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--rec-emerald-bright), transparent);
            opacity: 0.5;
        }

        /* === Step section === */
        .step-section {
            margin-bottom: 2.75rem;
        }
        .step-section:last-of-type {
            margin-bottom: 0;
        }
        .step-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.12), rgba(139, 92, 246, 0.12));
            border: 1px solid var(--rec-border-strong);
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 13px;
            font-weight: 500;
            color: var(--rec-emerald-bright);
            letter-spacing: -0.02em;
        }
        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--rec-ink);
            letter-spacing: -0.015em;
        }

        /* === Section label legacy class (used by some partials) === */
        .section-label {
            /* no-op — we use step-section now */
            display: none;
        }
        .section-label-text { display: none; }

        /* === Field label === */
        .field-label {
            display: inline-flex;
            align-items: center;
            gap: 0.4375rem;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--rec-ink-2);
            margin-bottom: 0.625rem;
            letter-spacing: -0.005em;
        }
        .required-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            background-color: var(--rec-emerald-bright);
            box-shadow: 0 0 6px rgba(52, 211, 153, 0.6);
        }

        /* === Inputs === */
        .input-base {
            width: 100%;
            padding: 0.8125rem 1rem;
            font-size: 14.5px;
            line-height: 1.4;
            color: var(--rec-ink);
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--rec-border);
            border-radius: 12px;
            transition: all 180ms cubic-bezier(0.2, 0.8, 0.2, 1);
            font-family: inherit;
            font-feature-settings: inherit;
        }
        .input-base::placeholder {
            color: var(--rec-muted-3);
        }
        .input-base:hover {
            background: rgba(255, 255, 255, 0.035);
            border-color: var(--rec-border-strong);
        }
        .input-base:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--rec-emerald-bright);
            box-shadow:
                0 0 0 4px rgba(52, 211, 153, 0.12),
                0 0 24px -4px var(--rec-emerald-glow);
        }
        textarea.input-base {
            resize: vertical;
            min-height: 116px;
            font-family: inherit;
        }
        select.input-base {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath stroke='%23A1A1AA' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round' d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            padding-right: 2.5rem;
        }
        input[type="date"].input-base,
        input[type="datetime-local"].input-base {
            color-scheme: dark;
        }

        .hint-text {
            font-size: 12.5px;
            color: var(--rec-muted-2);
            margin-top: 0.4375rem;
            line-height: 1.5;
        }

        /* === Platform / choice cards === */
        .platform-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border: 1px solid var(--rec-border);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            cursor: pointer;
            transition: all 180ms cubic-bezier(0.2, 0.8, 0.2, 1);
            user-select: none;
            color: var(--rec-ink-2);
        }
        .platform-option:hover {
            border-color: var(--rec-border-strong);
            background: rgba(255, 255, 255, 0.035);
            transform: translateY(-1px);
        }
        .platform-option input[type="checkbox"],
        .platform-option input[type="radio"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--rec-border-strong);
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            transition: all 180ms ease;
        }
        .platform-option input[type="checkbox"] {
            border-radius: 5px;
        }
        .platform-option input[type="radio"] {
            border-radius: 9999px;
        }
        .platform-option input[type="checkbox"]:checked,
        .platform-option input[type="radio"]:checked {
            background: var(--rec-emerald-bright);
            border-color: var(--rec-emerald-bright);
            box-shadow: 0 0 12px var(--rec-emerald-glow);
        }
        .platform-option input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 9px;
            border: solid #07070A;
            border-width: 0 2.25px 2.25px 0;
            transform: rotate(45deg);
        }
        .platform-option input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            inset: 4px;
            border-radius: 9999px;
            background: #07070A;
        }
        .platform-option:has(input:checked) {
            border-color: var(--rec-emerald-bright);
            background: rgba(52, 211, 153, 0.08);
            box-shadow:
                0 0 0 1px var(--rec-emerald-bright),
                0 8px 24px -8px var(--rec-emerald-glow);
        }
        .platform-label {
            font-size: 14.5px;
            font-weight: 500;
            color: var(--rec-ink);
        }

        /* === File drop === */
        .file-drop {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 1.125rem 1.125rem;
            border: 1.5px dashed var(--rec-border-strong);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.015);
            cursor: pointer;
            transition: all 180ms cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .file-drop:hover {
            border-color: var(--rec-emerald-bright);
            background: rgba(52, 211, 153, 0.04);
        }
        .file-drop-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.18), rgba(139, 92, 246, 0.18));
            border: 1px solid var(--rec-border-strong);
            color: var(--rec-emerald-bright);
            flex-shrink: 0;
        }
        .file-drop-text {
            font-size: 14px;
            color: var(--rec-ink);
            font-weight: 500;
        }
        .file-drop-hint {
            font-size: 12px;
            color: var(--rec-muted-2);
            margin-top: 2px;
        }

        /* === Submit button === */
        .btn-submit {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--rec-emerald-bright) 0%, #14B8A6 50%, var(--rec-cyan) 100%);
            color: #07070A;
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 200ms cubic-bezier(0.2, 0.8, 0.2, 1);
            letter-spacing: -0.01em;
            box-shadow:
                0 0 0 1px rgba(52, 211, 153, 0.4),
                0 12px 32px -8px var(--rec-emerald-glow),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
            transition: left 600ms ease;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow:
                0 0 0 1px rgba(52, 211, 153, 0.5),
                0 16px 40px -8px var(--rec-emerald-glow),
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }
        .btn-submit:hover::before {
            left: 100%;
        }
        .btn-submit:active {
            transform: translateY(0);
        }

        /* === Error block === */
        .error-block {
            padding: 1rem 1.125rem;
            background: var(--rec-danger-soft);
            border: 1px solid rgba(251, 113, 133, 0.25);
            border-radius: 12px;
            color: var(--rec-danger);
            font-size: 13.5px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* === Footer note === */
        .footer-note {
            margin-top: 1rem;
            text-align: center;
            font-size: 12px;
            color: var(--rec-muted-2);
            line-height: 1.5;
        }

        /* === Avatar stack === */
        .avatar-stack {
            display: inline-flex;
            margin-top: 1.5rem;
            align-items: center;
            gap: 0.625rem;
        }
        .avatar-stack-imgs {
            display: flex;
        }
        .avatar-stack-imgs > div {
            width: 26px;
            height: 26px;
            border-radius: 9999px;
            border: 2px solid var(--rec-bg);
            margin-left: -8px;
        }
        .avatar-stack-imgs > div:first-child {
            margin-left: 0;
            background: linear-gradient(135deg, #34D399, #10B981);
        }
        .avatar-stack-imgs > div:nth-child(2) { background: linear-gradient(135deg, #A78BFA, #8B5CF6); }
        .avatar-stack-imgs > div:nth-child(3) { background: linear-gradient(135deg, #F472B6, #EC4899); }
        .avatar-stack-imgs > div:nth-child(4) { background: linear-gradient(135deg, #FBBF24, #F59E0B); }
        .avatar-stack-text {
            font-size: 13px;
            color: var(--rec-muted);
        }

        /* === Animations === */
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rise { animation: riseIn 700ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
        .rise-1 { animation-delay: 80ms; }
        .rise-2 { animation-delay: 200ms; }
        .rise-3 { animation-delay: 320ms; }
        .rise-4 { animation-delay: 440ms; }
        .rise-5 { animation-delay: 560ms; }

        /* Light mode override - if user explicitly prefers light, soften the dark */
        @media (prefers-color-scheme: light) {
            /* We deliberately stay dark — this form is meant to feel like a live broadcast */
        }
    </style>
</head>
<body>
    <div class="aurora"></div>
    <div class="aurora-extra"></div>
    <div class="grid-overlay"></div>
    <div class="grain"></div>

    <div style="position: relative; z-index: 10; width: 100%; max-width: 720px; margin: 0 auto; padding: 0 1.5rem 5rem;">

        {{-- HERO --}}
        <header class="hero">
            <div class="rise rise-1">
                <span class="live-pill">
                    <span class="dot"></span>
                    Now hiring
                </span>
            </div>

            <h1 class="rise rise-2">
                {{ $campaign->title }}
            </h1>

            @if (! empty($campaign->description))
                <p class="rise rise-3" style="white-space: pre-wrap;">{{ $campaign->description }}</p>
            @endif

            <div class="meta-row rise rise-4">
                @if ($campaign->closes_at)
                    <span class="meta-pill">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Closes {{ $campaign->closes_at->toFormattedDateString() }}
                    </span>
                @endif
                <span class="meta-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    ~5 minutes
                </span>
                <span class="meta-pill">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                    Live commerce
                </span>
            </div>

            <div class="avatar-stack rise rise-5">
                <div class="avatar-stack-imgs">
                    <div></div><div></div><div></div><div></div>
                </div>
                <span class="avatar-stack-text">Join our growing team of live hosts</span>
            </div>
        </header>

        {{-- FORM CARD --}}
        <div class="rise rise-5">

            @if ($errors->any())
                <div class="error-block" style="margin-bottom: 1.25rem;">
                    <div style="font-weight: 500; margin-bottom: 0.25rem;">Please fix the following:</div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 13px; line-height: 1.5;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('recruitment.apply', $campaign->slug) }}"
                  enctype="multipart/form-data"
                  class="form-card">
                @csrf

                @foreach (($campaign->form_schema['pages'] ?? []) as $page)
                    <section class="step-section">
                        <div class="step-header">
                            <div class="step-number">{{ sprintf('%02d', $loop->iteration) }}</div>
                            <div class="step-title">{{ $page['title'] }}</div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 1.125rem;">
                            @foreach (($page['fields'] ?? []) as $field)
                                @include("recruitment.fields.{$field['type']}", ['field' => $field])
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div style="margin-top: 2.25rem; padding-top: 1.75rem; border-top: 1px solid var(--rec-border-2);">
                    <button type="submit" class="btn-submit">
                        Submit application
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                    <p class="footer-note">
                        By submitting you agree we may contact you about this role.<br>
                        We handle your info with care.
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
