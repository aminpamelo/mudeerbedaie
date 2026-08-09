<?php

declare(strict_types=1);

use App\Models\MindpalChunk;
use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Models\MindpalMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('document has chunks relationship', function () {
    $user = User::factory()->create();

    $document = MindpalDocument::create([
        'title' => 'Test Document',
        'file_path' => 'documents/test.pdf',
        'file_name' => 'test.pdf',
        'file_size' => 1024,
        'status' => MindpalDocument::STATUS_READY,
        'uploaded_by' => $user->id,
    ]);

    MindpalChunk::create([
        'document_id' => $document->id,
        'content' => 'This is chunk one.',
        'page_number' => 1,
        'chunk_index' => 0,
        'token_count' => 5,
    ]);

    MindpalChunk::create([
        'document_id' => $document->id,
        'content' => 'This is chunk two.',
        'page_number' => 1,
        'chunk_index' => 1,
        'token_count' => 5,
    ]);

    expect($document->chunks)->toHaveCount(2);
    expect($document->chunks->first())->toBeInstanceOf(MindpalChunk::class);
});

test('document ready scope works', function () {
    $user = User::factory()->create();

    MindpalDocument::create([
        'title' => 'Processing Doc',
        'file_path' => 'documents/proc.pdf',
        'file_name' => 'proc.pdf',
        'status' => MindpalDocument::STATUS_PROCESSING,
        'uploaded_by' => $user->id,
    ]);

    MindpalDocument::create([
        'title' => 'Ready Doc',
        'file_path' => 'documents/ready.pdf',
        'file_name' => 'ready.pdf',
        'status' => MindpalDocument::STATUS_READY,
        'uploaded_by' => $user->id,
    ]);

    MindpalDocument::create([
        'title' => 'Failed Doc',
        'file_path' => 'documents/failed.pdf',
        'file_name' => 'failed.pdf',
        'status' => MindpalDocument::STATUS_FAILED,
        'uploaded_by' => $user->id,
    ]);

    $readyDocs = MindpalDocument::ready()->get();

    expect($readyDocs)->toHaveCount(1);
    expect($readyDocs->first()->title)->toBe('Ready Doc');
});

test('conversation has messages relationship', function () {
    $user = User::factory()->create();

    $conversation = MindpalConversation::create([
        'user_id' => $user->id,
        'title' => 'Test Conversation',
    ]);

    MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hello, how are you?',
    ]);

    MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'I am fine, thank you!',
        'tokens_used' => 25,
    ]);

    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages->first())->toBeInstanceOf(MindpalMessage::class);
});

test('conversation forUser scope works', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    MindpalConversation::create(['user_id' => $userA->id, 'title' => 'Conv A']);
    MindpalConversation::create(['user_id' => $userA->id, 'title' => 'Conv A2']);
    MindpalConversation::create(['user_id' => $userB->id, 'title' => 'Conv B']);

    expect(MindpalConversation::forUser($userA->id)->count())->toBe(2);
    expect(MindpalConversation::forUser($userB->id)->count())->toBe(1);
});

test('message casts sources as array', function () {
    $user = User::factory()->create();

    $conversation = MindpalConversation::create([
        'user_id' => $user->id,
        'title' => 'Test',
    ]);

    $message = MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Based on your documents...',
        'sources' => [
            ['document_id' => 1, 'chunk_index' => 0, 'score' => 0.95],
            ['document_id' => 1, 'chunk_index' => 3, 'score' => 0.87],
        ],
        'tokens_used' => 150,
    ]);

    $message->refresh();

    expect($message->sources)->toBeArray();
    expect($message->sources)->toHaveCount(2);
    expect($message->sources[0]['score'])->toBe(0.95);
});

test('deleting document cascades to chunks', function () {
    $user = User::factory()->create();

    $document = MindpalDocument::create([
        'title' => 'Cascade Test',
        'file_path' => 'documents/cascade.pdf',
        'file_name' => 'cascade.pdf',
        'status' => MindpalDocument::STATUS_READY,
        'uploaded_by' => $user->id,
    ]);

    MindpalChunk::create([
        'document_id' => $document->id,
        'content' => 'Chunk content',
        'page_number' => 1,
        'chunk_index' => 0,
    ]);

    expect(MindpalChunk::where('document_id', $document->id)->count())->toBe(1);

    $document->delete();

    expect(MindpalChunk::where('document_id', $document->id)->count())->toBe(0);
});

test('deleting conversation cascades to messages', function () {
    $user = User::factory()->create();

    $conversation = MindpalConversation::create([
        'user_id' => $user->id,
        'title' => 'Cascade Test',
    ]);

    MindpalMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Test message',
    ]);

    expect(MindpalMessage::where('conversation_id', $conversation->id)->count())->toBe(1);

    $conversation->delete();

    expect(MindpalMessage::where('conversation_id', $conversation->id)->count())->toBe(0);
});
