<?php

namespace App\Livewire\Concerns;

use App\Models\ExternalSystem;
use App\Models\ProductOrder;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use Illuminate\Support\Collection;

/**
 * Shared "Provision this order to an external system" behaviour for the orders
 * list and the order detail page: the pick-a-system modal and the action that
 * creates the buyer's account on demand. UI lives in
 * resources/views/livewire/admin/orders/partials/provision-modal.blade.php.
 */
trait ProvisionsOrders
{
    public bool $showProvisionModal = false;

    public ?int $provisionOrderId = null;

    public ?int $provisionSystemId = null;

    public string $provisionPlan = '';

    public function openProvisionModal(int $orderId): void
    {
        $this->resetProvisionForm();
        $this->provisionOrderId = $orderId;

        $systems = $this->provisionSystems();

        if ($systems->count() === 1) {
            $this->provisionSystemId = (int) $systems->first()->id;
        }

        $this->showProvisionModal = true;
    }

    public function provisionOrder(): void
    {
        $this->validate([
            'provisionOrderId' => ['required', 'integer', 'exists:product_orders,id'],
            'provisionSystemId' => ['required', 'integer', 'exists:external_systems,id'],
            'provisionPlan' => ['nullable', 'string', 'max:191'],
        ], [], ['provisionSystemId' => 'system']);

        $order = ProductOrder::findOrFail($this->provisionOrderId);
        $system = ExternalSystem::findOrFail($this->provisionSystemId);

        $request = app(ExternalProvisioningManager::class)
            ->provisionManually($order, $system, $this->provisionPlan !== '' ? $this->provisionPlan : null);

        $this->showProvisionModal = false;
        $this->resetProvisionForm();

        if ($request->isSucceeded()) {
            $this->dispatch('order-provisioned', message: "Account created in {$system->name}.");
        } else {
            $this->dispatch('order-provision-failed', message: $request->last_error ?: 'Provisioning failed.');
        }
    }

    /**
     * @return Collection<int, ExternalSystem>
     */
    public function provisionSystems(): Collection
    {
        return ExternalSystem::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function resetProvisionForm(): void
    {
        $this->provisionOrderId = null;
        $this->provisionSystemId = null;
        $this->provisionPlan = '';
        $this->resetErrorBag(['provisionOrderId', 'provisionSystemId', 'provisionPlan']);
    }
}
