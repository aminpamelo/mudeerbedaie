<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-conversation runtime state of a {@see CekbotFlow} — which step the
 * customer is on and the answers collected so far. One active row per
 * conversation drives the guided funnel forward.
 */
class CekbotFlowEnrollment extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotFlowEnrollmentFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STEP_AWAIT_PACKAGE = 'await_package';

    public const STEP_AWAIT_PAYMENT = 'await_payment';

    public const STEP_AWAIT_NAME = 'await_name';

    public const STEP_AWAIT_ADDRESS = 'await_address';

    public const STEP_AWAIT_RECEIPT = 'await_receipt';

    public const STEP_DONE = 'done';

    public const PAYMENT_TRANSFER = 'bank_transfer';

    public const PAYMENT_COD = 'cod';

    protected $fillable = [
        'cekbot_conversation_id',
        'cekbot_flow_id',
        'status',
        'current_step',
        'data',
        'product_order_id',
        'follow_up_stage',
        'next_follow_up_at',
        'started_at',
        'last_activity_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'follow_up_stage' => 'integer',
            'next_follow_up_at' => 'datetime',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CekbotConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CekbotConversation::class, 'cekbot_conversation_id');
    }

    /**
     * @return BelongsTo<CekbotFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(CekbotFlow::class, 'cekbot_flow_id');
    }

    /**
     * @return BelongsTo<ProductOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Read a single collected answer from the JSON bag.
     */
    public function answer(string $key, mixed $default = null): mixed
    {
        return data_get($this->data ?? [], $key, $default);
    }

    /**
     * Merge new answers into the JSON bag without clobbering existing keys.
     *
     * @param  array<string, mixed>  $values
     */
    public function putData(array $values): void
    {
        $this->data = array_merge($this->data ?? [], $values);
    }
}
