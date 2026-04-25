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
 * Dedicated Live Host login page (Bahasa Melayu).
 *
 * Auth logic mirrors the generic auth.login Volt — phone-or-email + password,
 * rate-limited, remember-me, intended-URL redirect. The only difference is
 * the mounted layout + copy: hosts land on a branded BM page rather than
 * the shared simple-auth layout.
 *
 * Post-login routing is handled by the `dashboard` route's role switch,
 * which already redirects `live_host` users to `live-host.dashboard`. So
 * any role CAN successfully sign in here — they just land on whichever
 * dashboard matches their role.
 */
new #[Layout('components.layouts.live-host-auth')] class extends Component {
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
    {{-- Row 1: LIVE pill + BeDaie brand --}}
    <div class="flex items-center justify-between">
        <span class="lha-live">
            <span class="lha-live-diode" aria-hidden="true"></span>
            LIVE HOST
        </span>

        <div class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 shadow-sm shadow-black/30">
            <img
                src="{{ asset('images/bedaie-brand.png') }}"
                alt="BeDaie"
                class="h-5 w-auto select-none"
                draggable="false"
            >
        </div>
    </div>

    {{-- Row 2: Friendly headline --}}
    <div class="mt-6">
        <h1 class="text-[30px] font-bold leading-[1.05] tracking-[-0.025em] text-white sm:text-[32px]">
            Hai, hos!<br>
            <span class="bg-gradient-to-r from-fuchsia-200 via-violet-300 to-indigo-200 bg-clip-text text-transparent">
                Dah sedia untuk live?
            </span>
        </h1>
        <p class="mt-[10px] text-[14px] leading-[1.5] text-white/60">
            Log masuk untuk semak jadual, ringkasan sesi dan komisen anda.
        </p>
    </div>

    {{-- Row 3: Status flash (e.g. after password reset) --}}
    @if (session('status'))
        <div class="mt-5 flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-3 py-2 text-[12.5px] font-medium text-emerald-200">
            <span class="h-[6px] w-[6px] rounded-full bg-emerald-300" aria-hidden="true"></span>
            {{ session('status') }}
        </div>
    @endif

    {{-- Row 4: Form --}}
    <form wire:submit.prevent="authenticate" class="mt-6 flex flex-col gap-3" novalidate>

        <div class="lha-field">
            <input
                id="lha-login"
                type="text"
                class="lha-field-input"
                wire:model.live="login"
                required
                autofocus
                autocomplete="username"
                placeholder=" "
            >
            <label for="lha-login" class="lha-field-label">
                Nombor telefon atau emel
            </label>
            @error('login')
                <div class="lha-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="lha-field" x-data="{ visible: false }">
            <input
                id="lha-password"
                class="lha-field-input"
                :type="visible ? 'text' : 'password'"
                wire:model.live="password"
                required
                autocomplete="current-password"
                placeholder=" "
            >
            <label for="lha-password" class="lha-field-label">
                Kata laluan
            </label>
            <button
                type="button"
                @click="visible = !visible"
                class="lha-pw-toggle"
                aria-label="Tunjuk atau sorok kata laluan"
            >
                <span x-show="!visible">Tunjuk</span>
                <span x-show="visible" x-cloak>Sorok</span>
            </button>
            @error('password')
                <div class="lha-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-[2px]">
            <label class="flex cursor-pointer select-none items-center gap-[10px]">
                <input type="checkbox" class="lha-check" wire:model="remember">
                <span class="text-[13px] font-medium text-white/70">Ingat saya</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="lha-link" wire:navigate>
                    Lupa kata laluan?
                </a>
            @endif
        </div>

        <button
            type="submit"
            class="lha-cta mt-2"
            wire:loading.attr="disabled"
            wire:target="authenticate"
        >
            <span class="relative z-10 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="authenticate">Log masuk</span>
                <span wire:loading wire:target="authenticate" class="flex items-center gap-2">
                    <span class="inline-block h-[12px] w-[12px] animate-spin rounded-full border-2 border-white/35 border-t-white" aria-hidden="true"></span>
                    Sedang log masuk…
                </span>
                <svg wire:loading.remove wire:target="authenticate" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </button>
    </form>

    {{-- Row 5: Soft helper chip — reassurance copy --}}
    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
        <div class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-violet-500/20 text-violet-200">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8v4m0 4h.01M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <div class="text-[12px] leading-[1.5] text-white/60">
            Akaun anda didaftarkan oleh PIC. Hubungi PIC anda jika belum
            terima maklumat log masuk.
        </div>
    </div>
</div>
