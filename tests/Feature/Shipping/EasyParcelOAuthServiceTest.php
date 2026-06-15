<?php

declare(strict_types=1);

use App\Services\SettingsService;
use App\Services\Shipping\EasyParcelOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function setCredentials(): void
{
    $s = app(SettingsService::class);
    $s->set('easyparcel_client_id', 'CID', 'encrypted', 'shipping');
    $s->set('easyparcel_client_secret', 'SECRET', 'encrypted', 'shipping');
}

function oauth(): EasyParcelOAuthService
{
    return app(EasyParcelOAuthService::class);
}

it('builds an authorize URL with client_id, redirect_uri and state', function () {
    setCredentials();

    $url = oauth()->authorizeUrl('https://app.test/callback', 'xyz');

    expect($url)->toStartWith('https://api.easyparcel.com/oauth/login?')
        ->and($url)->toContain('client_id=CID')
        ->and($url)->toContain('redirect_uri='.urlencode('https://app.test/callback'))
        ->and($url)->toContain('state=xyz');
});

it('exchanges an authorization code for tokens with Basic auth', function () {
    setCredentials();

    Http::fake(['*/oauth/token' => Http::response([
        'token_type' => 'Bearer',
        'expires_in' => 36000,
        'access_token' => 'ACCESS-1',
        'refresh_token' => 'REFRESH-1',
    ])]);

    expect(oauth()->exchangeCode('the-code', 'https://app.test/callback'))->toBeTrue();

    $s = app(SettingsService::class);
    expect($s->isEasyParcelConnected())->toBeTrue()
        ->and($s->get('easyparcel_access_token'))->toBe('ACCESS-1')
        ->and($s->get('easyparcel_refresh_token'))->toBe('REFRESH-1');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/oauth/token')
        && $req->hasHeader('Authorization', 'Basic '.base64_encode('CID:SECRET'))
        && $req['grant_type'] === 'authorization_code'
        && $req['code'] === 'the-code');
});

it('returns the stored access token while it is still fresh without calling the API', function () {
    setCredentials();
    $s = app(SettingsService::class);
    $s->setEasyParcelTokens('FRESH-TOKEN', 'REFRESH-1', now()->addHours(5)->toIso8601String());

    Http::fake();

    expect(oauth()->accessToken())->toBe('FRESH-TOKEN');
    Http::assertNothingSent();
});

it('refreshes an expired access token using the refresh token', function () {
    setCredentials();
    $s = app(SettingsService::class);
    $s->setEasyParcelTokens('OLD-TOKEN', 'REFRESH-1', now()->subMinute()->toIso8601String());

    Http::fake(['*/oauth/token' => Http::response([
        'token_type' => 'Bearer',
        'expires_in' => 36000,
        'access_token' => 'NEW-TOKEN',
        'refresh_token' => 'REFRESH-2',
    ])]);

    expect(oauth()->accessToken())->toBe('NEW-TOKEN')
        ->and($s->get('easyparcel_refresh_token'))->toBe('REFRESH-2');

    Http::assertSent(fn ($req) => $req['grant_type'] === 'refresh_token' && $req['refresh_token'] === 'REFRESH-1');
});

it('returns null when the account is not connected', function () {
    setCredentials();
    Http::fake();

    expect(oauth()->accessToken())->toBeNull();
});
