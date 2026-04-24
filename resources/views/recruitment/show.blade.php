<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $campaign->title }} &mdash; Apply to join</title>

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
            --rec-danger: #BE123C;
            --rec-danger-soft: #FFF1F2;
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
                --rec-danger: #FB7185;
                --rec-danger-soft: rgba(251,113,133,0.08);
            }
        }

        html, body {
            background-color: var(--rec-bg);
            color: var(--rec-ink);
        }
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            font-feature-settings: "ss01", "cv11";
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .font-serif {
            font-family: 'Instrument Serif', ui-serif, Georgia, serif;
            font-feature-settings: normal;
        }

        /* Subtle grain texture overlay */
        .grain::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.035;
            z-index: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='a'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' seed='7'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23a)' opacity='0.6'/%3E%3C/svg%3E");
        }

        .input-base {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 15px;
            line-height: 1.4;
            color: var(--rec-ink);
            background-color: var(--rec-surface);
            border: 1px solid var(--rec-border);
            border-radius: 10px;
            transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            font-family: inherit;
        }
        .input-base::placeholder {
            color: var(--rec-muted-2);
        }
        .input-base:hover {
            border-color: color-mix(in oklab, var(--rec-border) 50%, var(--rec-ink) 20%);
        }
        .input-base:focus {
            outline: none;
            border-color: var(--rec-accent);
            box-shadow: 0 0 0 4px color-mix(in oklab, var(--rec-accent) 15%, transparent);
            background-color: var(--rec-surface);
        }
        textarea.input-base {
            resize: vertical;
            min-height: 108px;
        }

        .label-base {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--rec-muted);
            margin-bottom: 0.5rem;
        }
        .required-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background-color: var(--rec-accent);
            margin-left: 6px;
            vertical-align: middle;
            transform: translateY(-1px);
        }

        .hint-text {
            font-size: 12.5px;
            color: var(--rec-muted);
            margin-top: 6px;
            line-height: 1.5;
        }

        /* Platform checkbox grid */
        .platform-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border: 1px solid var(--rec-border);
            border-radius: 10px;
            background-color: var(--rec-surface);
            cursor: pointer;
            transition: all 120ms ease;
            position: relative;
        }
        .platform-option:hover {
            border-color: color-mix(in oklab, var(--rec-border) 50%, var(--rec-ink) 20%);
        }
        .platform-option input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid var(--rec-border);
            border-radius: 5px;
            background-color: var(--rec-surface);
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            transition: all 120ms ease;
        }
        .platform-option input[type="checkbox"]:checked {
            background-color: var(--rec-ink);
            border-color: var(--rec-ink);
        }
        .platform-option input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 9px;
            border: solid var(--rec-surface);
            border-width: 0 1.75px 1.75px 0;
            transform: rotate(45deg);
        }
        .platform-option input[type="checkbox"]:checked ~ .platform-label {
            color: var(--rec-ink);
            font-weight: 500;
        }
        .platform-option:has(input:checked) {
            border-color: var(--rec-ink);
            background-color: color-mix(in oklab, var(--rec-surface) 85%, var(--rec-ink) 4%);
        }
        .platform-label {
            font-size: 14px;
            color: var(--rec-ink-2);
            font-weight: 400;
        }

        /* Custom file input */
        .file-drop {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.25rem;
            border: 1.5px dashed var(--rec-border);
            border-radius: 12px;
            background-color: var(--rec-surface);
            cursor: pointer;
            transition: all 120ms ease;
            font-size: 14px;
            color: var(--rec-muted);
        }
        .file-drop:hover {
            border-color: var(--rec-ink);
            color: var(--rec-ink);
        }
        .file-drop-filename {
            color: var(--rec-ink);
            font-weight: 500;
        }

        /* Submit button */
        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.75rem;
            background-color: var(--rec-ink);
            color: var(--rec-bg);
            border-radius: 999px;
            font-size: 15px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: transform 120ms ease, background-color 120ms ease, box-shadow 200ms ease;
            box-shadow: 0 2px 4px rgba(26,22,18,0.08), 0 8px 20px -6px rgba(26,22,18,0.2);
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(26,22,18,0.1), 0 12px 28px -6px rgba(26,22,18,0.28);
        }
        .btn-submit:active {
            transform: translateY(0);
        }

        /* Status pill */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            background-color: var(--rec-accent-soft);
            color: var(--rec-accent-ink);
            border: 1px solid color-mix(in oklab, var(--rec-accent) 30%, transparent);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pill .dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background-color: var(--rec-accent);
            box-shadow: 0 0 0 3px color-mix(in oklab, var(--rec-accent) 20%, transparent);
        }

        .section-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
            margin-top: 2.5rem;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--rec-border-2);
        }
        .section-label-text {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            font-size: 18px;
            color: var(--rec-ink-2);
            letter-spacing: -0.01em;
        }

        /* Error block */
        .error-block {
            padding: 1rem 1.25rem;
            background-color: var(--rec-danger-soft);
            border: 1px solid color-mix(in oklab, var(--rec-danger) 25%, transparent);
            border-radius: 10px;
            color: var(--rec-danger);
            font-size: 14px;
        }

        /* Entrance animation */
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .rise {
            animation: riseIn 500ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }
        .rise-1 { animation-delay: 40ms; }
        .rise-2 { animation-delay: 120ms; }
        .rise-3 { animation-delay: 200ms; }
    </style>
</head>
<body class="grain">
<div class="relative z-10 mx-auto w-full max-w-[680px] px-6 pb-20 pt-16 sm:px-8 sm:pt-24">

    <header class="mb-10">
        <div class="rise rise-1 flex flex-wrap items-center gap-3">
            <span class="status-pill">
                <span class="dot"></span>
                Now hiring
            </span>
            @if ($campaign->closes_at)
                <span class="text-[12px]" style="color: var(--rec-muted);">
                    Applications close {{ $campaign->closes_at->toFormattedDateString() }}
                </span>
            @endif
        </div>

        <h1 class="rise rise-2 mt-5 font-serif text-[42px] leading-[1.08] tracking-[-0.02em] sm:text-[52px]" style="color: var(--rec-ink);">
            {{ $campaign->title }}
        </h1>

        @if (! empty($campaign->description))
            <div class="rise rise-3 mt-5 whitespace-pre-wrap text-[15.5px] leading-[1.65]" style="color: var(--rec-ink-2);">{{ $campaign->description }}</div>
        @endif
    </header>

    @if ($errors->any())
        <div class="error-block mb-8">
            <div class="font-medium">Please fix the following before submitting:</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-[13.5px]">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ route('recruitment.apply', $campaign->slug) }}"
          enctype="multipart/form-data"
          class="relative">
        @csrf

        <div class="section-label">
            <span class="section-label-text">About you</span>
        </div>

        <div class="space-y-5">
            <div>
                <label for="full_name" class="label-base">Full name<span class="required-dot" aria-label="required"></span></label>
                <input type="text" id="full_name" name="full_name" required
                       value="{{ old('full_name') }}" autocomplete="name"
                       class="input-base">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="email" class="label-base">Email<span class="required-dot" aria-label="required"></span></label>
                    <input type="email" id="email" name="email" required
                           value="{{ old('email') }}" autocomplete="email"
                           class="input-base">
                </div>
                <div>
                    <label for="phone" class="label-base">Phone<span class="required-dot" aria-label="required"></span></label>
                    <input type="text" id="phone" name="phone" required
                           value="{{ old('phone') }}" autocomplete="tel"
                           placeholder="60123456789"
                           class="input-base">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="ic_number" class="label-base">IC number</label>
                    <input type="text" id="ic_number" name="ic_number"
                           value="{{ old('ic_number') }}"
                           class="input-base">
                </div>
                <div>
                    <label for="location" class="label-base">Location</label>
                    <input type="text" id="location" name="location"
                           value="{{ old('location') }}"
                           placeholder="City, state"
                           class="input-base">
                </div>
            </div>
        </div>

        <div class="section-label">
            <span class="section-label-text">Where can you go live?</span>
        </div>

        <fieldset>
            <legend class="label-base">Pick one or more<span class="required-dot" aria-label="required"></span></legend>
            @php($selectedPlatforms = old('platforms', []))
            <div class="mt-2 grid gap-2.5 sm:grid-cols-3">
                <label class="platform-option">
                    <input type="checkbox" name="platforms[]" value="tiktok" @checked(in_array('tiktok', $selectedPlatforms))>
                    <span class="platform-label">TikTok</span>
                </label>
                <label class="platform-option">
                    <input type="checkbox" name="platforms[]" value="shopee" @checked(in_array('shopee', $selectedPlatforms))>
                    <span class="platform-label">Shopee</span>
                </label>
                <label class="platform-option">
                    <input type="checkbox" name="platforms[]" value="facebook" @checked(in_array('facebook', $selectedPlatforms))>
                    <span class="platform-label">Facebook</span>
                </label>
            </div>
        </fieldset>

        <div class="section-label">
            <span class="section-label-text">Tell us your story</span>
        </div>

        <div class="space-y-5">
            <div>
                <label for="experience_summary" class="label-base">Experience</label>
                <textarea id="experience_summary" name="experience_summary" rows="4"
                          placeholder="Briefly share your live selling or hosting background — platforms, niches, notable wins, audience size, etc."
                          class="input-base">{{ old('experience_summary') }}</textarea>
            </div>

            <div>
                <label for="motivation" class="label-base">Why do you want to join us?</label>
                <textarea id="motivation" name="motivation" rows="4"
                          placeholder="What draws you to this role? What would you bring to the team?"
                          class="input-base">{{ old('motivation') }}</textarea>
            </div>
        </div>

        <div class="section-label">
            <span class="section-label-text">Anything to attach</span>
        </div>

        <div>
            <label for="resume" class="file-drop" id="resume-label">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <span id="resume-filename">Upload resume (optional)</span>
            </label>
            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="sr-only" onchange="document.getElementById('resume-filename').textContent = this.files[0] ? this.files[0].name : 'Upload resume (optional)'; document.getElementById('resume-filename').classList.toggle('file-drop-filename', !!this.files[0]);">
            <p class="hint-text">PDF, DOC, or DOCX · up to 5 MB</p>
        </div>

        <div class="mt-10 flex flex-col-reverse items-start justify-between gap-4 border-t pt-6 sm:flex-row sm:items-center" style="border-color: var(--rec-border-2);">
            <p class="text-[12.5px] leading-relaxed" style="color: var(--rec-muted);">
                By submitting, you agree that we may contact you about this role.<br class="hidden sm:inline">
                We handle your info with care and never share it.
            </p>
            <button type="submit" class="btn-submit">
                Submit application
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </div>
    </form>
</div>
</body>
</html>
