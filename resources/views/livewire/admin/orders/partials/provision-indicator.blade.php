@php
    $latestProvision = ($order->relationLoaded('provisioningRequests')
        ? $order->provisioningRequests
        : $order->provisioningRequests()->with('externalSystem')->get())
        ->sortByDesc('id')->first();
@endphp

@if ($latestProvision)
    @php
        [$pColor, $pIcon, $pLabel] = match ($latestProvision->status) {
            'succeeded' => ['emerald', 'check-circle', 'Provisioned'],
            'failed' => ['red', 'x-circle', 'Provision failed'],
            'processing' => ['amber', 'arrow-path', 'Provisioning'],
            default => ['zinc', 'clock', 'Queued'],
        };
    @endphp
    <flux:tooltip content="{{ $latestProvision->externalSystem?->name ?? 'External system' }}{{ $latestProvision->last_error ? ' — '.$latestProvision->last_error : '' }}">
        <flux:badge size="sm" :color="$pColor" :icon="$pIcon">
            {{ $pLabel }}@if ($latestProvision->externalSystem) · {{ $latestProvision->externalSystem->name }}@endif
        </flux:badge>
    </flux:tooltip>
@endif
