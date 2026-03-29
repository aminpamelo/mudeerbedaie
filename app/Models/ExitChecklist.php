<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExitChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\ExitChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'resignation_request_id',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function resignationRequest(): BelongsTo
    {
        return $this->belongsTo(ResignationRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExitChecklistItem::class)->orderBy('sort_order');
    }

    public function createDefaultItems(): void
    {
        $defaults = [
            ['title' => 'Return laptop/PC', 'category' => 'asset_return', 'sort_order' => 1],
            ['title' => 'Return access card', 'category' => 'asset_return', 'sort_order' => 2],
            ['title' => 'Return office keys', 'category' => 'asset_return', 'sort_order' => 3],
            ['title' => 'Return uniform', 'category' => 'asset_return', 'sort_order' => 4],
            ['title' => 'Return company phone', 'category' => 'asset_return', 'sort_order' => 5],
            ['title' => 'Revoke email access', 'category' => 'system_access', 'sort_order' => 6],
            ['title' => 'Revoke system login', 'category' => 'system_access', 'sort_order' => 7],
            ['title' => 'Remove VPN access', 'category' => 'system_access', 'sort_order' => 8],
            ['title' => 'Handover documents', 'category' => 'documentation', 'sort_order' => 9],
            ['title' => 'Knowledge transfer session', 'category' => 'documentation', 'sort_order' => 10],
            ['title' => 'Return signed resignation acceptance', 'category' => 'documentation', 'sort_order' => 11],
            ['title' => 'Department head clearance', 'category' => 'clearance', 'sort_order' => 12],
            ['title' => 'Finance clearance (no outstanding)', 'category' => 'clearance', 'sort_order' => 13],
            ['title' => 'HR clearance', 'category' => 'clearance', 'sort_order' => 14],
        ];

        foreach ($defaults as $item) {
            $this->items()->create(array_merge($item, ['status' => 'pending']));
        }
    }

    public function addAssetReturnItems(): void
    {
        $assignments = AssetAssignment::where('employee_id', $this->employee_id)
            ->whereNull('returned_date')
            ->with('asset:id,name,asset_tag')
            ->get();

        $sortOrder = $this->items()->max('sort_order') ?? 0;

        foreach ($assignments as $assignment) {
            $sortOrder++;
            $this->items()->create([
                'title' => "Return {$assignment->asset->name} ({$assignment->asset->asset_tag})",
                'category' => 'asset_return',
                'status' => 'pending',
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
