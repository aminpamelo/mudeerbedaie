<?php

namespace App\Livewire\Concerns;

use App\Models\ExternalProvisioningRequest;
use App\Models\ExternalSystem;
use App\Models\ProductOrder;
use App\Services\ExternalProvisioning\ExternalProvisioningManager;
use App\Services\ExternalProvisioning\ExternalSystemClient;
use Illuminate\Support\Collection;

/**
 * Shared "Provision this order to an external system" behaviour for the orders
 * list and the order detail page: the pick-a-system modal, the login details it
 * shows afterwards, and the action that creates the buyer's account on demand.
 * UI: resources/views/livewire/admin/orders/partials/provision-modal.blade.php.
 */
trait ProvisionsOrders
{
    public bool $showProvisionModal = false;

    public ?int $provisionOrderId = null;

    public ?int $provisionSystemId = null;

    public string $provisionPlan = '';

    /** @var array<string, string> slug => name, pulled from the chosen system's /packages */
    public array $provisionPlans = [];

    public bool $provisionPlansLoaded = false;

    /** @var array<string, mixed>|null login details shown after a successful provision */
    public ?array $provisionResult = null;

    public ?string $provisionError = null;

    public function openProvisionModal(int $orderId): void
    {
        $this->resetProvisionForm();
        $this->provisionOrderId = $orderId;

        // Already provisioned? Show the stored login details immediately.
        $existing = ExternalProvisioningRequest::with('externalSystem')
            ->where('product_order_id', $orderId)
            ->where('status', ExternalProvisioningRequest::STATUS_SUCCEEDED)
            ->latest('id')
            ->first();

        if ($existing) {
            $this->provisionResult = $this->resultFromRequest($existing);
        } else {
            $this->prepareProvisionForm();
        }

        $this->showProvisionModal = true;
    }

    public function provisionAnother(): void
    {
        $this->provisionResult = null;
        $this->provisionError = null;
        $this->prepareProvisionForm();
    }

    public function updatedProvisionSystemId(): void
    {
        $this->provisionPlan = '';
        $this->loadProvisionPlans();
    }

    /**
     * Pull the plan list from the chosen system's /packages endpoint so the
     * admin picks a real plan instead of typing a slug. Falls back silently to
     * a free-text field when the system does not expose /packages.
     */
    public function loadProvisionPlans(): void
    {
        $this->provisionPlans = [];
        $this->provisionPlansLoaded = false;

        $system = $this->provisionSystemId ? ExternalSystem::find($this->provisionSystemId) : null;

        if (! $system) {
            return;
        }

        try {
            foreach (app(ExternalSystemClient::class)->packages($system) as $plan) {
                if (is_array($plan) && filled($plan['slug'] ?? null)) {
                    $this->provisionPlans[$plan['slug']] = $plan['name'] ?? $plan['slug'];
                }
            }
        } catch (\Throwable) {
            $this->provisionPlans = []; // system has no /packages — free-text fallback
        }

        $this->provisionPlansLoaded = true;
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

        if ($request->isSucceeded()) {
            $this->provisionResult = $this->resultFromRequest($request->loadMissing('externalSystem'));
            $this->provisionError = null;
            $this->dispatch('order-provisioned', message: "Account created in {$system->name}.");
        } else {
            $this->provisionResult = null;
            $this->provisionError = $request->last_error ?: 'Provisioning failed.';
            $this->dispatch('order-provision-failed', message: $this->provisionError);
        }
    }

    public function closeProvisionModal(): void
    {
        $this->showProvisionModal = false;
        $this->resetProvisionForm();
    }

    /**
     * @return Collection<int, ExternalSystem>
     */
    public function provisionSystems(): Collection
    {
        return ExternalSystem::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function prepareProvisionForm(): void
    {
        $this->provisionSystemId = null;
        $this->provisionPlan = '';
        $this->provisionPlans = [];
        $this->provisionPlansLoaded = false;

        $systems = $this->provisionSystems();

        if ($systems->count() === 1) {
            $this->provisionSystemId = (int) $systems->first()->id;
            $this->loadProvisionPlans();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromRequest(ExternalProvisioningRequest $request): array
    {
        $creds = $request->credentials ?: [];

        return [
            'system' => $request->externalSystem?->name ?? 'System #'.$request->external_system_id,
            'external_user_id' => $request->external_user_id,
            'login_url' => $request->login_url,
            'magic_link' => $creds['magic_link'] ?? null,
            'username' => $creds['username'] ?? null,
            'temp_password' => $creds['temp_password'] ?? null,
        ];
    }

    private function resetProvisionForm(): void
    {
        $this->provisionOrderId = null;
        $this->provisionSystemId = null;
        $this->provisionPlan = '';
        $this->provisionPlans = [];
        $this->provisionPlansLoaded = false;
        $this->provisionResult = null;
        $this->provisionError = null;
        $this->resetErrorBag(['provisionOrderId', 'provisionSystemId', 'provisionPlan']);
    }
}
