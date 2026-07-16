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
 * Dedicated Fighter login page.
 *
 * Auth logic mirrors the generic auth.login Volt — phone-or-email + password,
 * rate-limited, remember-me, intended-URL redirect. Only the layout + copy
 * differ: fighters land on a branded orange page matching their workspace.
 *
 * Post-login routing is handled by the `dashboard` route's role switch, which
 * redirects `fighter` users to `fighter.dashboard`. Any role CAN sign in here;
 * they just land on whichever dashboard matches their role.
 */
new #[Layout('components.layouts.fighter-auth')] class extends Component {
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], attributes: [
            'login' => 'phone number or email',
            'password' => 'password',
        ]);

        $this->ensureIsNotRateLimited();

        $loginField = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$loginField => $this->login, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records. Please try again.',
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
            'login' => "Too many attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }
}; ?>

<div>
    {{-- Row 1: FIGHTER pill + brand mark --}}
    <div class="flex items-center justify-between">
        <span class="fla-badge">Fighter</span>

        <div class="inline-flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-xl bg-gradient-to-br from-[#FB923C] to-[#F43F5E] text-white shadow-[0_8px_20px_-8px_rgba(249,115,22,0.7)]">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5" />
                    <line x1="13" y1="19" x2="19" y2="13" />
                    <line x1="16" y1="16" x2="20" y2="20" />
                    <line x1="19" y1="21" x2="21" y2="19" />
                    <polyline points="14.5 6.5 18 3 21 3 21 6 17.5 9.5" />
                    <line x1="5" y1="14" x2="9" y2="18" />
                    <line x1="7" y1="17" x2="4" y2="20" />
                    <line x1="3" y1="19" x2="5" y2="21" />
                </svg>
            </span>
            <span class="text-[14px] font-semibold tracking-[-0.02em] text-white">Bedaie Fighter</span>
        </div>
    </div>

    {{-- Row 2: Headline --}}
    <div class="mt-6">
        <h1 class="text-[30px] font-bold leading-[1.05] tracking-[-0.025em] text-white sm:text-[32px]">
            Welcome, fighter!<br>
            <span class="bg-gradient-to-r from-amber-200 via-orange-300 to-rose-200 bg-clip-text text-transparent">
                Ready to run your funnels?
            </span>
        </h1>
        <p class="mt-[10px] text-[14px] leading-[1.5] text-white/60">
            Log in to build funnels, track performance and watch your orders.
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

        <div class="fla-field">
            <input
                id="fla-login"
                type="text"
                class="fla-field-input"
                wire:model.live="login"
                required
                autofocus
                autocomplete="username"
                placeholder=" "
            >
            <label for="fla-login" class="fla-field-label">
                Phone number or email
            </label>
            @error('login')
                <div class="fla-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="fla-field" x-data="{ visible: false }">
            <input
                id="fla-password"
                class="fla-field-input"
                :type="visible ? 'text' : 'password'"
                wire:model.live="password"
                required
                autocomplete="current-password"
                placeholder=" "
            >
            <label for="fla-password" class="fla-field-label">
                Password
            </label>
            <button
                type="button"
                @click="visible = !visible"
                class="fla-pw-toggle"
                aria-label="Show or hide password"
            >
                <span x-show="!visible">Show</span>
                <span x-show="visible" x-cloak>Hide</span>
            </button>
            @error('password')
                <div class="fla-error" role="alert">
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-[2px]">
            <label class="flex cursor-pointer select-none items-center gap-[10px]">
                <input type="checkbox" class="fla-check" wire:model="remember">
                <span class="text-[13px] font-medium text-white/70">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="fla-link" wire:navigate>
                    Forgot password?
                </a>
            @endif
        </div>

        <button
            type="submit"
            class="fla-cta mt-2"
            wire:loading.attr="disabled"
            wire:target="authenticate"
        >
            <span class="relative z-10 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="authenticate">Log in</span>
                <span wire:loading wire:target="authenticate" class="flex items-center gap-2">
                    <span class="inline-block h-[12px] w-[12px] animate-spin rounded-full border-2 border-white/35 border-t-white" aria-hidden="true"></span>
                    Logging in…
                </span>
                <svg wire:loading.remove wire:target="authenticate" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
        </button>
    </form>

    {{-- Row 5: Helper chip --}}
    <div class="mt-6 flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
        <div class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-orange-500/20 text-orange-200">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8v4m0 4h.01M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <div class="text-[12px] leading-[1.5] text-white/60">
            Your account is set up by the admin. Contact them if you haven't received your login details.
        </div>
    </div>
</div>
