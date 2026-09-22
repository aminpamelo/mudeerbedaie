<!DOCTYPE html>
<html lang="ms" class="bg-[#F6F4FC]">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#F6F4FC" />

    <title>{{ $title ?? 'Log Masuk · Portal Pelajar BeDaie' }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    {{-- Geist — same family as the student portal (/my), so the login reads
         as part of the same BeDaie surface rather than a separate page. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="antialiased text-[#1F1B2E]" style="font-family: 'Geist', system-ui, sans-serif;">

    {{-- Soft lavender canvas — mirrors the student portal body wash: two
         faint radial blooms over a #F6F4FC base, with a barely-there
         khatam (8-point star) tile for a gentle Islamic-education warmth. --}}
    <div class="sla-stage relative flex min-h-dvh items-center justify-center p-4 sm:p-8">

        <div class="sla-pattern pointer-events-none absolute inset-0" aria-hidden="true"></div>

        {{-- The card. Max 440px. Clean white surface, hairline violet-tinted
             border, and a soft lavender shadow — same recipe as the portal's
             .glass-card so it feels native to /my. --}}
        <main class="relative z-10 w-full max-w-[440px]">
            <div class="sla-card">
                {{ $slot }}
            </div>

            {{-- Footer outside the card — page-chrome, not form-chrome. --}}
            <div class="mt-6 flex items-center justify-between px-1 text-[11.5px] text-[#8B8798]">
                <span>Bukan pelajar?</span>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-[4px] font-semibold text-[#6D28D9] transition-colors hover:text-[#5B21B6]"
                    wire:navigate
                >
                    <span>Log masuk biasa</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <p class="mt-3 text-center text-[10.5px] text-[#A5A1B4]">
                &copy; {{ now()->format('Y') }} BeDaie · 1 Rumah 1 Daie
            </p>
        </main>
    </div>

    <style>
        :root {
            /* Student-portal tokens — student.css isn't loaded here, so we
               re-declare the few we reference from the Volt component. */
            --color-ink: #1F1B2E;
            --color-ink-2: #413B54;
            --color-muted: #6E6A82;
            --color-line: #ECE9F4;
            --color-brand-soft: #F3F0FE;
        }

        body {
            background:
                radial-gradient(1100px 560px at 100% -12%, #ECE6FD 0%, transparent 55%),
                radial-gradient(820px 520px at -12% 112%, #F4ECFA 0%, transparent 52%),
                #F6F4FC;
            background-attachment: fixed;
            min-height: 100dvh;
        }

        .sla-stage {
            background:
                radial-gradient(1000px 640px at 50% -12%, rgba(139, 92, 246, 0.10), transparent 58%),
                radial-gradient(760px 620px at 8% 108%, rgba(244, 63, 94, 0.06), transparent 55%);
        }

        /* Faint khatam tile — Islamic 8-point star, extremely low opacity so
           it's texture, not decoration. Inline SVG, no extra request. */
        .sla-pattern {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48'%3E%3Cpath fill='none' stroke='%237C3AED' stroke-width='1' stroke-opacity='0.10' d='M24 6l4.9 8.2 9.3 2.1-6.3 7.1 0.9 9.5L24 39l-8.7 3-0.9-9.5-6.3-7.1 9.3-2.1z'/%3E%3C/svg%3E");
            background-size: 48px 48px;
            mask-image: radial-gradient(80% 80% at 50% 40%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(80% 80% at 50% 40%, #000 0%, transparent 78%);
        }

        /* White card — the portal's .glass-card recipe. */
        .sla-card {
            position: relative;
            border-radius: 28px;
            padding: 36px 28px 32px;
            background: #FFFFFF;
            border: 1px solid var(--color-line);
            box-shadow:
                0 1px 2px rgba(31, 27, 46, 0.04),
                0 24px 60px -28px rgba(90, 45, 165, 0.32),
                0 8px 24px -16px rgba(90, 45, 165, 0.18);
        }

        /* Floating-label inputs — light variant. */
        .sla-field {
            position: relative;
        }

        .sla-field-input {
            width: 100%;
            padding: 22px 16px 10px 16px;
            background: #FBFAFE;
            border: 1px solid var(--color-line);
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.005em;
            color: var(--color-ink);
            transition: border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
            -webkit-tap-highlight-color: transparent;
        }

        .sla-field-input::placeholder {
            color: transparent; /* Floating label is the placeholder */
        }

        .sla-field-input:hover {
            border-color: #DAD4EC;
        }

        .sla-field-input:focus {
            outline: none;
            border-color: #A78BFA;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.14);
        }

        .sla-field-label {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            font-weight: 500;
            color: var(--color-muted);
            pointer-events: none;
            transition: top 160ms ease, font-size 160ms ease, color 160ms ease, transform 160ms ease;
            background: transparent;
        }

        .sla-field-input:focus ~ .sla-field-label,
        .sla-field-input:not(:placeholder-shown) ~ .sla-field-label {
            top: 10px;
            transform: translateY(0);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #8B8798;
            text-transform: uppercase;
        }

        .sla-field-input:focus ~ .sla-field-label {
            color: #7C3AED;
        }

        /* Rounded checkbox. */
        .sla-check {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 1.5px solid #D5CFEA;
            border-radius: 6px;
            background: #FFFFFF;
            cursor: pointer;
            position: relative;
            transition: border-color 140ms ease, background 140ms ease;
            flex-shrink: 0;
        }

        .sla-check:hover {
            border-color: #A78BFA;
        }

        .sla-check:checked {
            border-color: transparent;
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
        }

        .sla-check:checked::after {
            content: "";
            position: absolute;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2.5px 2.5px 0;
            transform: rotate(45deg);
        }

        /* Primary CTA — BeDaie brand gradient pill. */
        .sla-cta {
            position: relative;
            width: 100%;
            padding: 16px 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 48%, #6D28D9 100%);
            color: #fff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.01em;
            cursor: pointer;
            overflow: hidden;
            transition: transform 140ms ease, box-shadow 240ms ease;
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.22) inset,
                0 12px 30px -8px rgba(124, 58, 237, 0.45),
                0 4px 12px -4px rgba(124, 58, 237, 0.30);
        }

        .sla-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 35%, rgba(255, 255, 255, 0.30) 50%, transparent 65%);
            transform: translateX(-100%);
            transition: transform 700ms ease;
        }

        .sla-cta:hover {
            transform: translateY(-1px);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.28) inset,
                0 16px 34px -8px rgba(124, 58, 237, 0.55),
                0 6px 14px -4px rgba(124, 58, 237, 0.40);
        }

        .sla-cta:hover::before {
            transform: translateX(100%);
        }

        .sla-cta:active {
            transform: translateY(0) scale(0.992);
        }

        .sla-cta:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* Error pill — sits just under the input. */
        .sla-error {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 6px 10px;
            background: #FFF1F3;
            border: 1px solid rgba(244, 63, 94, 0.30);
            color: #BE123C;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.4;
        }

        .sla-pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            padding: 6px 10px;
            background: #F3F0FE;
            border: 0;
            border-radius: 8px;
            color: #6D28D9;
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background 140ms ease, color 140ms ease;
        }

        .sla-pw-toggle:hover {
            background: #E9E3FD;
            color: #5B21B6;
        }

        /* Portal Pelajar pill at top of card. */
        .sla-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 11px 5px 9px;
            border-radius: 999px;
            background: #F3F0FE;
            border: 1px solid rgba(124, 58, 237, 0.18);
            color: #6D28D9;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.10em;
            text-transform: uppercase;
        }

        /* Forgot-password link. */
        .sla-link {
            font-size: 12.5px;
            font-weight: 600;
            color: #7C3AED;
            transition: color 140ms ease;
        }

        .sla-link:hover {
            color: #5B21B6;
        }

        /* Staggered fade-up on card mount. */
        .sla-card > * {
            opacity: 0;
            transform: translateY(8px);
            animation: sla-in 520ms cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        .sla-card > *:nth-child(1) { animation-delay: 50ms; }
        .sla-card > *:nth-child(2) { animation-delay: 120ms; }
        .sla-card > *:nth-child(3) { animation-delay: 190ms; }
        .sla-card > *:nth-child(4) { animation-delay: 260ms; }
        .sla-card > *:nth-child(5) { animation-delay: 320ms; }
        .sla-card > *:nth-child(6) { animation-delay: 380ms; }

        @keyframes sla-in {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sla-card > * {
                opacity: 1;
                transform: none;
                animation: none;
            }
            .sla-cta::before {
                display: none;
            }
        }
    </style>

    @livewireScripts
    @fluxScripts
</body>
</html>
