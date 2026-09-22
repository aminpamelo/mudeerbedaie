<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Dedicated Student login page (Bahasa Melayu).
 *
 * Auth logic mirrors the generic auth.login Volt — phone-or-email + password,
 * rate-limited, remember-me, intended-URL redirect. The only difference is
 * the mounted layout + copy: students land on a light, BeDaie-themed page
 * rather than the shared simple-auth layout.
 *
 * Post-login routing is handled by the `dashboard` route's role switch,
 * which already redirects `student` users to `student.dashboard` (/my). So
 * any role CAN successfully sign in here — they just land on whichever
 * dashboard matches their role.
 */
new #[Layout('components.layouts.student-auth')] class extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], attributes: [
            'login' => 'nombor telefon atau emel',
            'password' => 'kata laluan',
        ]);

        $this->ensureIsNotRateLimited();

        $loginField = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$loginField => $this->login, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'Maklumat log masuk tidak betul. Sila cuba sekali lagi.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak cubaan. Sila cuba lagi dalam {$seconds} saat.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
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
            Log masuk untuk akses kelas, kursus dan langganan anda.
        </p>
    </div>

    {{-- Row 3: Status flash (e.g. after password reset) --}}
    @if (session('status'))
        <div class="mt-5 flex items-center gap-2 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-3 py-2 text-[12.5px] font-medium text-emerald-700">
            <span class="h-[6px] w-[6px] rounded-full bg-emerald-500" aria-hidden="true"></span>
            {{ session('status') }}
        </div>
    @endif

    {{-- Row 4: Form --}}
    <form wire:submit.prevent="authenticate" class="mt-6 flex flex-col gap-3" novalidate>

        <div class="sla-field">
            <input
                id="sla-login"
                type="text"
                class="sla-field-input"
                wire:model.live="login"
                required
                autofocus
                autocomplete="username"
                placeholder=" "
            >
            <label for="sla-login" class="sla-field-label">
                Nombor telefon atau emel
            </label>
            @error('login')
                <div class="sla-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="sla-field" x-data="{ visible: false }">
            <input
                id="sla-password"
                class="sla-field-input"
                :type="visible ? 'text' : 'password'"
                wire:model.live="password"
                required
                autocomplete="current-password"
                placeholder=" "
            >
            <label for="sla-password" class="sla-field-label">
                Kata laluan
            </label>
            <button
                type="button"
                @click="visible = !visible"
                class="sla-pw-toggle"
                aria-label="Tunjuk atau sorok kata laluan"
            >
                <span x-show="!visible">Tunjuk</span>
                <span x-show="visible" x-cloak>Sorok</span>
            </button>
            @error('password')
                <div class="sla-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-[2px]">
            <label class="flex cursor-pointer select-none items-center gap-[10px]">
                <input type="checkbox" class="sla-check" wire:model="remember">
                <span class="text-[13px] font-medium text-[var(--color-ink-2)]">Ingat saya</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="sla-link" wire:navigate>
                    Lupa kata laluan?
                </a>
            @endif
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
            Akaun anda didaftarkan oleh pihak admin. Hubungi admin jika anda
            belum terima maklumat log masuk.
        </div>
    </div>
</div>
