<?php

declare(strict_types=1);

namespace App\Services\Blog\Ai;

use App\Models\BlogIssue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Scores headlines 0–10 for how well they suit a BeDaie article that analyses
 * a current issue from an Islamic perspective, and proposes the angle.
 */
class IssueCurator
{
    private const BATCH = 40;

    private const PROMPT = <<<'PROMPT'
        Anda editor kandungan dakwah untuk BeDaie (Malaysia). Diberi senarai tajuk berita, nilai setiap satu dari 0 hingga 10:
        sejauh mana ia sesuai dijadikan artikel blog yang mengupas isu semasa dari sudut agama Islam (akhlak, keluarga,
        ibadah, muamalah, sosial, ujian hidup) dengan hadis/ayat yang relevan, untuk pembaca umum di Malaysia.

        Skor rendah (0–3) untuk: sukan, hiburan gosip, politik kepartian yang memecah-belah, jenayah grafik tanpa pengajaran,
        pasaran saham, berita luar negara yang tiada kaitan. Skor tinggi (7–10) untuk isu yang menyentuh nilai, keluarga,
        anak muda, kesihatan jiwa, bencana (sabar & tolong-menolong), integriti, media sosial, ibadah dan musim (Ramadan dll).
        Elak isu yang sensitif kaum/agama lain atau boleh mengaibkan individu.

        Untuk setiap tajuk beri "angle": satu ayat ringkas sudut kupasan agama yang sesuai (Bahasa Melayu).
        Balas JSON sahaja: {"items":[{"id":<id>,"relevance":<0-10>,"angle":"..."}]}
        PROMPT;

    /**
     * @param  Collection<int, BlogIssue>  $issues
     * @return int number of issues scored
     */
    public function score(Collection $issues): int
    {
        if (blank(config('openai.api_key'))) {
            return 0;
        }

        $scored = 0;

        foreach ($issues->chunk(self::BATCH) as $batch) {
            try {
                $list = $batch->map(fn (BlogIssue $i) => ['id' => $i->id, 'title' => $i->title, 'summary' => mb_substr((string) $i->summary, 0, 240)])->values();

                $response = OpenAI::chat()->create([
                    'model' => config('blog.ai.curate_model', 'gpt-4o-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => self::PROMPT],
                        ['role' => 'user', 'content' => json_encode($list, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

                $items = json_decode((string) ($response->choices[0]->message->content ?? ''), true)['items'] ?? [];

                foreach ($items as $item) {
                    $issue = $batch->firstWhere('id', (int) ($item['id'] ?? 0));
                    if (! $issue) {
                        continue;
                    }

                    $issue->update([
                        'relevance' => max(0, min(10, (int) ($item['relevance'] ?? 0))),
                        'angle' => isset($item['angle']) ? mb_substr(trim((string) $item['angle']), 0, 500) : null,
                    ]);
                    $scored++;
                }
            } catch (\Throwable $e) {
                Log::warning('Blog issue curation failed', ['error' => $e->getMessage()]);
            }
        }

        return $scored;
    }
}
