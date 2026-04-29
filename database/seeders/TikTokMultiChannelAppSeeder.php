<?php

namespace Database\Seeders;

use App\Models\Platform;
use App\Models\PlatformApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TikTokMultiChannelAppSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            $this->command->warn('TikTok Shop platform not found. Skipping.');

            return;
        }

        $appKey = config('services.tiktok.app_key');
        $appSecret = config('services.tiktok.app_secret');

        if (empty($appKey) || empty($appSecret)) {
            $this->command->warn('TIKTOK_APP_KEY/TIKTOK_APP_SECRET not set. Skipping seed.');

            return;
        }

        $app = PlatformApp::firstOrNew([
            'platform_id' => $platform->id,
            'category' => PlatformApp::CATEGORY_MULTI_CHANNEL,
        ]);

        if (! $app->exists) {
            $app->fill([
                'slug' => 'tiktok-multi-channel',
                'name' => 'TikTok Multi-Channel Management',
                'app_key' => $appKey,
                'encrypted_app_secret' => Crypt::encryptString($appSecret),
                'redirect_uri' => config('services.tiktok.redirect_uri'),
                'is_active' => true,
                'scopes' => [],
                'metadata' => ['seeded_from' => 'env'],
            ])->save();

            $this->command->info("Created Multi-Channel app row id={$app->id}");
        }

        $backfilled = DB::table('platform_api_credentials')
            ->where('platform_id', $platform->id)
            ->whereNull('platform_app_id')
            ->update(['platform_app_id' => $app->id]);

        $this->command->info("Backfilled {$backfilled} credentials with platform_app_id={$app->id}");
    }
}
