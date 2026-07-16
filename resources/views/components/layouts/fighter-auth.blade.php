<!DOCTYPE html>
<html lang="en" class="bg-[#0B1120]">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#0B1120" />

    <title>{{ $title ?? 'Log in · Bedaie Fighter' }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
    @livewireStyles
    @fluxAppearance
</head>
<body class="antialiased text-white" style="font-family: 'Geist', system-ui, sans-serif;">

    {{-- Deep slate stage with warm orange glow — matches the Fighter workspace. --}}
    <div class="fla-stage relative flex min-h-screen items-center justify-center p-4 sm:p-8">
        <div class="fla-aurora pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <main class="relative z-10 w-full max-w-[440px]">
            <div class="fla-card">
                {{ $slot }}
            </div>

            <div class="mt-6 flex items-center justify-between px-1 text-[11.5px] text-white/50">
                <span>Not a fighter?</span>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-[4px] font-semibold text-white/80 transition-colors hover:text-white"
                    wire:navigate
                >
                    <span>Normal login</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <p class="mt-3 text-center text-[10.5px] text-white/30">
                &copy; {{ now()->format('Y') }} Bedaie Fighter
            </p>
        </main>
    </div>

    <style>
        body { background: #0B1120; }

        .fla-stage {
            background:
                radial-gradient(1200px 800px at 50% -10%, rgba(249, 115, 22, 0.26), transparent 60%),
                radial-gradient(900px 700px at 8% 110%, rgba(244, 63, 94, 0.20), transparent 58%),
                radial-gradient(800px 700px at 100% 80%, rgba(56, 189, 248, 0.14), transparent 60%),
                #0B1120;
        }

        .fla-aurora {
            background:
                radial-gradient(600px 400px at 80% 10%, rgba(245, 158, 11, 0.14), transparent 60%),
                radial-gradient(500px 500px at 20% 70%, rgba(249, 115, 22, 0.16), transparent 55%);
            filter: blur(30px);
        }

        .fla-card {
            position: relative;
            border-radius: 28px;
            padding: 36px 28px 32px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%),
                rgba(17, 24, 39, 0.72);
            backdrop-filter: blur(32px) saturate(160%);
            -webkit-backdrop-filter: blur(32px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.06) inset,
                0 30px 60px -20px rgba(0, 0, 0, 0.55),
                0 10px 25px -10px rgba(249, 115, 22, 0.20);
        }

        .fla-field { position: relative; }

        .fla-field-input {
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

        .fla-field-input::placeholder { color: transparent; }
        .fla-field-input:hover { border-color: rgba(255, 255, 255, 0.18); }

        .fla-field-input:focus {
            outline: none;
            border-color: #FB923C;
            background: rgba(249, 115, 22, 0.08);
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.18);
        }

        .fla-field-label {
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

        .fla-field-input:focus ~ .fla-field-label,
        .fla-field-input:not(:placeholder-shown) ~ .fla-field-label {
            top: 10px;
            transform: translateY(0);
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: rgba(255, 255, 255, 0.55);
            text-transform: uppercase;
        }

        .fla-field-input:focus ~ .fla-field-label { color: #FDBA74; }

        .fla-check {
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

        .fla-check:hover { border-color: rgba(255, 255, 255, 0.40); }

        .fla-check:checked {
            border-color: transparent;
            background: linear-gradient(135deg, #FB923C 0%, #EA580C 100%);
        }

        .fla-check:checked::after {
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

        .fla-cta {
            position: relative;
            width: 100%;
            padding: 16px 20px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #FB923C 0%, #F97316 55%, #EA580C 100%);
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
                0 12px 30px -8px rgba(249, 115, 22, 0.55),
                0 4px 12px -4px rgba(249, 115, 22, 0.40);
        }

        .fla-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 35%, rgba(255, 255, 255, 0.28) 50%, transparent 65%);
            transform: translateX(-100%);
            transition: transform 700ms ease;
        }

        .fla-cta:hover {
            transform: translateY(-1px);
            box-shadow:
                0 1px 0 0 rgba(255, 255, 255, 0.25) inset,
                0 16px 34px -8px rgba(249, 115, 22, 0.65),
                0 6px 14px -4px rgba(249, 115, 22, 0.50);
        }

        .fla-cta:hover::before { transform: translateX(100%); }
        .fla-cta:active { transform: translateY(0) scale(0.992); }
        .fla-cta:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .fla-error {
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

        .fla-pw-toggle {
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

        .fla-pw-toggle:hover { background: rgba(255, 255, 255, 0.14); color: #fff; }

        .fla-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 10px 5px 8px;
            border-radius: 999px;
            background: rgba(249, 115, 22, 0.16);
            border: 1px solid rgba(249, 115, 22, 0.30);
            color: #FED7AA;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .fla-link {
            font-size: 12.5px;
            font-weight: 600;
            color: #FDBA74;
            transition: color 140ms ease;
        }

        .fla-link:hover { color: #fff; }

        .fla-card > * {
            opacity: 0;
            transform: translateY(8px);
            animation: fla-in 520ms cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        .fla-card > *:nth-child(1) { animation-delay: 50ms; }
        .fla-card > *:nth-child(2) { animation-delay: 120ms; }
        .fla-card > *:nth-child(3) { animation-delay: 190ms; }
        .fla-card > *:nth-child(4) { animation-delay: 260ms; }
        .fla-card > *:nth-child(5) { animation-delay: 320ms; }

        @keyframes fla-in {
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .fla-card > * { opacity: 1; transform: none; animation: none; }
            .fla-cta::before { display: none; }
        }
    </style>

    @livewireScripts
    @fluxScripts
</body>
</html>
