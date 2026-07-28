# External System Provisioning — Setup Guide

When a paid order comes in from the Bedaie platform, it calls an external system to
**create the buyer's account and grant the plan they bought**. This is the guide for
whoever sets up (or maintains) one of those external systems.

```
BEDAIE PLATFORM (done)                    YOUR SYSTEM (what you set up)
order paid → signs + POSTs   ─────────▶   POST /api/v1/provision
  the standard payload                      create/find the user + grant the plan
                             ◀─────────      return the login details
stores login + notifies buyer
```

You do **not** hand-write the auth/idempotency/HTTP plumbing anymore — that lives in a
Composer package. You implement **one method** and paste **two secrets**.

---

## If your system is Laravel (recommended) — use the package

The package `bedaie/provisioning-receiver` gives you the `/api/v1/provision` endpoint,
Bearer + HMAC verification, idempotency, and the response format. You write one handler.

### 1. Install

The package is its own private repo: **github.com/aminpamelo/provisioning-receiver**.
Add it as a VCS repository in your external system's `composer.json`:

```jsonc
"repositories": [
    { "type": "vcs", "url": "https://github.com/aminpamelo/provisioning-receiver.git" }
],
```

```bash
# One-time (private repo) — let composer read it with a GitHub token:
composer config --global github-oauth.github.com <token>   # e.g. gh auth token

composer require bedaie/provisioning-receiver:^1.0
php artisan vendor:publish --tag=provisioning-receiver-config
php artisan migrate
```

Updating the contract later is `git tag v1.0.x` on the package repo, then
`composer update bedaie/provisioning-receiver` on each system.

### 2. Implement one method — create the user + grant the plan

```php
// app/Provisioning/GrantAccessHandler.php
class GrantAccessHandler implements \Bedaie\ProvisioningReceiver\Contracts\ProvisionHandler
{
    public function provision(\Bedaie\ProvisioningReceiver\ProvisionOrder $order): \Bedaie\ProvisioningReceiver\ProvisionResult
    {
        $user = User::firstOrCreate(['email' => $order->email], ['name' => $order->name]);
        $user->grantPlan($order->plan ?? 'default');          // ← this is what gives real access
        $token = $user->createMagicLoginToken();

        return \Bedaie\ProvisioningReceiver\ProvisionResult::make(
            externalUserId: $user->id,
            loginUrl: route('magic-login', $token),
            magicLink: route('magic-login', $token),
        );
    }
}
```

Then point the config at it: `config/provisioning-receiver.php` → `'handler' => GrantAccessHandler::class`.

> **Remember:** creating the user is not enough — the user needs the **plan/subscription
> activated** to actually have access. That is the handler's job. See the working reference
> in SunnahTracker: `app/Provisioning/GrantAccessHandler.php`.

### 3. Set the two shared secrets — **paste them in the UI, no `.env` editing**

The Bedaie platform generates two secrets (API Key + Signing Secret). Your system needs the
same two values. Two ways to store them:

- **Recommended — the "Sambungan Masuk" admin page** (paste in the UI, no server access):
  a small settings page saves the two secrets **encrypted** into your settings store, and a
  service provider overrides the package config from there. See the reference implementation
  in SunnahTracker:
  - Page: `resources/js/pages/admin/incoming-provisioning.tsx`
  - Controller: `app/Http/Controllers/Admin/IncomingProvisioningController.php`
  - Config override: `app/Providers/AppServiceProvider.php` (`applyIncomingProvisioningCredentials()`)
  - Stored in `site_settings` (keys `provisioning_api_key` / `provisioning_signing_secret`, `Crypt::encryptString`)

- **Or `.env`** (needs server access):
  ```dotenv
  PROVISIONING_API_KEY=...
  PROVISIONING_SIGNING_SECRET=...
  ```

### 4. Connect it to the platform

Give the platform admin your **Base URL** (e.g. `https://your-system.com`, use `https` for
TLS hosts) and **Provision Path** (default `/api/v1/provision`). They register it under
**Admin → External Systems → Add System**, click **Generate** to mint the secrets, and
**Copy** the `.env` block — those are the values you paste in step 3.

### 5. Verify

- On your side: `php artisan provisioning:test`, or the **"Uji Sambungan"** button on the
  Sambungan Masuk page (confirms your receiver stack is wired).
- On the platform side: **Test connection** on the system row (the authoritative check that
  the secrets match).

---

## If your system is NOT Laravel

There is no package, but the contract is identical. Implement one endpoint following the
[spec](#the-contract), using [`receiver.mjs`](receiver.mjs) (Node/Express) as a reference, and
[`selftest.sh`](selftest.sh) to test it.

---

## The contract

### Request (what the platform sends you)

```http
POST {base_url}/api/v1/provision
Authorization: Bearer <api_key>
X-Signature: <lowercase hex hmac_sha256(RAW_BODY, signing_secret)>
Content-Type: application/json

{
  "idempotency_key": "prov_123_45_1",
  "order_ref": "ORD-000123",
  "order_id": 123,
  "customer": { "email": "buyer@example.com", "name": "Buyer", "phone": "+60123456789" },
  "product":  { "funnel_product_id": 45, "name": "Premium", "sku": "PRE-1", "plan": "gold" }
}
```

### Response (what you return — HTTP 2xx)

```json
{
  "external_user_id": "9987",
  "login_url": "https://your-system.com/login/abc",
  "username": "buyer@example.com",
  "magic_link": "https://your-system.com/go/TOKEN"
}
```

Return `magic_link` **or** `temp_password`. The platform stores `external_user_id`,
`login_url`, and any credential fields, then delivers them to the buyer.

### The four rules (the package enforces these for you)

1. **Verify auth** — Bearer token *and* `X-Signature` (HMAC-SHA256 over the **raw body**,
   constant-time compare). Reject with `401` otherwise.
2. **Be idempotent** — dedupe on `idempotency_key`; a repeat returns the same result (the
   platform retries up to 3×).
3. **Create synchronously** — return the login details in the response (no async 202).
4. **Grant the plan** — activate the purchased plan/subscription, not just create the user.

---

## Where everything lives

| Piece | Location |
| --- | --- |
| The package | github.com/aminpamelo/provisioning-receiver (private, tagged v1.0.0) |
| Reference receiver (Laravel) | SunnahTracker: `app/Provisioning/GrantAccessHandler.php` + `config/provisioning-receiver.php` |
| Reference "Sambungan Masuk" UI | SunnahTracker: `IncomingProvisioningController` + `incoming-provisioning.tsx` + `AppServiceProvider` |
| Non-Laravel reference | [`receiver.mjs`](receiver.mjs), [`selftest.sh`](selftest.sh) |
| Platform side (sender) | `app/Services/ExternalProvisioning/*`, admin at `/admin/external-systems` |
