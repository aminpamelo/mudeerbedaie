<?php

namespace App\Models\Concerns;

/**
 * Fulfillment nature for sellable items (products & packages): whether the item
 * ships physically, is a digital download, or grants access to an external
 * system that must have an account provisioned when the order is paid.
 *
 * External-system items store their provisioning link in the model's JSON
 * `metadata` column under metadata['provisioning'] = {external_system_id, plan},
 * mirroring FunnelProduct.settings['provisioning'].
 *
 * Requires the using model to have `fulfillment_type` (string) and `metadata`
 * (array-cast) attributes.
 */
trait HasFulfillmentType
{
    public const FULFILLMENT_PHYSICAL = 'physical';

    public const FULFILLMENT_DIGITAL = 'digital';

    public const FULFILLMENT_EXTERNAL_SYSTEM = 'external_system';

    /**
     * @return array<int, string>
     */
    public static function fulfillmentTypes(): array
    {
        return [
            self::FULFILLMENT_PHYSICAL,
            self::FULFILLMENT_DIGITAL,
            self::FULFILLMENT_EXTERNAL_SYSTEM,
        ];
    }

    public function isPhysical(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_PHYSICAL;
    }

    public function isDigital(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_DIGITAL;
    }

    public function isExternalSystem(): bool
    {
        return $this->fulfillment_type === self::FULFILLMENT_EXTERNAL_SYSTEM;
    }

    /**
     * The provisioning link stored on this item, or null when none is configured.
     *
     * @return array{external_system_id?: int, plan?: ?string}|null
     */
    public function provisioningSettings(): ?array
    {
        $settings = $this->metadata['provisioning'] ?? null;

        return is_array($settings) ? $settings : null;
    }

    public function provisioningExternalSystemId(): ?int
    {
        $id = $this->provisioningSettings()['external_system_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    public function provisioningPlan(): ?string
    {
        $plan = $this->provisioningSettings()['plan'] ?? null;

        return filled($plan) ? (string) $plan : null;
    }

    /**
     * True when this item both is marked external-system and points at a system
     * to provision into — i.e. a paid order should create an account for it.
     */
    public function shouldProvisionExternally(): bool
    {
        return $this->isExternalSystem() && $this->provisioningExternalSystemId() !== null;
    }

    public function scopeExternalSystem($query)
    {
        return $query->where('fulfillment_type', self::FULFILLMENT_EXTERNAL_SYSTEM);
    }
}
