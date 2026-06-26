<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveCustomDomain;
use App\Models\CustomDomain;
use App\Models\Funnel;
use App\Models\User;
use App\Services\CloudflareCustomHostnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ─────────────────────────────────────────────────────────────────
// API Tests — Custom Domain CRUD
// ─────────────────────────────────────────────────────────────────

test('user can add subdomain to funnel', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$funnel->uuid}/custom-domain", [
            'domain' => 'mybrand',
            'type' => 'subdomain',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.verification_status', 'active')
        ->assertJsonPath('data.ssl_status', 'active')
        ->assertJsonPath('data.type', 'subdomain')
        ->assertJsonPath('data.domain', 'mybrand');

    $this->assertDatabaseHas('custom_domains', [
        'funnel_id' => $funnel->id,
        'domain' => 'mybrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);
});

test('user can add custom domain to funnel', function () {
    $mock = $this->mock(CloudflareCustomHostnameService::class);
    $mock->shouldReceive('createHostname')
        ->once()
        ->with('checkout.mybrand.com')
        ->andReturn([
            'id' => 'cf-hostname-123',
            'status' => 'pending',
            'ssl_status' => 'pending',
            'verification_errors' => [],
        ]);

    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$funnel->uuid}/custom-domain", [
            'domain' => 'checkout.mybrand.com',
            'type' => 'custom',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.verification_status', 'pending')
        ->assertJsonPath('data.ssl_status', 'pending')
        ->assertJsonPath('data.type', 'custom');

    $this->assertDatabaseHas('custom_domains', [
        'domain' => 'checkout.mybrand.com',
        'cloudflare_hostname_id' => 'cf-hostname-123',
        'verification_status' => 'pending',
        'ssl_status' => 'pending',
    ]);
});

test('user cannot add duplicate domain', function () {
    $funnel1 = Funnel::factory()->published()->create(['user_id' => $this->user->id]);
    $funnel2 = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $funnel1->id,
        'user_id' => $this->user->id,
        'domain' => 'mybrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$funnel2->uuid}/custom-domain", [
            'domain' => 'mybrand',
            'type' => 'subdomain',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('domain');
});

test('duplicate domain error names the owning funnel for its owner', function () {
    $owned = Funnel::factory()->published()->create(['user_id' => $this->user->id, 'name' => 'Untuk Muslimah']);
    $target = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $owned->id,
        'user_id' => $this->user->id,
        'domain' => 'untukmuwanita',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$target->uuid}/custom-domain", [
            'domain' => 'untukmuwanita',
            'type' => 'subdomain',
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('domain');
    $message = $response->json('errors.domain.0');
    expect($message)->toContain('Untuk Muslimah')
        ->and($message)->toContain($owned->uuid);
});

test('duplicate domain owned by another account is not named for a non-admin', function () {
    $other = User::factory()->create();
    $otherFunnel = Funnel::factory()->published()->create(['user_id' => $other->id, 'name' => 'Secret Brand']);
    $mine = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $otherFunnel->id,
        'user_id' => $other->id,
        'domain' => 'takenname',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$mine->uuid}/custom-domain", [
            'domain' => 'takenname',
            'type' => 'subdomain',
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('domain');
    $message = $response->json('errors.domain.0');
    expect($message)->not->toContain('Secret Brand')
        ->and($message)->toContain('another account');
});

test('admin sees the owning funnel name even across accounts', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->create();
    $otherFunnel = Funnel::factory()->published()->create(['user_id' => $other->id, 'name' => 'Cross Acct']);
    $adminFunnel = Funnel::factory()->published()->create(['user_id' => $admin->id]);

    CustomDomain::create([
        'funnel_id' => $otherFunnel->id,
        'user_id' => $other->id,
        'domain' => 'crosstaken',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/funnels/{$adminFunnel->uuid}/custom-domain", [
            'domain' => 'crosstaken',
            'type' => 'subdomain',
        ]);

    $response->assertUnprocessable();
    expect($response->json('errors.domain.0'))->toContain('Cross Acct');
});

test('soft-deleting a funnel releases its custom domain', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);
    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'releaseme',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $funnel->delete();

    $this->assertSoftDeleted('custom_domains', ['id' => $domain->id]);
});

test('a subdomain orphaned by a deleted funnel can be reclaimed', function () {
    $ghost = Funnel::factory()->published()->create();
    CustomDomain::create([
        'funnel_id' => $ghost->id,
        'user_id' => $ghost->user_id,
        'domain' => 'reclaimme',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    // Simulate a legacy orphan (funnel soft-deleted before the release fix existed):
    // soft-delete the funnel directly so its still-live domain row is left behind.
    DB::table('funnels')->where('id', $ghost->id)->update(['deleted_at' => now()]);

    $newFunnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$newFunnel->uuid}/custom-domain", [
            'domain' => 'reclaimme',
            'type' => 'subdomain',
        ]);

    $response->assertCreated()->assertJsonPath('data.domain', 'reclaimme');
    $this->assertDatabaseHas('custom_domains', ['funnel_id' => $newFunnel->id, 'domain' => 'reclaimme']);
});

test('user cannot add domain to funnel that already has one', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'existing',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$funnel->uuid}/custom-domain", [
            'domain' => 'newbrand',
            'type' => 'subdomain',
        ]);

    $response->assertUnprocessable()
        ->assertJsonFragment(['message' => 'This funnel already has a custom domain. Remove it first.']);
});

test('user can remove a custom domain with cloudflare cleanup', function () {
    $mock = $this->mock(CloudflareCustomHostnameService::class);
    $mock->shouldReceive('deleteHostname')
        ->once()
        ->with('cf-hostname-456')
        ->andReturn(true);

    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'checkout.mybrand.com',
        'type' => 'custom',
        'cloudflare_hostname_id' => 'cf-hostname-456',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/funnels/{$funnel->uuid}/custom-domain");

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Custom domain removed successfully']);

    $this->assertSoftDeleted('custom_domains', ['id' => $domain->id]);
});

test('user can remove a subdomain without cloudflare call', function () {
    $mock = $this->mock(CloudflareCustomHostnameService::class);
    $mock->shouldNotReceive('deleteHostname');

    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'mybrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/funnels/{$funnel->uuid}/custom-domain");

    $response->assertOk();
    $this->assertSoftDeleted('custom_domains', ['id' => $domain->id]);
});

test('user can check domain status via cloudflare polling', function () {
    $mock = $this->mock(CloudflareCustomHostnameService::class);
    $mock->shouldReceive('getHostnameStatus')
        ->once()
        ->with('cf-hostname-789')
        ->andReturn([
            'status' => 'active',
            'ssl_status' => 'active',
            'verification_errors' => [],
        ]);

    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'checkout.mybrand.com',
        'type' => 'custom',
        'cloudflare_hostname_id' => 'cf-hostname-789',
        'verification_status' => 'pending',
        'ssl_status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/funnels/{$funnel->uuid}/custom-domain/check-status");

    $response->assertOk()
        ->assertJsonPath('data.verification_status', 'active')
        ->assertJsonPath('data.ssl_status', 'active')
        ->assertJsonPath('data.is_active', true);
});

test('user can get custom domain for a funnel', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'mybrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/funnels/{$funnel->uuid}/custom-domain");

    $response->assertOk()
        ->assertJsonPath('data.domain', 'mybrand')
        ->assertJsonPath('data.type', 'subdomain')
        ->assertJsonPath('data.verification_status', 'active')
        ->assertJsonPath('data.is_active', true);
});

test('user gets null when funnel has no domain', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/funnels/{$funnel->uuid}/custom-domain");

    $response->assertOk()
        ->assertJsonPath('data', null);
});

// ─────────────────────────────────────────────────────────────────
// Public URL — preview/copy use the live custom domain
// ─────────────────────────────────────────────────────────────────

test('public url uses an active subdomain for a published funnel', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id, 'slug' => 'haid']);

    CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'untukmuwanita',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    expect($funnel->fresh()->getPublicUrl())->toBe('https://untukmuwanita.kelasify.com');
});

test('public url falls back to /f/slug without an active domain', function () {
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id, 'slug' => 'plainfunnel']);

    expect($funnel->getPublicUrl())->toBe(url('/f/plainfunnel'));
});

test('public url ignores a pending domain and a draft funnel', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);

    $pending = Funnel::factory()->published()->create(['user_id' => $this->user->id, 'slug' => 'pendingfunnel']);
    CustomDomain::create([
        'funnel_id' => $pending->id,
        'user_id' => $this->user->id,
        'domain' => 'pendingbrand.com',
        'type' => 'custom',
        'cloudflare_hostname_id' => 'cf-x',
        'verification_status' => 'pending',
        'ssl_status' => 'pending',
    ]);
    expect($pending->fresh()->getPublicUrl())->toBe(url('/f/pendingfunnel'));

    $draft = Funnel::factory()->draft()->create(['user_id' => $this->user->id, 'slug' => 'draftfunnel']);
    CustomDomain::create([
        'funnel_id' => $draft->id,
        'user_id' => $this->user->id,
        'domain' => 'draftbrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);
    expect($draft->fresh()->getPublicUrl())->toBe(url('/f/draftfunnel'));
});

test('funnel api url reflects the active custom subdomain', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);
    $funnel = Funnel::factory()->published()->create(['user_id' => $this->user->id]);

    CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $this->user->id,
        'domain' => 'apibrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/funnels/{$funnel->uuid}")
        ->assertOk()
        ->assertJsonPath('data.url', 'https://apibrand.kelasify.com');
});

// ─────────────────────────────────────────────────────────────────
// Middleware Tests — ResolveCustomDomain
// ─────────────────────────────────────────────────────────────────

test('middleware resolves subdomain correctly', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);

    $funnel = Funnel::factory()->published()->create();

    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $funnel->user_id,
        'domain' => 'testbrand',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('HOST', 'testbrand.kelasify.com');

    $middleware = new ResolveCustomDomain;
    $resolvedRequest = null;

    $middleware->handle($request, function ($req) use (&$resolvedRequest) {
        $resolvedRequest = $req;

        return response('ok');
    });

    expect($resolvedRequest->attributes->get('custom_domain'))->not->toBeNull();
    expect($resolvedRequest->attributes->get('custom_domain')->domain)->toBe('testbrand');
    expect($resolvedRequest->attributes->get('custom_domain_funnel_id'))->toBe($funnel->id);
});

test('middleware resolves custom domain correctly', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);

    $funnel = Funnel::factory()->published()->create();

    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $funnel->user_id,
        'domain' => 'checkout.mybrand.com',
        'type' => 'custom',
        'cloudflare_hostname_id' => 'cf-test-123',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('HOST', 'checkout.mybrand.com');

    $middleware = new ResolveCustomDomain;
    $resolvedRequest = null;

    $middleware->handle($request, function ($req) use (&$resolvedRequest) {
        $resolvedRequest = $req;

        return response('ok');
    });

    expect($resolvedRequest->attributes->get('custom_domain'))->not->toBeNull();
    expect($resolvedRequest->attributes->get('custom_domain')->domain)->toBe('checkout.mybrand.com');
    expect($resolvedRequest->attributes->get('custom_domain_funnel_id'))->toBe($funnel->id);
});

test('middleware returns 404 for unknown domains', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);

    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('HOST', 'unknown.mybrand.com');

    $middleware = new ResolveCustomDomain;

    $middleware->handle($request, function ($req) {
        return response('ok');
    });
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

test('middleware skips main app domain (.test domains)', function () {
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('HOST', 'myapp.test');

    $middleware = new ResolveCustomDomain;
    $response = $middleware->handle($request, function ($req) {
        return response('ok');
    });

    expect($response->getContent())->toBe('ok');
});

test('middleware skips localhost', function () {
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->headers->set('HOST', 'localhost');

    $middleware = new ResolveCustomDomain;
    $response = $middleware->handle($request, function ($req) {
        return response('ok');
    });

    expect($response->getContent())->toBe('ok');
});

// ─────────────────────────────────────────────────────────────────
// Model Tests — CustomDomain
// ─────────────────────────────────────────────────────────────────

test('custom domain auto-generates UUID on creation', function () {
    $funnel = Funnel::factory()->published()->create();

    $domain = CustomDomain::create([
        'funnel_id' => $funnel->id,
        'user_id' => $funnel->user_id,
        'domain' => 'testauto',
        'type' => 'subdomain',
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    expect($domain->uuid)->not->toBeNull();
    expect($domain->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('isActive returns true when both verification and ssl are active', function () {
    $domain = new CustomDomain([
        'verification_status' => 'active',
        'ssl_status' => 'active',
    ]);

    expect($domain->isActive())->toBeTrue();
});

test('isActive returns false when verification is pending', function () {
    $domain = new CustomDomain([
        'verification_status' => 'pending',
        'ssl_status' => 'active',
    ]);

    expect($domain->isActive())->toBeFalse();
});

test('isActive returns false when ssl is pending', function () {
    $domain = new CustomDomain([
        'verification_status' => 'active',
        'ssl_status' => 'pending',
    ]);

    expect($domain->isActive())->toBeFalse();
});

test('isPending returns true when verification_status is pending', function () {
    $domain = new CustomDomain([
        'verification_status' => 'pending',
    ]);

    expect($domain->isPending())->toBeTrue();
});

test('isPending returns false when verification_status is active', function () {
    $domain = new CustomDomain([
        'verification_status' => 'active',
    ]);

    expect($domain->isPending())->toBeFalse();
});

test('isFailed returns true when verification_status is failed', function () {
    $domain = new CustomDomain([
        'verification_status' => 'failed',
        'ssl_status' => 'active',
    ]);

    expect($domain->isFailed())->toBeTrue();
});

test('isFailed returns true when ssl_status is failed', function () {
    $domain = new CustomDomain([
        'verification_status' => 'active',
        'ssl_status' => 'failed',
    ]);

    expect($domain->isFailed())->toBeTrue();
});

test('full_domain attribute returns domain with base for subdomains', function () {
    config(['services.cloudflare.subdomain_base' => 'kelasify.com']);

    $domain = new CustomDomain([
        'domain' => 'mybrand',
        'type' => 'subdomain',
    ]);

    expect($domain->full_domain)->toBe('mybrand.kelasify.com');
});

test('full_domain attribute returns raw domain for custom domains', function () {
    $domain = new CustomDomain([
        'domain' => 'checkout.mybrand.com',
        'type' => 'custom',
    ]);

    expect($domain->full_domain)->toBe('checkout.mybrand.com');
});

test('isSubdomain returns true for subdomain type', function () {
    $domain = new CustomDomain(['type' => 'subdomain']);

    expect($domain->isSubdomain())->toBeTrue();
});

test('isSubdomain returns false for custom type', function () {
    $domain = new CustomDomain(['type' => 'custom']);

    expect($domain->isSubdomain())->toBeFalse();
});
