<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Dedicated Student login page (Bahasa Melayu) — phone-only sign-in.
 *
 * By product decision, students sign in with just their registered phone
 * number (no password). Their portal only exposes classes/subscriptions they
 * have already purchased, so the owner accepts the lower assurance in exchange
 * for a friction-free login for non-technical students.
 *
 * SECURITY GUARDRAILS:
 *  - Phone-only auth is restricted to `role = student` accounts only. An
 *    admin/teacher phone will NOT authenticate here — those roles must use the
 *    password-backed `/login`. This keeps the passwordless shortcut from ever
 *    reaching a privileged account.
 *  - The attempt is rate-limited per phone+IP to blunt number enumeration.
 *  - Login is always "remember me" so the browser keeps a long-lived session
 *    and the student rarely has to sign in again.
 *
 * Post-login routing is handled by the `dashboard` route's role switch, which
 * redirects `student` users to `student.dashboard` (/my).
 */
new #[Layout('components.layouts.student-auth')] class extends Component
{
    public string $phone = '';

    public function authenticate(): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
        ], attributes: [
            'phone' => 'nombor telefon',
        ]);

        $this->ensureIsNotRateLimited();

        $user = $this->resolveStudentByPhone($this->phone);

        if (! $user) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'phone' => 'Nombor telefon tidak dijumpai. Pastikan ia sama seperti yang didaftarkan, atau hubungi admin.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Always remember → long-lived browser session so students stay logged
        // in and rarely need to re-enter their number.
        Auth::login($user, true);
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Find the single student whose stored phone matches the typed number.
     *
     * Stored numbers use the `+60` + local-number-with-leading-zero shape
     * (e.g. `+600123456789`). Students may type any common variant, so we
     * normalise to a set of candidate stored forms and match on role=student.
     */
    protected function resolveStudentByPhone(string $input): ?User
    {
        $digits = preg_replace('/\D/', '', $input);

        if ($digits === '' || strlen($digits) < 7) {
            return null;
        }

        // Derive the local part (must begin with a leading 0, matching storage).
        if (str_starts_with($digits, '600')) {
            $local = substr($digits, 2);            // 600123… → 0123…
        } elseif (str_starts_with($digits, '60')) {
            $local = '0'.substr($digits, 2);        // 60123…  → 0123…
        } elseif (str_starts_with($digits, '0')) {
            $local = $digits;                        // 0123…   → 0123…
        } else {
            $local = '0'.$digits;                    // 123…    → 0123…
        }

        $candidates = array_values(array_unique(array_filter([
            '+60'.$local,               // canonical stored form
            '+'.$digits,                // if they typed the full 60… string
            '+60'.$digits,             // if they typed a bare local number
            '+60'.ltrim($local, '0'),  // fallback: stored without the extra 0
            $local,                     // bare local, just in case
        ])));

        return User::query()
            ->where('role', 'student')
            ->whereIn('phone', $candidates)
            ->first();
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'phone' => "Terlalu banyak cubaan. Sila cuba lagi dalam {$seconds} saat.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->phone).'|'.request()->ip());
    }
}; ?>

<div>
    {{-- Row 1: BeDaie wordmark + Portal Pelajar pill --}}
    <div class="flex items-center justify-between">
        <img
            src="{{ asset('images/bedaie-brand.png') }}"
            alt="BeDaie"
            class="h-8 w-auto select-none"
            draggable="false"
        >

        <span class="sla-pill">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M22 10 12 5 2 10l10 5 10-5Zm0 0v6M6 12.5V17c0 1.1 2.7 2 6 2s6-.9 6-2v-4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            PORTAL PELAJAR
        </span>
    </div>

    {{-- Row 2: Warm headline --}}
    <div class="mt-6">
        <h1 class="text-[30px] font-bold leading-[1.08] tracking-[-0.025em] text-[var(--color-ink)] sm:text-[32px]">
            Selamat kembali!<br>
            <span class="bg-gradient-to-r from-violet-600 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent">
                Sambung pembelajaran anda.
            </span>
        </h1>
        <p class="mt-[10px] text-[14px] leading-[1.5] text-[var(--color-muted)]">
            Masukkan nombor telefon anda untuk log masuk ke kelas dan kursus anda.
        </p>
    </div>

    {{-- Row 3: Status flash --}}
    @if (session('status'))
        <div class="mt-5 flex items-center gap-2 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-3 py-2 text-[12.5px] font-medium text-emerald-700">
            <span class="h-[6px] w-[6px] rounded-full bg-emerald-500" aria-hidden="true"></span>
            {{ session('status') }}
        </div>
    @endif

    {{-- Row 4: Form — phone only --}}
    <form wire:submit.prevent="authenticate" class="mt-6 flex flex-col gap-3" novalidate>

        <div class="sla-field">
            <input
                id="sla-phone"
                type="tel"
                inputmode="tel"
                class="sla-field-input"
                wire:model="phone"
                required
                autofocus
                autocomplete="tel"
                placeholder=" "
            >
            <label for="sla-phone" class="sla-field-label">
                Nombor telefon
            </label>
            @error('phone')
                <div class="sla-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <button
            type="submit"
            class="sla-cta mt-2"
            wire:loading.attr="disabled"
            wire:target="authenticate"
        >
            <span class="relative z-10 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="authenticate">Log masuk</span>
                <span wire:loading wire:target="authenticate" class="flex items-center gap-2">
                    <span class="inline-block h-[12px] w-[12px] animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                    Sedang log masuk…
                </span>
                <svg wire:loading.remove wire:target="authenticate" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </button>
    </form>

    {{-- Row 5: Soft helper chip — reassurance copy --}}
    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-[var(--color-line)] bg-[var(--color-brand-soft)] px-4 py-3">
        <div class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-violet-500/15 text-violet-700">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8v4m0 4h.01M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <div class="text-[12px] leading-[1.5] text-[var(--color-muted)]">
            Guna nombor telefon yang anda daftarkan. Anda akan kekal log masuk
            pada peranti ini. Hubungi admin jika nombor tidak dikenali.
        </div>
    </div>
</div>
