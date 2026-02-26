<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunnelStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'funnel_id' => $this->funnel_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'next_step_id' => $this->next_step_id,
            'decline_step_id' => $this->decline_step_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed fields
            'has_content' => $this->hasContent(),
            'has_published_content' => $this->hasPublishedContent(),

            // Content (when loaded)
            'content' => $this->when(
                $this->relationLoaded('draftContent') || $this->relationLoaded('publishedContent'),
                fn () => [
                    'draft' => $this->whenLoaded('draftContent', fn () => [
                        'content' => $this->draftContent?->content,
                        'version' => $this->draftContent?->version,
                        'updated_at' => $this->draftContent?->updated_at?->toIso8601String(),
                    ]),
                    'published' => $this->whenLoaded('publishedContent', fn () => [
                        'content' => $this->publishedContent?->content,
                        'version' => $this->publishedContent?->version,
                        'published_at' => $this->publishedContent?->published_at?->toIso8601String(),
                    ]),
                ]
            ),

            // Products
            'products' => $this->when(
                $this->relationLoaded('products'),
                fn () => $this->products->map(fn ($p) => [
                    'id' => $p->id,
                    'product_id' => $p->product_id,
                    'course_id' => $p->course_id,
                    'type' => $p->type,
                    'name' => $p->name,
                    'description' => $p->description,
                    'image_url' => $p->image_url,
                    'funnel_price' => $p->funnel_price,
                    'compare_at_price' => $p->compare_at_price,
                    'is_recurring' => $p->is_recurring,
                    'billing_interval' => $p->billing_interval,
                ])
            ),

            // Order bumps
            'order_bumps' => $this->when(
                $this->relationLoaded('orderBumps'),
                fn () => $this->orderBumps->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'headline' => $b->headline,
                    'description' => $b->description,
                    'price' => $b->price,
                    'compare_at_price' => $b->compare_at_price,
                    'is_active' => $b->is_active,
                ])
            ),
        ];
    }
}
