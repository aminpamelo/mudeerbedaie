<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    /** @use HasFactory<\Database\Factories\FormFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    /**
     * Field types available in the builder. Grouped for the field picker UI.
     *
     * @var list<string>
     */
    public const FIELD_TYPES = [
        'short_text', 'long_text', 'number', 'email', 'date',
        'radio', 'checkbox', 'dropdown',
        'file',
        'rating', 'phone', 'section', 'paragraph',
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'form_category_id',
        'title',
        'slug',
        'description',
        'status',
        'fields',
        'settings',
        'submissions_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'settings' => 'array',
            'submissions_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form): void {
            $form->uuid = $form->uuid ?: Str::uuid()->toString();

            if (empty($form->slug)) {
                $form->slug = static::uniqueSlug($form->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';

        return $base.'-'.Str::lower(Str::random(6));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FormCategory::class, 'form_category_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isAcceptingSubmissions(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Fields flattened to the answerable ones (excludes layout-only fields
     * like sections and paragraphs), used when validating/rendering answers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function answerableFields(): array
    {
        return array_values(array_filter(
            $this->fields ?? [],
            fn (array $field): bool => ! in_array($field['type'] ?? '', ['section', 'paragraph'], true),
        ));
    }

    public function publicUrl(): string
    {
        return route('form.public.show', $this->slug);
    }
}
