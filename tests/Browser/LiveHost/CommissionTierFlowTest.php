<?php

use App\Models\LiveHostCommissionProfile;
use App\Models\LiveHostPlatformCommissionTier;
use App\Models\Platform;
use App\Models\User;

/**
 * Browser coverage for the full commission tier flow on the Live Host
 * Desk host detail page (`/livehost/hosts/{host}`).
 *
 * Scope:
 *  - seeds an admin_livehost, a live_host, a TikTok Shop platform, a
 *    commission profile (fixed pay), and a 5-tier schedule matching the
 *    canonical spreadsheet.
 *  - loads the host show page, switches to the Commission tab.
 *  - asserts tier rows render.
 *  - drives the Monthly projection GMV input and asserts the tier
 *    badge + breakdown earnings update as expected at 80,000 MYR
 *    (Tier 3 · 6%) and 8,000 MYR (below Tier 1).
 *
 * The tier CRUD surface is already covered by HostTierControllerTest at
 * the HTTP layer, so this browser test deliberately seeds tiers via
 * factories rather than going through the add-tier-schedule form.
 */
function seedTikTokTierLadder(User $host, Platform $platform): void
{
    $tiers = [
        ['tier_number' => 1, 'min' => 15000, 'max' => 30000, 'internal' => 6, 'l1' => 1, 'l2' => 2],
        ['tier_number' => 2, 'min' => 30000, 'max' => 60000, 'internal' => 6, 'l1' => 1.3, 'l2' => 2.3],
        ['tier_number' => 3, 'min' => 60000, 'max' => 100000, 'internal' => 6, 'l1' => 1.5, 'l2' => 2.5],
        ['tier_number' => 4, 'min' => 100000, 'max' => 150000, 'internal' => 6, 'l1' => 1.7, 'l2' => 2.7],
        ['tier_number' => 5, 'min' => 150000, 'max' => null, 'internal' => 6, 'l1' => 2, 'l2' => 3],
    ];

    $effectiveFrom = now()->subDay()->toDateString();

    foreach ($tiers as $row) {
        LiveHostPlatformCommissionTier::factory()->create([
            'user_id' => $host->id,
            'platform_id' => $platform->id,
            'tier_number' => $row['tier_number'],
            'min_gmv_myr' => $row['min'],
            'max_gmv_myr' => $row['max'],
            'internal_percent' => $row['internal'],
            'l1_percent' => $row['l1'],
            'l2_percent' => $row['l2'],
            'effective_from' => $effectiveFrom,
            'is_active' => true,
        ]);
    }
}

it('renders the tier ladder and drives the Monthly projection slider', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $host = User::factory()->create(['role' => 'live_host', 'name' => 'Projection Host']);

    $platform = Platform::factory()->create([
        'slug' => 'tiktok-shop',
        'name' => 'TikTok Shop',
        'is_active' => true,
    ]);

    LiveHostCommissionProfile::factory()->create([
        'user_id' => $host->id,
        'base_salary_myr' => 1800,
        'per_live_rate_myr' => 30,
        'override_rate_l1_percent' => 1,
        'override_rate_l2_percent' => 2,
        'upline_user_id' => null,
        'is_active' => true,
        'effective_from' => now()->subDay(),
    ]);

    seedTikTokTierLadder($host, $platform);

    $this->actingAs($pic);

    $page = visit("/livehost/hosts/{$host->id}");

    // Host hero + tab navigation render.
    $page
        ->assertNoJavascriptErrors()
        ->assertSee('Projection Host');

    // Switch to the Commission tab. We can't use click('Commission') here
    // because the sidebar also has a "Commission" link that would be
    // matched first and navigate away from the host detail page. Target
    // the in-page tab button by walking the DOM for a <button type="button">
    // whose visible label is exactly "Commission".
    $page->script(
        <<<'JS'
        (() => {
          const btn = Array.from(document.querySelectorAll('button[type="button"]'))
            .find(el => el.textContent.trim() === 'Commission');
          if (btn) { btn.click(); }
        })();
        JS
    );

    $page
        ->assertSee('Commission tiers')
        ->assertSee('TikTok Shop');

    // Tier ladder rows render (T1..T5 + internal percent).
    $page
        ->assertSee('T1')
        ->assertSee('T2')
        ->assertSee('T3')
        ->assertSee('T4')
        ->assertSee('T5');

    // Monthly projection — set GMV to 80,000 and assert Tier 3 badge +
    // the "Your earnings" breakdown row (80000 * 6% = 4,800).
    $page
        ->fill('input[type="number"][max="500000"]', '80000')
        ->assertSee('T3')
        ->assertSee('60K–100K')
        ->assertSee('Your earnings · 6% × GMV')
        ->assertSee('RM 4,800')
        ->assertNoJavascriptErrors();

    // Drop GMV below Tier 1 floor (15,000) — should flip the badge to
    // "Below Tier 1" and drive performance pay to RM 0.
    $page
        ->fill('input[type="number"][max="500000"]', '8000')
        ->assertSee('Below Tier 1')
        ->assertSee('Your earnings · performance commission')
        ->assertNoJavascriptErrors();
});
