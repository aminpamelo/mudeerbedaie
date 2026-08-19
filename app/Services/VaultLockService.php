<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

/**
 * Manages the shared "vault password" gate that sits in front of the Password
 * Vault, independent of the normal admin login.
 *
 * A single hashed password is stored in the settings table. Once an admin
 * enters it correctly, the current session is marked unlocked for the rest of
 * the login session. Rotating the password (e.g. when a staff member leaves)
 * changes the derived unlock token, which immediately re-locks every other
 * session that was unlocked against the old password.
 */
class VaultLockService
{
    private const SETTING_KEY = 'vault.access_password';

    private const SESSION_KEY = 'vault.unlock_token';

    /**
     * The stored bcrypt hash of the vault password, or null if never set.
     */
    public function passwordHash(): ?string
    {
        return Setting::getValue(self::SETTING_KEY);
    }

    /**
     * Whether a vault password has been configured yet.
     */
    public function isConfigured(): bool
    {
        return filled($this->passwordHash());
    }

    /**
     * A short token derived from the current password hash. Baking the hash
     * into the token means changing the password invalidates every existing
     * unlocked session automatically.
     */
    public function currentToken(): ?string
    {
        $hash = $this->passwordHash();

        return $hash ? hash('sha256', $hash) : null;
    }

    /**
     * Verify a plain-text attempt against the stored hash.
     */
    public function verify(string $password): bool
    {
        $hash = $this->passwordHash();

        return $hash !== null && Hash::check($password, $hash);
    }

    /**
     * Store a new vault password (hashed).
     */
    public function setPassword(string $password): void
    {
        Setting::setValue(self::SETTING_KEY, Hash::make($password), 'string', 'vault');
    }

    /**
     * Mark the current session as unlocked against the current password.
     */
    public function unlock(): void
    {
        session()->put(self::SESSION_KEY, $this->currentToken());
    }

    /**
     * Re-lock the current session.
     */
    public function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Whether the current session may see vault contents.
     */
    public function isUnlocked(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return session(self::SESSION_KEY) === $this->currentToken();
    }
}
