<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'settings' => $this->settings,
            'thumbnail' => $this->thumbnail,
            'published_at' => $this->published_at?->toIso8601String(),

            // Embed settings
            'embed_enabled' => $this->embed_enabled ?? false,
            'embed_key' => $this->embed_key,
            'embed_settings' => $this->embed_settings,

            // Affiliate settings
            'affiliate_enabled' => $this->affiliate_enabled ?? false,
            'affiliate_custom_url' => $this->affiliate_custom_url,

            // Order settings
            'show_orders_in_admin' => $this->show_orders_in_admin ?? true,

            // Payment settings
            'payment_settings' => $this->payment_settings,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed fields
            'url' => $this->getPublicUrl(),
            'builder_url' => $this->getBuilderUrl(),
            'is_published' => $this->isPublished(),

            // Counts
            'steps_count' => $this->when(isset($this->steps_count), $this->steps_count),
            'sessions_count' => $this->when(isset($this->sessions_count), $this->sessions_count),
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),

            // Relations
            'steps' => FunnelStepResource::collection($this->whenLoaded('steps')),
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
        ];
    }
}
