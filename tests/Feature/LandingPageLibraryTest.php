<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\LandingPageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pageContent(string $text = 'Hello'): array
{
    return ['content' => [['type' => 'Heading', 'props' => ['id' => 'h1', 'text' => $text]]], 'root' => ['props' => []]];
}

function stepWithPage(array $funnelAttrs = [], string $type = 'landing', bool $published = true): FunnelStep
{
    $funnel = Funnel::factory()->create($funnelAttrs);
    $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'type' => $type, 'name' => 'Sales Page']);
    $step->contents()->create(['content' => pageContent('Original'), 'version' => 1, 'is_published' => $published, 'published_at' => $published ? now() : null]);

    return $step;
}

it('saves a funnel step page into the library and links the step', function () {
    $admin = User::factory()->admin()->create();
    $step = stepWithPage();

    $this->actingAs($admin)
        ->postJson("/api/v1/funnels/{$step->funnel->uuid}/steps/{$step->id}/save-as-landing-template", ['name' => 'Long Sales', 'category' => 'sales'])
        ->assertCreated();

    $template = LandingPageTemplate::sole();
    expect($template->content['content'][0]['props']['text'])->toBe('Original')
        ->and($step->fresh()->landing_page_template_id)->toBe($template->id);
});

it('refuses to save a step with no page content', function () {
    $funnel = Funnel::factory()->create();
    $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/funnels/{$funnel->uuid}/steps/{$step->id}/save-as-landing-template", ['name' => 'X'])
        ->assertStatus(422);
});

it('applies a template as a new draft without touching the live page', function () {
    $step = stepWithPage(published: true);
    $template = LandingPageTemplate::factory()->create(['content' => pageContent('From template')]);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson("/api/v1/funnels/{$step->funnel->uuid}/steps/{$step->id}/landing-template", ['template_id' => $template->id])
        ->assertOk();

    $step->refresh();
    expect($step->publishedContent->content['content'][0]['props']['text'])->toBe('Original')
        ->and($step->draftContent->content['content'][0]['props']['text'])->toBe('From template')
        ->and($step->draftContent->is_published)->toBeFalse()
        ->and($step->landing_page_template_id)->toBe($template->id)
        ->and($template->fresh()->usage_count)->toBe(1);
});

it('blocks a fighter from applying a template to someone else\'s funnel', function () {
    $fighter = User::factory()->create(['role' => 'fighter']);
    $step = stepWithPage(['user_id' => User::factory()->create()->id]);
    $template = LandingPageTemplate::factory()->create();

    $this->actingAs($fighter)
        ->postJson("/api/v1/funnels/{$step->funnel->uuid}/steps/{$step->id}/landing-template", ['template_id' => $template->id])
        ->assertForbidden();
});

it('imports existing landing pages once', function () {
    $admin = User::factory()->admin()->create();
    stepWithPage(['name' => 'Haid Malaysia']);
    stepWithPage(['name' => 'Checkout Only'], type: 'checkout');

    $this->actingAs($admin)->postJson('/api/v1/landing-templates/import')->assertOk();
    $this->actingAs($admin)->postJson('/api/v1/landing-templates/import')->assertOk();

    expect(LandingPageTemplate::count())->toBe(1)
        ->and(LandingPageTemplate::sole()->name)->toBe('Haid Malaysia — Sales Page');
});

it('lets only admin/employee import', function () {
    $this->actingAs(User::factory()->create(['role' => 'fighter']))
        ->postJson('/api/v1/landing-templates/import')
        ->assertForbidden();
});

it('lists templates with the funnels using them', function () {
    $step = stepWithPage(['name' => 'Qadha Solat']);
    $template = LandingPageTemplate::factory()->create();
    $step->update(['landing_page_template_id' => $template->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/api/v1/landing-templates')
        ->assertOk()
        ->assertJsonPath('data.0.funnels_count', 1)
        ->assertJsonPath('data.0.used_by.0.funnel_name', 'Qadha Solat');
});

it('creates a template from pasted HTML and previews it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/api/v1/landing-templates', ['name' => 'HTML page', 'category' => 'lead', 'html' => '<!DOCTYPE html><html><body><h1>Hi there</h1></body></html>'])
        ->assertCreated();

    $template = LandingPageTemplate::sole();
    expect($template->content['content'][0]['type'])->toBe('CustomHtml');

    $this->actingAs($admin)->get("/funnel-builder/landing-preview/{$template->uuid}")
        ->assertOk()
        ->assertSee('Hi there', false);
});

it('only lets the creator or staff edit a template', function () {
    $template = LandingPageTemplate::factory()->create(['created_by' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create(['role' => 'fighter']))
        ->putJson("/api/v1/landing-templates/{$template->id}", ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'employee']))
        ->putJson("/api/v1/landing-templates/{$template->id}", ['name' => 'Renamed'])
        ->assertOk();

    expect($template->fresh()->name)->toBe('Renamed');
});

it('unlinks steps when a template is deleted', function () {
    $step = stepWithPage();
    $template = LandingPageTemplate::factory()->create();
    $step->update(['landing_page_template_id' => $template->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->deleteJson("/api/v1/landing-templates/{$template->id}")
        ->assertOk();

    expect($step->fresh()->landing_page_template_id)->toBeNull()
        ->and($step->fresh()->contents()->count())->toBe(1);
});
