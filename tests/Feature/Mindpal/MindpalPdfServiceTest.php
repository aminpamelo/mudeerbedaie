<?php

declare(strict_types=1);

use App\Services\MindpalEmbeddingService;
use App\Services\MindpalPdfService;

test('chunkPages keeps small page as single chunk', function () {
    $service = new MindpalPdfService;

    $words = implode(' ', array_fill(0, 100, 'word'));
    $pages = [
        ['page' => 1, 'text' => $words],
    ];

    $chunks = $service->chunkPages($pages);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['page_number'])->toBe(1);
    expect($chunks[0]['chunk_index'])->toBe(0);
    expect($chunks[0]['token_count'])->toBe(100);
});

test('chunkPages splits large page with overlap', function () {
    $service = new MindpalPdfService;

    $words = implode(' ', array_map(fn ($i) => "word{$i}", range(1, 1000)));
    $pages = [
        ['page' => 1, 'text' => $words],
    ];

    $chunks = $service->chunkPages($pages);

    // With 1000 words, chunk_size=500, overlap=50:
    // chunk 0: words 0-499 (500 words), next pos = 450
    // chunk 1: words 450-949 (500 words), next pos = 900
    // chunk 2: words 900-999 (100 words), next pos = 1350 > 1000 → done
    expect($chunks)->toHaveCount(3);
    expect($chunks[0]['token_count'])->toBe(500);
    expect($chunks[1]['token_count'])->toBe(500);
    expect($chunks[2]['token_count'])->toBe(100);

    // Verify overlap: last 50 words of chunk 0 should appear at start of chunk 1
    $chunk0Words = explode(' ', $chunks[0]['content']);
    $chunk1Words = explode(' ', $chunks[1]['content']);
    $overlapFromChunk0 = array_slice($chunk0Words, -50);
    $overlapFromChunk1 = array_slice($chunk1Words, 0, 50);
    expect($overlapFromChunk0)->toBe($overlapFromChunk1);
});

test('chunkPages preserves page numbers', function () {
    $service = new MindpalPdfService;

    $pages = [
        ['page' => 1, 'text' => implode(' ', array_fill(0, 50, 'alpha'))],
        ['page' => 3, 'text' => implode(' ', array_fill(0, 80, 'beta'))],
        ['page' => 5, 'text' => implode(' ', array_fill(0, 30, 'gamma'))],
    ];

    $chunks = $service->chunkPages($pages);

    expect($chunks)->toHaveCount(3);
    expect($chunks[0]['page_number'])->toBe(1);
    expect($chunks[1]['page_number'])->toBe(3);
    expect($chunks[2]['page_number'])->toBe(5);
});

test('cosineSimilarity returns 1 for identical vectors', function () {
    $service = new MindpalEmbeddingService;

    $result = $service->cosineSimilarity([1, 0, 0], [1, 0, 0]);

    expect($result)->toBe(1.0);
});

test('cosineSimilarity returns 0 for orthogonal vectors', function () {
    $service = new MindpalEmbeddingService;

    $result = $service->cosineSimilarity([1, 0, 0], [0, 1, 0]);

    expect($result)->toBe(0.0);
});

test('cosineSimilarity handles zero vectors', function () {
    $service = new MindpalEmbeddingService;

    $result = $service->cosineSimilarity([0, 0, 0], [1, 0, 0]);

    expect($result)->toBe(0.0);
});
