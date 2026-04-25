<!DOCTYPE html>
<html lang="ms" class="bg-[#0A0A0F]">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#0A0A0F" />

    <title>{{ $title ?? 'Log Masuk · Hos Siaran Langsung' }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    {{-- Geist (sans + mono). Same family as the PIC desk so the host
         experience reads as part of the same modern, official Mudeer
         Bedaie surface rather than a separate consumer-app aesthetic. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@100..900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="antialiased text-white" style="font-family: 'Geist', system-ui, sans-serif;">

    {{-- Full-bleed aurora backdrop. Dark base (#0A0A0F), then three warm
         radial blobs layered in — a violet, a pink, and a teal accent.
         They don't move on load (premium apps tend to avoid "wow" motion
         on auth screens) but the layered softness gives the canvas depth. --}}
    <div class="lha-stage relative flex min-h-screen items-center justify-center p-4 sm:p-8">

        <div class="lha-aurora pointer-events-none absolute inset-0" aria-hidden="true"></div>
        <div class="lha-grain pointer-events-none absolute inset-0 mix-blend-soft-light opacity-[0.22]" aria-hidden="true"></div>

        {{-- The card. Max 440px. Glass-morphism-adjacent: high-contrast
             white surface with a razor-thin inner hairline and a soft big
             shadow below. Lands a little above centre so it feels airy
             rather than vertically crammed. --}}
        <main class="relative z-10 w-full max-w-[440px]">
            <div class="lha-card">
                {{ $slot }}
            </div>

            {{-- Soft footer outside the card so it reads as page-chrome,
                 not form-chrome. --}}
            <div class="mt-6 flex items-center justify-between px-1 text-[11.5px] text-white/50">
                <span>Bukan hos siaran?</span>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-[4px] font-semibold text-white/80 transition-colors hover:text-white"
                    wire:navigate
                >
                    <span>Log masuk biasa</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <p class="mt-3 text-center text-[10.5px] text-white/30">
                &copy; {{ now()->format('Y') }} Live Host Desk
            </p>
        </main>
    </div>

    <style>
        /* Lock body to dark base so the aurora reads as glow, not as a
           gradient laid on a white canvas. */
        body {
            background: #0A0A0F;
        }

        .lha-stage {
            background:
                radial-gradient(1200px 800px at 50% -10%, rgba(124, 58, 237, 0.28), transparent 60%),
                radial-gradient(900px 700px at 10% 110%, rgba(225, 29, 72, 0.26), transparent 58%),
                radial-gradient(800px 700px at 100% 80%, rgba(37, 99, 235, 0.22), transparent 60%),
                #0A0A0F;
        }

        .lha-aurora {
            /* A second pass of soft conical light — gives the gradient
               blobs a mild edge-shimmer so it doesn't feel pastel-flat. */
            background:
                radial-gradient(600px 400px at 80% 10%, rgba(245, 158, 11, 0.12), transparent 60%),
                radial-gradient(500px 500px at 20% 70%, rgba(124, 58, 237, 0.18), transparent 55%);
            filter: blur(30px);
        }

        .lha-grain {
            /* Subtle noise so the smooth gradients don't band on wide
               screens. Uses an inline SVG turbulence pattern — no extra
               asset request. */
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 0.6 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");
        }

        /* The glass card. Not truly transparent — a solid dark-ink surface
           with a subtle inner border + outer glow. Keeps legibility high
           on varied backdrops. */
        .lha-card {
            position: relative;
            border-radius: 28px;
            padding: 36px 28px 32px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%),
                rgba(18, 16, 24, 0.72);
            backdrop-filter: blur(32px) saturate(160%);
            -webkit-backdrop-filter: blur(32px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.06) inset,
                0 30px 60px -20px rgba(0, 0, 0, 0.55),
                0 10px 25px -10px rgba(124, 58, 237, 0.20);
        }

        /* Floating-label inputs — modern app pattern. Label sits inside
           the field outline and slides up when the field is focused or
           has a value. */
        .lha-field {
            position: relative;
        }

        .lha-field-input {
            width: 100%;
            padding: 22px 16px 10px 16px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.005em;
            color: #fff;
            transition: border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
            -webkit-tap-highlight-color: transparent;
        }

        .lha-field-input::placeholder {
            color: transparent; /* Floating label is the placeholder */
        }

        .lha-field-input:hover {
            border-color: rgba(255, 255, 255, 0.18);
        }

        .lha-field-input:focus {
            outline: none;
            border-color: #A78BFA;
            background: rgba(124, 58, 237, 0.08);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.18);
        }

        .lha-field-label {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.5);
            pointer-events: none;
            transition: top 160ms ease, font-size 160ms ease, color 160ms ease, transform 160ms ease;
            background: transparent;
        }

        .lha-field-input:focus ~ .lha-field-label,
        .lha-field-input:not(:placeholder-shown) ~ .lha-field-label,
        .lha-field-label.is-active {
            top: 10px;
            transform: translateY(0);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.55);
            text-transform: uppercase;
        }

        .lha-field-input:focus ~ .lha-field-label {
            color: #C4B5FD;
        }

        /* Custom rounded checkbox to match app-store-era mobile apps. */
        .lha-check {
            appearance: none;
            width: 20px;
            height: 20px;
            border: 1.5px solid rgba(255, 255, 255, 0.22);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.04);
            cursor: pointer;
            position: relative;
            transition: border-color 140ms ease, background 140ms ease;
            flex-shrink: 0;
        }

        .lha-check:hover {
            border-color: rgba(255, 255, 255, 0.40);
        }

        .lha-check:checked {
            border-color: transparent;
            background:
                linear-gradient(135deg, #A78BFA 0%, #7C3AED 100%);
        }

        .lha-check:checked::after {
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

        /* Primary CTA — gradient pill with lift. Follows modern app
           conventions (Revolut/Grab/Stripe checkout). */
        .lha-cta {
            position: relative;
            width: 100%;
            padding: 16px 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #9F7AEA 0%, #7C3AED 55%, #6D28D9 100%);
            color: #fff;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.01em;
            cursor: pointer;
            overflow: hidden;
            transition: transform 140ms ease, box-shadow 240ms ease;
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.20) inset,
                0 12px 30px -8px rgba(124, 58, 237, 0.55),
                0 4px 12px -4px rgba(124, 58, 237, 0.40);
        }

        .lha-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 35%, rgba(255, 255, 255, 0.28) 50%, transparent 65%);
            transform: translateX(-100%);
            transition: transform 700ms ease;
        }

        .lha-cta:hover {
            transform: translateY(-1px);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.25) inset,
                0 16px 34px -8px rgba(124, 58, 237, 0.65),
                0 6px 14px -4px rgba(124, 58, 237, 0.50);
        }

        .lha-cta:hover::before {
            transform: translateX(100%);
        }

        .lha-cta:active {
            transform: translateY(0) scale(0.992);
        }

        .lha-cta:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* Friendly red pill for errors — lives just under the input. */
        .lha-error {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 6px 10px;
            background: rgba(225, 29, 72, 0.12);
            border: 1px solid rgba(225, 29, 72, 0.35);
            color: #FDA4AF;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.4;
        }

        .lha-pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            padding: 6px 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 0;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.72);
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: background 140ms ease, color 140ms ease;
        }

        .lha-pw-toggle:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }

        /* LIVE pill at top of card — soft chip indicator. */
        .lha-live {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 10px 5px 8px;
            border-radius: 999px;
            background: rgba(225, 29, 72, 0.16);
            border: 1px solid rgba(225, 29, 72, 0.30);
            color: #FECDD3;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .lha-live-diode {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #F43F5E;
            box-shadow: 0 0 8px rgba(244, 63, 94, 0.9);
            animation: lha-pulse 1.5s ease-out infinite;
        }

        @keyframes lha-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.55; transform: scale(0.85); }
        }

        /* Link chip at row right edge. */
        .lha-link {
            font-size: 12.5px;
            font-weight: 600;
            color: #C4B5FD;
            transition: color 140ms ease;
        }

        .lha-link:hover {
            color: #fff;
        }

        /* Layered-animation entry. Staggered fade-up on card mount so the
           first impression feels crafted, not static. */
        .lha-card > * {
            opacity: 0;
            transform: translateY(8px);
            animation: lha-in 520ms cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        .lha-card > *:nth-child(1) { animation-delay: 50ms; }
        .lha-card > *:nth-child(2) { animation-delay: 120ms; }
        .lha-card > *:nth-child(3) { animation-delay: 190ms; }
        .lha-card > *:nth-child(4) { animation-delay: 260ms; }
        .lha-card > *:nth-child(5) { animation-delay: 320ms; }
        .lha-card > *:nth-child(6) { animation-delay: 380ms; }

        @keyframes lha-in {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Respect reduced-motion preference. */
        @media (prefers-reduced-motion: reduce) {
            .lha-card > * {
                opacity: 1;
                transform: none;
                animation: none;
            }
            .lha-live-diode {
                animation: none;
            }
            .lha-cta::before {
                display: none;
            }
        }
    </style>

    @livewireScripts
    @fluxScripts
</body>
</html>
