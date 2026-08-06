{{--
    Marks a Product/Package that is set to provision an account on an external
    system when a paid order comes in (fulfillment_type = external_system +
    metadata.provisioning.external_system_id). Used in the Products & Packages
    lists so you can detect at a glance which items create external accounts.

    Expects: $item (a model using App\Models\Concerns\HasFulfillmentType).
--}}
@if (method_exists($item, 'isExternalSystem') && $item->isExternalSystem())
    @php
        // Resolve system id -> name once per request (no N+1 across rows).
        $esMap = once(fn () => \App\Models\ExternalSystem::query()->pluck('name', 'id'));
        $esId = $item->provisioningExternalSystemId();
        $esName = $esId ? ($esMap[$esId] ?? 'System #'.$esId) : null;
        $esPlan = $item->provisioningPlan();
    @endphp
    <div class="mt-1">
        <flux:tooltip content="{{ $esName ? 'Provisions to '.$esName.($esPlan ? ' · '.$esPlan : '') : 'Marked external system — no system linked yet' }}">
            <flux:badge size="sm" :color="$esName ? 'indigo' : 'amber'" icon="server-stack">
                {{ $esName ?? 'External system' }}
            </flux:badge>
        </flux:tooltip>
    </div>
@endif
