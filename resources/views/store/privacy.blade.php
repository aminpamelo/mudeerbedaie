@php
    $store = config('store.name');
    $company = config('app.company');
    $companyName = $company['name'] ?? $store;
    $email = $company['email'] ?? null;
    $phone = $company['phone'] ?? null;
    $addressParts = array_filter([
        $company['address_line_1'] ?? null,
        $company['address_line_2'] ?? null,
        trim(($company['postal_code'] ?? '').' '.($company['city'] ?? '')),
        $company['state'] ?? null,
        $company['country'] ?? null,
    ]);
    $address = implode(', ', $addressParts);
    $effectiveDate = 'September 3, 2026';
    $site = rtrim(config('app.url'), '/');
@endphp

<x-layouts.store :title="'Privacy Policy — ' . $store">
    {{-- Header --}}
    <section class="relative overflow-hidden border-b border-violet-100/70 bg-gradient-to-b from-violet-50 via-fuchsia-50/50 to-white">
        <span class="pointer-events-none absolute -right-20 -top-20 h-60 w-60 rounded-full bg-fuchsia-300/30 blur-3xl"></span>
        <div class="relative mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <nav class="mb-3 flex items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="text-zinc-600">Privacy Policy</span>
            </nav>
            <h1 class="font-display text-3xl font-extrabold text-zinc-900 sm:text-4xl">Privacy Policy</h1>
            <p class="mt-2 text-sm text-zinc-500">Last updated: {{ $effectiveDate }}</p>
        </div>
    </section>

    {{-- Body --}}
    <style>
        .policy-prose { color: #3f3f46; font-size: 15px; line-height: 1.75; }
        .policy-prose > p { margin: 0 0 1rem; }
        .policy-prose h2 { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; font-weight: 700; font-size: 1.25rem; color: #18181b; margin: 2rem 0 .75rem; letter-spacing: -0.01em; }
        .policy-prose ul { margin: 0 0 1rem; padding-left: 1.25rem; list-style: disc; }
        .policy-prose li { margin: .35rem 0; }
        .policy-prose li::marker { color: #a78bfa; }
        .policy-prose a { color: #6d28d9; text-decoration: none; }
        .policy-prose a:hover { text-decoration: underline; }
        .policy-prose strong { color: #27272a; }
    </style>
    <article class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="policy-prose">

            <p>
                This Privacy Policy explains how <strong>{{ $companyName }}</strong>@if(!empty($company['registration'])) (Company Reg. No. {{ $company['registration'] }})@endif,
                operating the <strong>{{ $store }}</strong> brand and the websites and services at
                <a href="{{ $site }}">{{ $site }}</a> (collectively, the “Services”), collects, uses, shares, and
                protects your personal information. By using our Services you agree to the practices described here.
            </p>

            <h2>1. Who we are</h2>
            <p>
                The data controller responsible for your information is:
            </p>
            <ul>
                <li><strong>{{ $companyName }}</strong></li>
                @if($address)<li>{{ $address }}</li>@endif
                @if($email)<li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                @if($phone)<li>Phone: {{ $phone }}</li>@endif
            </ul>

            <h2>2. Information we collect</h2>
            <p>We collect the following categories of information:</p>
            <ul>
                <li><strong>Information you provide:</strong> name, phone number (including WhatsApp number), email address, delivery/billing address, order and payment details, messages you send us, and any information you submit in forms.</li>
                <li><strong>Information collected automatically:</strong> device and browser data, IP address, pages viewed, and interactions with our Services, collected through cookies and similar technologies.</li>
                <li><strong>Information from Meta Platforms:</strong> when you use Facebook Login, message us on WhatsApp, or interact with our ads, we may receive information such as your name, profile details, phone number, message content, and advertising interactions from Meta’s APIs (including the WhatsApp Business Platform, Facebook Login, the Marketing API, and Meta Pixel / Conversions API).</li>
                <li><strong>Information from payment and logistics providers:</strong> transaction status and delivery information needed to fulfil your orders.</li>
            </ul>

            <h2>3. How we use your information</h2>
            <ul>
                <li>To process, fulfil, and deliver your orders and provide customer support.</li>
                <li>To send transactional and service messages over WhatsApp and email (e.g., order confirmations, delivery updates, receipts, and account notifications).</li>
                <li>To send marketing or promotional messages <strong>only where you have opted in</strong>, and to let you opt out at any time (for WhatsApp, reply <strong>STOP</strong>).</li>
                <li>To operate, secure, analyse, and improve our Services, and to measure the performance of our advertising.</li>
                <li>To comply with legal obligations and enforce our terms.</li>
            </ul>

            <h2>4. WhatsApp Business Platform &amp; Meta technologies</h2>
            <p>
                We use the <strong>WhatsApp Business Platform</strong> and other Meta technologies (including Facebook Login, the
                Marketing API, and the Meta Pixel / Conversions API) to communicate with customers, run and measure advertising, and
                provide our Services.
            </p>
            <p>
                <strong>Our use and transfer of information received from Meta APIs adheres to the
                <a href="https://developers.facebook.com/terms/" target="_blank" rel="noopener">Meta Platform Terms</a> and the
                <a href="https://developers.facebook.com/devpolicy/" target="_blank" rel="noopener">Meta Developer Policies</a>,
                including any applicable Limited Use requirements.</strong>
                We only request the data we need, use it solely for the purposes described in this policy, do not sell it, and do not
                use it for unauthorised purposes.
            </p>
            <p>
                WhatsApp messages are also governed by
                <a href="https://www.whatsapp.com/legal/business-policy/" target="_blank" rel="noopener">WhatsApp’s Business Messaging Policy</a>.
                We message you only after you contact us or opt in, and you can stop receiving messages at any time by replying STOP or contacting us.
            </p>

            <h2>5. How we share your information</h2>
            <p>We do not sell your personal information. We share it only as needed with:</p>
            <ul>
                <li><strong>Service providers</strong> who help us operate the Services — including Meta Platforms, Inc. and WhatsApp, payment processors, hosting, shipping and logistics partners — under obligations to protect your data.</li>
                <li><strong>Authorities</strong> where required by law, regulation, legal process, or to protect our rights, users, and the public.</li>
                <li><strong>Successors</strong> in the event of a merger, acquisition, or sale of assets, subject to this policy.</li>
            </ul>

            <h2>6. Cookies and tracking technologies</h2>
            <p>
                We use cookies and pixels (including the Meta Pixel and Google tags) to keep the site working, remember your
                preferences, understand usage, and measure advertising. You can control cookies through your browser settings; disabling
                some cookies may affect functionality.
            </p>

            <h2>7. Data retention</h2>
            <p>
                We keep personal information only for as long as necessary to provide the Services, fulfil the purposes described here,
                comply with legal, tax, and accounting obligations, and resolve disputes. When no longer needed, we delete or anonymise it.
            </p>

            <h2>8. Your rights and choices</h2>
            <ul>
                <li>Access, correct, or update your personal information.</li>
                <li>Withdraw consent and opt out of marketing (reply <strong>STOP</strong> on WhatsApp, or contact us).</li>
                <li>Request a copy or deletion of your personal information (see Data Deletion below).</li>
            </ul>

            <h2 id="data-deletion">9. Data deletion request</h2>
            <p>
                To request deletion of the personal information we hold about you — including data obtained through WhatsApp, Facebook
                Login, or other Meta APIs — email us at
                @if($email)<a href="mailto:{{ $email }}?subject=Data%20Deletion%20Request">{{ $email }}</a>@else our contact email @endif
                with the subject line “Data Deletion Request” and the phone number or email associated with your records. We will verify
                your request and delete the data within 30 days, unless we are required to retain it by law.
            </p>

            <h2>10. Data security</h2>
            <p>
                We use reasonable administrative, technical, and organisational measures to protect your information. No method of
                transmission or storage is completely secure, so we cannot guarantee absolute security.
            </p>

            <h2>11. Children’s privacy</h2>
            <p>
                Our Services are not directed to children under 13 (or the minimum age required in your jurisdiction), and we do not
                knowingly collect their personal information. If you believe a child has provided us data, please contact us so we can delete it.
            </p>

            <h2>12. International transfers</h2>
            <p>
                Your information may be processed on servers located outside your country of residence, including by our service providers
                such as Meta. Where required, we take steps to ensure your information receives an adequate level of protection.
            </p>

            <h2>13. Changes to this policy</h2>
            <p>
                We may update this Privacy Policy from time to time. We will post the updated version on this page and revise the “Last
                updated” date above. Material changes may be communicated to you directly where appropriate.
            </p>

            <h2>14. Contact us</h2>
            <p>If you have questions about this Privacy Policy or your personal information, contact us at:</p>
            <ul>
                <li><strong>{{ $companyName }}</strong></li>
                @if($address)<li>{{ $address }}</li>@endif
                @if($email)<li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                @if($phone)<li>Phone: {{ $phone }}</li>@endif
            </ul>
        </div>
    </article>
</x-layouts.store>
