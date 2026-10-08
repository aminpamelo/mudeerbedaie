<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A real closing conversation from the sales team that the Cekbot AI studies
 * for tone, persuasion and objection handling. Tagged to specific flows, or
 * global when no flow is set.
 */
class CekbotClosingReference extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotClosingReferenceFactory> */
    use HasFactory;

    /** References sent to the AI per reply, and the per-transcript size cap. */
    public const PROMPT_LIMIT = 3;

    public const PROMPT_CHARS_EACH = 3000;

    protected $fillable = [
        'title',
        'transcript',
        'notes',
        'flow_ids',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'flow_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Whether this reference applies to every flow (no flow tags).
     */
    public function isGlobal(): bool
    {
        return empty($this->flow_ids);
    }

    /**
     * The references the AI should study for a reply: those tagged to the
     * flow first, then global ones, newest first, capped at PROMPT_LIMIT.
     *
     * @return Collection<int, self>
     */
    public static function forFlow(?int $flowId): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->latest('id')
            ->get()
            ->filter(fn (self $ref) => $ref->isGlobal() || ($flowId !== null && in_array($flowId, array_map('intval', $ref->flow_ids ?? []), true)))
            ->sortBy(fn (self $ref) => $ref->isGlobal() ? 1 : 0)
            ->take(self::PROMPT_LIMIT)
            ->values();
    }

    /**
     * System-prompt block with the chosen references, or null when none.
     */
    public static function promptFor(?int $flowId): ?string
    {
        $refs = static::forFlow($flowId);

        if ($refs->isEmpty()) {
            return null;
        }

        $blocks = $refs->map(function (self $ref, int $i): string {
            $block = 'Contoh '.($i + 1).': '.$ref->title;
            if (filled($ref->notes)) {
                $block .= "\nKenapa berjaya: ".trim((string) $ref->notes);
            }

            return $block."\n---\n".Str::limit(self::maskPersonalData($ref->transcript), self::PROMPT_CHARS_EACH)."\n---";
        })->implode("\n\n");

        return 'RUJUKAN CLOSING TEAM SALES — perbualan sebenar yang berjaya closing. '
            .'Tiru GAYA sahaja: nada mesra, cara bina kepercayaan, cara jawab bantahan dan cara ajak buat keputusan. '
            .'JANGAN salin harga, pakej, tarikh, nama atau janji dari contoh ini — ikut maklumat produk/flow semasa. '
            ."Jangan sebut kepada pelanggan bahawa anda ada contoh ini.\n\n".$blocks;
    }

    /**
     * Hide phone numbers and emails before a transcript is sent to the AI.
     */
    public static function maskPersonalData(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[emel]', $text) ?? $text;

        return preg_replace('/(?<!\d)\+?\d[\d\s-]{7,}\d(?!\d)/u', '[nombor]', $text) ?? $text;
    }
}
