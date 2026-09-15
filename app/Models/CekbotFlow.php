<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A guided sales-funnel flow (per WhatsApp number) — greet → pick package →
 * choose payment (transfer/COD) → collect details → auto-create an order.
 */
class CekbotFlow extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotFlowFactory> */
    use HasFactory;

    protected $fillable = [
        'cekbot_session_id',
        'name',
        'is_active',
        'use_ai',
        'ai_instructions',
        'match_type',
        'trigger_keywords',
        'welcome_message',
        'package_prompt',
        'confirmation_message',
        'ask_payment',
        'payment_transfer_enabled',
        'payment_cod_enabled',
        'bank_details',
        'bank_image',
        'transfer_instructions',
        'ask_name',
        'sales_source_id',
        'follow_ups',
        'sort_order',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'use_ai' => 'boolean',
            'trigger_keywords' => 'array',
            'ask_payment' => 'boolean',
            'payment_transfer_enabled' => 'boolean',
            'payment_cod_enabled' => 'boolean',
            'ask_name' => 'boolean',
            'follow_ups' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CekbotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CekbotSession::class, 'cekbot_session_id');
    }

    /**
     * Packages offered by this flow, in display order.
     *
     * @return HasMany<CekbotFlowPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(CekbotFlowPackage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<SalesSource, $this>
     */
    public function salesSource(): BelongsTo
    {
        return $this->belongsTo(SalesSource::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<CekbotFlow>  $query
     * @return Builder<CekbotFlow>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Public URL of the transfer/QR poster, or null when none is uploaded.
     */
    public function bankImageUrl(): ?string
    {
        return filled($this->bank_image)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->bank_image)
            : null;
    }

    /**
     * Whether this flow should be driven by the AI sales agent (natural
     * language, intent + confirmation) rather than the numbered-menu fallback.
     * Requires an OpenAI key; otherwise the deterministic path runs.
     */
    public function aiEnabled(): bool
    {
        return $this->use_ai && filled(config('openai.api_key'));
    }

    /**
     * Whether an inbound message should trigger this flow.
     */
    public function matches(string $body): bool
    {
        $body = trim(mb_strtolower($body));

        if ($body === '') {
            return false;
        }

        $keywords = array_filter(array_map(
            fn ($k) => trim(mb_strtolower((string) $k)),
            $this->trigger_keywords ?? []
        ));

        if (empty($keywords)) {
            return false;
        }

        foreach ($keywords as $keyword) {
            $hit = match ($this->match_type) {
                'exact' => $body === $keyword,
                'starts' => Str::startsWith($body, $keyword),
                default => Str::contains($body, $keyword), // contains
            };

            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether both payment methods are offered (so the customer must choose).
     */
    public function offersBothPaymentMethods(): bool
    {
        return $this->ask_payment && $this->payment_transfer_enabled && $this->payment_cod_enabled;
    }

    /**
     * The single payment method when only one is enabled, or null when the
     * customer must be asked (both) or payment is skipped entirely.
     */
    public function solePaymentMethod(): ?string
    {
        if (! $this->ask_payment) {
            return null;
        }

        if ($this->payment_transfer_enabled && ! $this->payment_cod_enabled) {
            return CekbotFlowEnrollment::PAYMENT_TRANSFER;
        }

        if ($this->payment_cod_enabled && ! $this->payment_transfer_enabled) {
            return CekbotFlowEnrollment::PAYMENT_COD;
        }

        return null;
    }
}
