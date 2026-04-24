<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $campaign->title }} &mdash; Apply</title>

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
            --rec-muted-3: #A3A3A3;
            --rec-border: #E5E5E5;
            --rec-border-2: #F0F0F0;
            --rec-accent: #10B981;
            --rec-accent-ink: #047857;
            --rec-accent-soft: #ECFDF5;
            --rec-accent-ring: rgba(16, 185, 129, 0.14);
            --rec-danger: #E11D48;
            --rec-danger-soft: #FFF1F2;
            --rec-shadow: 0 1px 2px rgba(10,10,10,0.04), 0 4px 16px -2px rgba(10,10,10,0.06);
            --rec-shadow-2: 0 1px 3px rgba(10,10,10,0.06), 0 10px 32px -4px rgba(10,10,10,0.09);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --rec-bg: #0A0A0A;
                --rec-surface: #121212;
                --rec-ink: #FAFAFA;
                --rec-ink-2: #D4D4D4;
                --rec-muted: #A3A3A3;
                --rec-muted-2: #737373;
                --rec-muted-3: #525252;
                --rec-border: #262626;
                --rec-border-2: #1C1C1C;
                --rec-accent: #34D399;
                --rec-accent-ink: #6EE7B7;
                --rec-accent-soft: rgba(52,211,153,0.10);
                --rec-accent-ring: rgba(52,211,153,0.16);
                --rec-danger: #FB7185;
                --rec-danger-soft: rgba(251,113,133,0.08);
                --rec-shadow: 0 1px 2px rgba(0,0,0,0.5), 0 4px 16px -2px rgba(0,0,0,0.4);
                --rec-shadow-2: 0 1px 3px rgba(0,0,0,0.6), 0 10px 32px -4px rgba(0,0,0,0.5);
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

        /* Decorative emerald gradient glow behind hero */
        .glow {
            position: fixed;
            pointer-events: none;
            z-index: 0;
            inset: 0;
            overflow: hidden;
        }
        .glow::before,
        .glow::after {
            content: '';
            position: absolute;
            border-radius: 9999px;
            filter: blur(60px);
            opacity: 0.55;
        }
        .glow::before {
            top: -150px;
            left: -150px;
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, var(--rec-accent-ring) 0%, transparent 70%);
        }
        .glow::after {
            top: -80px;
            right: -200px;
            width: 540px;
            height: 540px;
            background: radial-gradient(circle, color-mix(in oklab, var(--rec-accent) 10%, transparent) 0%, transparent 72%);
        }
        @media (prefers-color-scheme: dark) {
            .glow::before { opacity: 0.8; }
            .glow::after { opacity: 0.6; }
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3125rem 0.75rem;
            background-color: var(--rec-accent-soft);
            color: var(--rec-accent-ink);
            border: 1px solid color-mix(in oklab, var(--rec-accent) 28%, transparent);
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        .status-pill .dot {
            position: relative;
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background-color: var(--rec-accent);
        }
        .status-pill .dot::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 9999px;
            background-color: var(--rec-accent);
            opacity: 0.4;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.6); opacity: 0; }
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 1.25rem;
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

        .field-label {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 13px;
            font-weight: 500;
            color: var(--rec-ink-2);
            margin-bottom: 0.5rem;
        }
        .required-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            background-color: var(--rec-accent);
        }

        .input-base {
            width: 100%;
            padding: 0.75rem 0.9375rem;
            font-size: 14.5px;
            line-height: 1.4;
            color: var(--rec-ink);
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 10px;
            transition: border-color 120ms ease, box-shadow 120ms ease;
            font-family: inherit;
            font-feature-settings: inherit;
        }
        .input-base::placeholder { color: var(--rec-muted-3); }
        .input-base:hover {
            border-color: color-mix(in oklab, var(--rec-border) 40%, var(--rec-ink) 30%);
        }
        .input-base:focus {
            outline: none;
            border-color: var(--rec-accent);
            box-shadow: 0 0 0 4px var(--rec-accent-ring);
        }
        textarea.input-base {
            resize: vertical;
            min-height: 108px;
        }

        .hint-text {
            font-size: 12.5px;
            color: var(--rec-muted-2);
            margin-top: 6px;
            line-height: 1.5;
        }

        /* Platform selector — modern pill cards */
        .platform-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 0.9375rem;
            border: 1px solid var(--rec-border);
            background-color: var(--rec-surface);
            border-radius: 10px;
            cursor: pointer;
            transition: all 140ms ease;
            user-select: none;
        }
        .platform-option:hover {
            border-color: color-mix(in oklab, var(--rec-border) 40%, var(--rec-ink) 30%);
        }
        .platform-option input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--rec-border);
            border-radius: 5px;
            background-color: var(--rec-surface);
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
            transition: all 140ms ease;
        }
        .platform-option input[type="checkbox"]:checked {
            background-color: var(--rec-accent);
            border-color: var(--rec-accent);
        }
        .platform-option input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 9px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .platform-option:has(input:checked) {
            border-color: var(--rec-accent);
            box-shadow: 0 0 0 3px var(--rec-accent-ring);
            background-color: color-mix(in oklab, var(--rec-surface) 90%, var(--rec-accent) 6%);
        }
        .platform-label {
            font-size: 14px;
            font-weight: 500;
            color: var(--rec-ink);
        }

        /* File drop */
        .file-drop {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.125rem;
            border: 1.5px dashed var(--rec-border);
            border-radius: 12px;
            background-color: var(--rec-surface);
            cursor: pointer;
            transition: all 140ms ease;
        }
        .file-drop:hover {
            border-color: var(--rec-accent);
            background-color: color-mix(in oklab, var(--rec-surface) 94%, var(--rec-accent) 4%);
        }
        .file-drop-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background-color: var(--rec-accent-soft);
            color: var(--rec-accent-ink);
            flex-shrink: 0;
        }
        .file-drop-text {
            font-size: 13.5px;
            color: var(--rec-ink);
            font-weight: 500;
        }
        .file-drop-hint {
            font-size: 12px;
            color: var(--rec-muted-2);
            margin-top: 2px;
        }

        /* Modern primary button — emerald tint */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.875rem 1.5rem;
            background-color: var(--rec-ink);
            color: var(--rec-bg);
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14.5px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 120ms ease, box-shadow 200ms ease, background-color 120ms ease;
            box-shadow:
                0 1px 2px rgba(10,10,10,0.06),
                0 6px 16px -4px rgba(10,10,10,0.14),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow:
                0 2px 4px rgba(10,10,10,0.08),
                0 12px 24px -4px rgba(10,10,10,0.18),
                inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .btn-primary:active { transform: translateY(0); }

        /* Form card with subtle elevation */
        .form-card {
            position: relative;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 20px;
            box-shadow: var(--rec-shadow);
            overflow: hidden;
        }
        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(to right, transparent, color-mix(in oklab, var(--rec-accent) 50%, transparent), transparent);
        }

        .error-block {
            padding: 0.875rem 1.125rem;
            background-color: var(--rec-danger-soft);
            border: 1px solid color-mix(in oklab, var(--rec-danger) 22%, transparent);
            border-radius: 10px;
            color: var(--rec-danger);
            font-size: 13.5px;
        }

        /* Left-side info card */
        .info-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 9999px;
            font-size: 12px;
            color: var(--rec-muted);
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rise { animation: riseIn 500ms cubic-bezier(0.2, 0.8, 0.2, 1) both; }
        .rise-1 { animation-delay: 40ms; }
        .rise-2 { animation-delay: 100ms; }
        .rise-3 { animation-delay: 180ms; }
        .rise-4 { animation-delay: 260ms; }

        /* Subtle dot grid texture in the background */
        .dots {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: radial-gradient(color-mix(in oklab, var(--rec-ink) 6%, transparent) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: radial-gradient(ellipse at top, black 0%, transparent 60%);
            opacity: 0.5;
        }
    </style>
</head>
<body>
<div class="glow"></div>
<div class="dots"></div>

<div class="relative z-10 mx-auto w-full max-w-[1120px] px-6 pb-20 pt-12 sm:px-8 sm:pt-16 lg:pt-20">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,560px)] lg:gap-16">

        {{-- LEFT: campaign info --}}
        <aside class="rise rise-1 lg:sticky lg:top-12 lg:self-start">
            <span class="status-pill">
                <span class="dot"></span>
                Now hiring
            </span>

            <h1 class="mt-6 text-[38px] font-semibold leading-[1.05] tracking-[-0.03em] sm:text-[48px] lg:text-[52px]" style="color: var(--rec-ink);">
                {{ $campaign->title }}
            </h1>

            @if (! empty($campaign->description))
                <p class="mt-5 whitespace-pre-wrap text-[15.5px] leading-[1.65]" style="color: var(--rec-ink-2);">{{ $campaign->description }}</p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-2">
                @if ($campaign->closes_at)
                    <span class="info-meta">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Closes {{ $campaign->closes_at->toFormattedDateString() }}
                    </span>
                @endif
                <span class="info-meta">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    ~5 minutes to complete
                </span>
            </div>

            <div class="mt-10 hidden lg:block">
                <div class="flex items-center gap-3">
                    <div class="flex -space-x-2">
                        <div class="h-6 w-6 rounded-full border-2" style="background: linear-gradient(135deg, #10B981, #059669); border-color: var(--rec-surface);"></div>
                        <div class="h-6 w-6 rounded-full border-2" style="background: linear-gradient(135deg, #8B5CF6, #6D28D9); border-color: var(--rec-surface);"></div>
                        <div class="h-6 w-6 rounded-full border-2" style="background: linear-gradient(135deg, #F59E0B, #D97706); border-color: var(--rec-surface);"></div>
                    </div>
                    <span class="text-[13px]" style="color: var(--rec-muted);">Join our growing team of live hosts</span>
                </div>
            </div>
        </aside>

        {{-- RIGHT: form --}}
        <div class="rise rise-2">
            @if ($errors->any())
                <div class="error-block mb-5">
                    <div class="font-medium">Please fix the following:</div>
                    <ul class="mt-1.5 list-disc space-y-1 pl-5 text-[13px]">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ route('recruitment.apply', $campaign->slug) }}"
                  enctype="multipart/form-data"
                  class="form-card p-6 sm:p-8">
                @csrf

                @foreach (($campaign->form_schema['pages'] ?? []) as $page)
                    <div class="section-label" @if ($loop->first) style="margin-top: 0;" @else style="margin-top: 2.5rem;" @endif>
                        <span class="section-label-text">{{ sprintf('%02d', $loop->iteration) }} · {{ $page['title'] ?? '' }}</span>
                    </div>

                    <div class="space-y-4">
                        @foreach (($page['fields'] ?? []) as $field)
                            @include("recruitment.fields.{$field['type']}", ['field' => $field])
                        @endforeach
                    </div>
                @endforeach

                {{-- Submit --}}
                <div class="mt-8 border-t pt-6" style="border-color: var(--rec-border-2);">
                    <button type="submit" class="btn-primary">
                        Submit application
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                    <p class="mt-3 text-center text-[12px]" style="color: var(--rec-muted-2);">
                        By submitting you agree we may contact you about this role. We handle your info with care.
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
