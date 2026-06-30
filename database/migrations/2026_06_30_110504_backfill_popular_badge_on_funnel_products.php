<?php

use App\Models\FunnelProduct;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Preserve the previous behaviour where the first package of a
     * multi-package step automatically displayed a "Paling Popular" badge.
     */
    public function up(): void
    {
        FunnelProduct::query()
            ->select('funnel_step_id')
            ->groupBy('funnel_step_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('funnel_step_id')
            ->each(function (int $stepId): void {
                $first = FunnelProduct::where('funnel_step_id', $stepId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                $first?->update([
                    'is_popular' => true,
                    'popular_label' => 'Paling Popular',
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FunnelProduct::query()->update([
            'is_popular' => false,
            'popular_label' => null,
        ]);
    }
};
