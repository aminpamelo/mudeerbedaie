<?php

namespace App\Services;

use App\Models\MindpalChunk;
use OpenAI\Laravel\Facades\OpenAI;

class MindpalEmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => mb_scrub($text, 'UTF-8'),
        ]);

        return $response->embeddings[0]->embedding;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => array_map(fn ($t) => mb_scrub($t, 'UTF-8'), $texts),
        ]);

        return array_map(fn ($e) => $e->embedding, $response->embeddings);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @return array<int, array{chunk: MindpalChunk, score: float}>
     */
    public function findSimilarChunks(array $queryEmbedding, int $limit = 5): array
    {
        $chunks = MindpalChunk::query()
            ->whereNotNull('embedding')
            ->with('document:id,title')
            ->get();

        $scored = $chunks->map(function ($chunk) use ($queryEmbedding) {
            return [
                'chunk' => $chunk,
                'score' => $this->cosineSimilarity($queryEmbedding, $chunk->embedding),
            ];
        });

        return $scored->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
