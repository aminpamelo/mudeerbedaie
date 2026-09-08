<?php

use App\Models\CekbotConversation;
use App\Models\CekbotLeadCategory;
use App\Models\CekbotSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->admin = User::factory()->admin()->create();
    $this->session = CekbotSession::factory()->working()->create(['session_name' => 'default']);
});

it('renders the leads page and excludes groups', function () {
    CekbotConversation::factory()->count(3)->create(['cekbot_session_id' => $this->session->id, 'is_group' => false]);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => true]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/leads')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Leads/Index', false)
            ->has('leads.data', 3)
            ->where('stats.total', 3));
});

it('filters leads by search', function () {
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'name' => 'Aisyah Kurma', 'chat_id' => '60123456789@c.us']);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'name' => 'Bakar', 'chat_id' => '60199999999@c.us']);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/leads?search=Aisyah')
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)
            ->where('leads.data.0.name', 'Aisyah Kurma'));

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/leads?search=60199')
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)
            ->where('leads.data.0.name', 'Bakar'));
});

it('filters leads by label', function () {
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'labels' => ['penting']]);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'labels' => ['selesai']]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/leads?label=penting')
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1));
});

it('renders the kanban board with a column per category plus uncategorised', function () {
    // 4 default categories are seeded by migration.
    $category = CekbotLeadCategory::query()->first();
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'lead_category_id' => $category->id]);
    CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'lead_category_id' => null]);

    $this->actingAs($this->admin)
        ->get('/admin/cekbot/leads?view=board')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Leads/Index', false)
            ->where('view', 'board')
            ->has('board', 5) // "Tiada kategori" + 4 seeded categories
            ->where('board.0.id', null));
});

it('creates a lead category', function () {
    $this->actingAs($this->admin)
        ->post('/admin/cekbot/leads/categories', ['name' => 'VIP', 'color' => 'rose'])
        ->assertRedirect();

    expect(CekbotLeadCategory::query()->where('name', 'VIP')->where('color', 'rose')->exists())->toBeTrue();
});

it('validates the category colour', function () {
    $this->actingAs($this->admin)
        ->post('/admin/cekbot/leads/categories', ['name' => 'X', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');
});

it('updates a lead category', function () {
    $category = CekbotLeadCategory::factory()->create(['name' => 'Old', 'color' => 'blue']);

    $this->actingAs($this->admin)
        ->put("/admin/cekbot/leads/categories/{$category->id}", ['name' => 'New', 'color' => 'amber'])
        ->assertRedirect();

    expect($category->fresh()->only(['name', 'color']))->toBe(['name' => 'New', 'color' => 'amber']);
});

it('deletes a category and nulls its leads', function () {
    $category = CekbotLeadCategory::factory()->create();
    $lead = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'lead_category_id' => $category->id]);

    $this->actingAs($this->admin)
        ->delete("/admin/cekbot/leads/categories/{$category->id}")
        ->assertRedirect();

    $this->assertModelMissing($category);
    expect($lead->fresh()->lead_category_id)->toBeNull();
});

it('moves a lead into a category', function () {
    $category = CekbotLeadCategory::factory()->create();
    $lead = CekbotConversation::factory()->create(['cekbot_session_id' => $this->session->id, 'is_group' => false, 'lead_category_id' => null]);

    $this->actingAs($this->admin)
        ->post("/admin/cekbot/leads/{$lead->id}/move", ['lead_category_id' => $category->id])
        ->assertRedirect();

    expect($lead->fresh()->lead_category_id)->toBe($category->id);

    // Move back to uncategorised.
    $this->actingAs($this->admin)
        ->post("/admin/cekbot/leads/{$lead->id}/move", ['lead_category_id' => null])
        ->assertRedirect();

    expect($lead->fresh()->lead_category_id)->toBeNull();
});

it('exports leads as a CSV download', function () {
    CekbotConversation::factory()->create([
        'cekbot_session_id' => $this->session->id,
        'is_group' => false,
        'name' => 'Puan Aisyah',
        'chat_id' => '60123456789@c.us',
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/cekbot/leads/export');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    expect($csv)->toContain('Nama')
        ->and($csv)->toContain('Puan Aisyah')
        ->and($csv)->toContain('60123456789');
});
