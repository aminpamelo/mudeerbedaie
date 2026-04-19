<?php

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\User;

it('renders platform accounts index page with data', function () {
    $pic = User::factory()->create(['role' => 'admin_livehost']);
    $platform = Platform::factory()->create(['name' => 'Shopee', 'slug' => 'shopee']);
    PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'name' => "Sarah Chen's Shopee",
        'is_active' => true,
    ]);
    PlatformAccount::factory()->create([
        'platform_id' => $platform->id,
        'name' => 'Dim Sum TikTok Studio',
        'is_active' => false,
    ]);

    $this->actingAs($pic);

    visit('/livehost/platform-accounts')
        ->assertSee('Platform Accounts')
        ->assertSee("Sarah Chen's Shopee")
        ->assertSee('Dim Sum TikTok Studio')
        ->assertNoJavascriptErrors();
});
