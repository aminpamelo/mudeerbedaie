<?php

declare(strict_types=1);

namespace App\Services\Blog\Ai;

use App\Models\BlogCategory;
use App\Models\BlogIssue;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Product;
use App\Services\Seo\SeoAnalyzer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

/**
 * Turns a captured current-affairs issue into a DRAFT blog article: a
 * religious commentary with Quran/hadith references, closing with a CTA for
 * the most relevant BeDaie book. Every cited hadith/ayat is recorded in
 * `ai_meta` so the editor verifies it before publishing — never auto-published.
 */
class ArticleWriter
{
    public const CATEGORY_NAME = 'Isu Semasa';

    private const PROMPT = <<<'PROMPT'
        Anda penulis artikel dakwah BeDaie (Malaysia) — bahasa Melayu standard yang mesra, jelas dan berhikmah.
        Tulis artikel blog yang mengupas ISU SEMASA yang diberi dari sudut agama Islam (Ahli Sunnah wal Jamaah, mazhab Syafi'i).

        Struktur (Markdown, gunakan ## untuk subtajuk, JANGAN ulang tajuk utama sebagai # dalam isi). Subtajuk mesti
        DESKRIPTIF dan menarik (contoh "## Mengapa Jerebu Ujian Buat Kita Semua") — JANGAN guna label generik seperti
        "Pembuka", "Kupasan Agama", "Panduan Praktikal" atau "Penutup" sebagai teks subtajuk.
        1. Pengenalan: ringkasan isu semasa dengan adil dan tanpa sensasi (2–3 perenggan). Jangan menuduh atau mengaibkan individu.
        2. Kupasan agama: 3–4 subtajuk, setiap satu 2–3 perenggan — pengajaran, hukum/prinsip ringkas, sikap yang wajar.
           Sertakan 1–2 ayat al-Quran (nyatakan nama surah dan nombor ayat) dan 1–2 hadis.
        3. Langkah praktikal: senarai bernombor 4–6 langkah, setiap satu dengan penjelasan 1–2 ayat.
        4. Penutup: muhasabah ringkas + doa pendek.
        WAJIB sekurang-kurangnya 900 patah perkataan (sasaran 900–1200). Masukkan focus_keyword dalam perenggan pertama
        dan dalam sekurang-kurangnya satu subtajuk. JANGAN tulis CTA jualan dalam isi — sistem akan tambah sendiri.

        PERATURAN HADIS & AYAT (sangat penting):
        - Utamakan hadis dari Sahih al-Bukhari dan Sahih Muslim. Hanya petik hadis yang anda yakin wujud dan masyhur.
        - Tulis matan dalam terjemahan Melayu dan nyatakan sumber, contoh "(Riwayat al-Bukhari dan Muslim)". Sertakan nombor
          hadis HANYA jika benar-benar pasti; jika tidak, sebut kitab sahaja.
        - Jika hadis itu dinilai daif oleh ulama, JANGAN gunakannya sebagai dalil utama; jika tetap dipetik untuk fadhail,
          nyatakan statusnya dalam artikel.
        - Jangan reka hadis, jangan sandarkan kata-kata ulama yang anda tidak pasti.
        - Senaraikan SEMUA hadis dan ayat yang dipetik dalam "references", dengan "grade" (sahih/hasan/daif/quran/tidak pasti).

        Pilih SATU buku/produk BeDaie dari senarai "books" yang paling berkaitan dengan isu (atau null jika tiada yang sesuai),
        dan tulis "cta_text": 1–2 ayat ajakan lembut yang mengaitkan isu dengan buku tersebut.

        Balas JSON sahaja:
        {"title":"...","excerpt":"(maks 200 aksara)","meta_description":"(120–155 aksara)","focus_keyword":"(2–4 perkataan)",
         "tags":["..."],"content":"(markdown)","references":[{"type":"hadith|quran","citation":"...","grade":"...","text":"..."}],
         "book_id":<id atau null>,"cta_text":"..."}
        PROMPT;

    public function __construct(private SeoAnalyzer $analyzer) {}

    public function write(BlogIssue $issue, ?int $userId = null): BlogPost
    {
        if (blank(config('openai.api_key'))) {
            throw new RuntimeException('OPENAI_API_KEY belum ditetapkan.');
        }

        $books = $this->books();

        $response = OpenAI::chat()->create([
            'model' => config('blog.ai.model', 'gpt-4o'),
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.6,
            'max_tokens' => 6000,
            'messages' => [
                ['role' => 'system', 'content' => self::PROMPT],
                ['role' => 'user', 'content' => json_encode([
                    'issue' => [
                        'title' => $issue->title,
                        'summary' => $issue->summary,
                        'source' => $issue->source,
                        'suggested_angle' => $issue->angle,
                    ],
                    'books' => $books->map(fn (Product $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'about' => Str::limit(strip_tags((string) ($p->short_description ?: $p->description)), 200),
                    ])->values(),
                ], JSON_UNESCAPED_UNICODE)],
            ],
        ]);

        $data = json_decode((string) ($response->choices[0]->message->content ?? ''), true);

        if (! is_array($data) || blank($data['title'] ?? null) || blank($data['content'] ?? null)) {
            throw new RuntimeException('AI tidak memulangkan artikel yang lengkap.');
        }

        $book = filled($data['book_id'] ?? null) ? $books->firstWhere('id', (int) $data['book_id']) : null;
        $references = collect($data['references'] ?? [])
            ->filter(fn ($r) => is_array($r) && filled($r['citation'] ?? null))
            ->map(fn (array $r) => [
                'type' => ($r['type'] ?? '') === 'quran' ? 'quran' : 'hadith',
                'citation' => mb_substr(trim((string) $r['citation']), 0, 300),
                'grade' => mb_substr(trim((string) ($r['grade'] ?? '')), 0, 40) ?: null,
                'text' => mb_substr(trim((string) ($r['text'] ?? '')), 0, 1000),
            ])
            ->values()
            ->all();

        $post = BlogPost::create([
            'title' => Str::limit(trim((string) $data['title']), 250, ''),
            'excerpt' => Str::limit(trim((string) ($data['excerpt'] ?? '')), 250, '') ?: null,
            'content' => $this->composeContent((string) $data['content'], $issue, $book, (string) ($data['cta_text'] ?? '')),
            'category_id' => $this->category()->id,
            'author_id' => $userId,
            'locale' => 'ms',
            'status' => BlogPost::STATUS_DRAFT,
            'allow_comments' => true,
            'meta_description' => Str::limit(trim((string) ($data['meta_description'] ?? '')), 160, '') ?: null,
            'focus_keyword' => Str::limit(trim((string) ($data['focus_keyword'] ?? '')), 100, '') ?: null,
            'ai_meta' => [
                'generated_at' => now()->toIso8601String(),
                'model' => config('blog.ai.model', 'gpt-4o'),
                'issue_id' => $issue->id,
                'issue_title' => $issue->title,
                'source' => $issue->source,
                'source_url' => $issue->url,
                'references' => $references,
                'book_id' => $book?->id,
                'needs_review' => true,
            ],
        ]);

        $tagIds = collect($data['tags'] ?? [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->take(6)
            ->map(fn (string $name) => BlogTag::findOrCreateByName($name)->id)
            ->all();
        $post->tags()->sync($tagIds);

        if ($book) {
            $post->products()->sync([$book->id]);
        }

        $this->analyzer->analyseAndStore($post->fresh());

        $issue->update(['status' => BlogIssue::STATUS_WRITTEN, 'blog_post_id' => $post->id, 'error' => null]);

        return $post;
    }

    /**
     * Body + source credit + the book CTA (built here, not by the AI, so the
     * link and price always point at the real product).
     */
    private function composeContent(string $content, BlogIssue $issue, ?Product $book, string $ctaText): string
    {
        $content = trim(preg_replace('/^#\s+.+\n+/u', '', trim($content)) ?? $content);

        $parts = [$content, "\n\n*Sumber isu: [{$issue->source}]({$issue->url})*"];

        if ($book) {
            $url = route('storefront.product', $book->slug);
            $name = trim((string) preg_replace('/\s+/u', ' ', $book->name));
            $label = Str::startsWith(Str::lower($name), 'buku') ? $name : "buku {$name}";
            $cta = trim($ctaText) ?: "Dapatkan panduan lanjut dalam {$label}.";
            $parts[] = "\n\n---\n\n### 📖 {$name}\n\n{$cta}\n\n**[Dapatkan {$label} di sini →]({$url})**";
        }

        return implode('', $parts)."\n";
    }

    /**
     * Products the AI may recommend: live on the storefront and active.
     *
     * @return Collection<int, Product>
     */
    private function books(): Collection
    {
        return Product::query()
            ->where('status', 'active')
            ->storefrontVisible()
            ->whereNotNull('slug')
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name', 'slug', 'short_description', 'description']);
    }

    private function category(): BlogCategory
    {
        return BlogCategory::query()->firstOrCreate(
            ['slug' => Str::slug(self::CATEGORY_NAME)],
            ['name' => self::CATEGORY_NAME, 'description' => 'Kupasan isu semasa dari sudut agama.', 'is_active' => true]
        );
    }
}
