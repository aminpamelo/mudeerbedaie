<?php

declare(strict_types=1);

use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Models\MindpalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/* -----------------------------------------------------------------------
 |  Admin — MindPal Dashboard (Inertia Overview)
 | --------------------------------------------------------------------- */

test('admin can access mindpal dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/mindpal')
        ->assertSuccessful();
});

/* -----------------------------------------------------------------------
 |  Admin — MindPal Documents page (Inertia)
 | --------------------------------------------------------------------- */

test('admin can access mindpal documents page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/mindpal/documents')
        ->assertSuccessful();
});

test('admin can upload pdf document', function () {
    Storage::fake('public');
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');

    $this->actingAs($admin)
        ->post('/admin/mindpal/documents', [
            'title' => 'Test Document',
            'description' => 'A test description',
            'file' => $file,
        ])
        ->assertRedirect();

    expect(MindpalDocument::where('title', 'Test Document')->exists())->toBeTrue();

    $document = MindpalDocument::where('title', 'Test Document')->first();
    expect($document->status)->toBe('processing');
    expect($document->uploaded_by)->toBe($admin->id);
    expect($document->description)->toBe('A test description');

    Queue::assertPushed(\App\Jobs\ProcessMindpalDocumentJob::class);
});

test('admin can delete document', function () {
    Storage::fake('public');

    $admin = User::factory()->admin()->create();

    $document = MindpalDocument::create([
        'title' => 'To Delete',
        'file_path' => 'mindpal-documents/test.pdf',
        'file_name' => 'test.pdf',
        'file_size' => 1024,
        'status' => MindpalDocument::STATUS_READY,
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete("/admin/mindpal/documents/{$document->id}")
        ->assertRedirect();

    expect(MindpalDocument::find($document->id))->toBeNull();
});

test('admin can reprocess failed document', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $document = MindpalDocument::create([
        'title' => 'Failed Doc',
        'file_path' => 'mindpal-documents/test.pdf',
        'file_name' => 'test.pdf',
        'file_size' => 1024,
        'status' => MindpalDocument::STATUS_FAILED,
        'error_message' => 'Something went wrong',
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post("/admin/mindpal/documents/{$document->id}/reprocess")
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe('processing');
    expect($document->error_message)->toBeNull();

    Queue::assertPushed(\App\Jobs\ProcessMindpalDocumentJob::class);
});

test('non-admin cannot access mindpal admin page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get('/admin/mindpal')
        ->assertForbidden();
});

test('non-admin cannot access mindpal documents page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get('/admin/mindpal/documents')
        ->assertForbidden();
});

/* -----------------------------------------------------------------------
 |  Student — MindPal Chat
 | --------------------------------------------------------------------- */

test('student can access mindpal page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->get('/my/mindpal')
        ->assertSuccessful();
});

test('student can create conversation', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)
        ->postJson('/my/mindpal/conversations');

    $response->assertSuccessful();
    $response->assertJsonStructure(['id']);

    $conversationId = $response->json('id');
    expect(MindpalConversation::find($conversationId))->not->toBeNull();
    expect(MindpalConversation::find($conversationId)->user_id)->toBe($student->id);
});

test('student can view own conversation', function () {
    $student = User::factory()->create(['role' => 'student']);

    $conversation = MindpalConversation::create([
        'user_id' => $student->id,
        'title' => 'My Conversation',
    ]);

    MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hello!',
    ]);

    $this->actingAs($student)
        ->get("/my/mindpal/{$conversation->id}")
        ->assertSuccessful();
});

test('student cannot view others conversation', function () {
    $studentA = User::factory()->create(['role' => 'student']);
    $studentB = User::factory()->create(['role' => 'student']);

    $conversation = MindpalConversation::create([
        'user_id' => $studentA->id,
        'title' => 'Private Conversation',
    ]);

    $this->actingAs($studentB)
        ->get("/my/mindpal/{$conversation->id}")
        ->assertForbidden();
});

test('student can delete own conversation', function () {
    $student = User::factory()->create(['role' => 'student']);

    $conversation = MindpalConversation::create([
        'user_id' => $student->id,
        'title' => 'To Delete',
    ]);

    MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Test',
    ]);

    $this->actingAs($student)
        ->delete("/my/mindpal/{$conversation->id}")
        ->assertRedirect();

    expect(MindpalConversation::find($conversation->id))->toBeNull();
    expect(MindpalMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
});

test('student cannot delete others conversation', function () {
    $studentA = User::factory()->create(['role' => 'student']);
    $studentB = User::factory()->create(['role' => 'student']);

    $conversation = MindpalConversation::create([
        'user_id' => $studentA->id,
        'title' => 'Not yours',
    ]);

    $this->actingAs($studentB)
        ->delete("/my/mindpal/{$conversation->id}")
        ->assertForbidden();

    expect(MindpalConversation::find($conversation->id))->not->toBeNull();
});

test('non-student cannot access student mindpal', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($teacher)
        ->get('/my/mindpal')
        ->assertForbidden();
});

test('guest cannot access student mindpal', function () {
    $this->get('/my/mindpal')
        ->assertRedirect('/login');
});
