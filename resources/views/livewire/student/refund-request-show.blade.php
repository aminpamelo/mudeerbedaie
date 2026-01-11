<?php

use App\Models\ReturnRefund;
use Livewire\Volt\Component;

new class extends Component
{
    public ReturnRefund $refund;

    public function mount(ReturnRefund $refund): void
    {
        if ($refund->customer_id !== auth()->id()) {
            abort(403, 'You can only view your own refund requests.');
        }

        $this->refund = $refund->load(['order.items', 'processedBy']);
    }
}; ?>

<div>
    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2">
                <flux:icon name="check-circle" class="w-5 h-5 text-green-600" />
                <flux:text class="text-green-800">{{ session('success') }}</flux:text>
            </div>
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <flux:heading size="xl">{{ $refund->refund_number }}</flux:heading>
                <flux:badge size="lg" color="{{ $refund->getStatusColor() }}">
                    {{ $refund->getStatusLabel() }}
                </flux:badge>
            </div>
            <flux:text class="text-gray-600">Submitted {{ $refund->created_at->format('M j, Y g:i A') }}</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('student.refund-requests') }}">
            <div class="flex items-center justify-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Requests
            </div>
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Status Timeline -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Request Status</flux:heading>

                <div class="relative">
                    @php
                        $steps = [
                            ['status' => 'pending_review', 'label' => 'Pending Review', 'icon' => 'clock'],
                            ['status' => 'approved_pending_return', 'label' => 'Approved', 'icon' => 'check'],
                            ['status' => 'item_received', 'label' => 'Item Received', 'icon' => 'inbox'],
                            ['status' => 'refund_processing', 'label' => 'Processing', 'icon' => 'arrow-path'],
                            ['status' => 'refund_completed', 'label' => 'Completed', 'icon' => 'check-circle'],
                        ];
                        $currentIndex = array_search($refund->status, array_column($steps, 'status'));
                        $isRejected = $refund->status === 'rejected';
                        $isCancelled = $refund->status === 'cancelled';
                    @endphp

                    @if($isRejected)
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <flux:icon name="x-circle" class="w-8 h-8 text-red-500" />
                                <div>
                                    <flux:text class="font-semibold text-red-800">Request Rejected</flux:text>
                                    @if($refund->action_reason)
                                        <flux:text class="text-red-700 mt-1">{{ $refund->action_reason }}</flux:text>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif($isCancelled)
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <flux:icon name="x-circle" class="w-8 h-8 text-gray-500" />
                                <div>
                                    <flux:text class="font-semibold text-gray-800">Request Cancelled</flux:text>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            @foreach($steps as $index => $step)
                                <div class="flex flex-col items-center flex-1">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $index <= $currentIndex ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500' }}">
                                        <flux:icon name="{{ $step['icon'] }}" class="w-5 h-5" />
                                    </div>
                                    <flux:text size="xs" class="mt-2 text-center {{ $index <= $currentIndex ? 'text-green-600 font-medium' : 'text-gray-500' }}">
                                        {{ $step['label'] }}
                                    </flux:text>
                                </div>
                                @if(!$loop->last)
                                    <div class="flex-1 h-1 mx-2 {{ $index < $currentIndex ? 'bg-green-500' : 'bg-gray-200' }}"></div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Reason -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Refund Reason</flux:heading>
                <div class="prose prose-sm max-w-none">
                    {!! nl2br(e($refund->reason)) !!}
                </div>

                @if($refund->notes)
                    <div class="mt-4 pt-4 border-t">
                        <flux:text class="text-gray-500 font-medium">Additional Notes</flux:text>
                        <flux:text class="mt-1">{{ $refund->notes }}</flux:text>
                    </div>
                @endif
            </flux:card>

            <!-- Admin Response -->
            @if($refund->action_reason && $refund->action !== 'pending')
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Admin Response</flux:heading>
                    <div class="p-4 {{ $refund->action === 'approved' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border rounded-lg">
                        <div class="flex items-start gap-3">
                            @if($refund->action === 'approved')
                                <flux:icon name="check-circle" class="w-6 h-6 text-green-600 mt-0.5" />
                            @else
                                <flux:icon name="x-circle" class="w-6 h-6 text-red-600 mt-0.5" />
                            @endif
                            <div>
                                <flux:text class="font-medium {{ $refund->action === 'approved' ? 'text-green-800' : 'text-red-800' }}">
                                    {{ $refund->action === 'approved' ? 'Approved' : 'Rejected' }}
                                    @if($refund->processedBy)
                                        by {{ $refund->processedBy->name }}
                                    @endif
                                </flux:text>
                                <flux:text class="mt-1 {{ $refund->action === 'approved' ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $refund->action_reason }}
                                </flux:text>
                                @if($refund->action_date)
                                    <flux:text size="sm" class="text-gray-500 mt-2">
                                        {{ $refund->action_date->format('M j, Y g:i A') }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif

            <!-- Order Details -->
            @if($refund->order)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Order Details</flux:heading>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Number</flux:text>
                            <flux:text class="font-medium">{{ $refund->order->order_number }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Date</flux:text>
                            <flux:text>{{ $refund->order->created_at->format('M j, Y') }}</flux:text>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Status</flux:text>
                            <flux:badge size="sm">{{ ucfirst($refund->order->status) }}</flux:badge>
                        </div>
                        <div>
                            <flux:text size="sm" class="text-gray-500">Order Total</flux:text>
                            <flux:text class="font-semibold">RM {{ number_format($refund->order->total_amount, 2) }}</flux:text>
                        </div>
                    </div>

                    @if($refund->order->items->count() > 0)
                        <div class="border-t pt-4">
                            <flux:text class="text-gray-500 font-medium mb-2">Items</flux:text>
                            <div class="space-y-2">
                                @foreach($refund->order->items as $item)
                                    <div class="flex justify-between py-2 border-b border-gray-100 last:border-0">
                                        <div>
                                            <flux:text>{{ $item->product_name }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500">Qty: {{ $item->quantity_ordered }}</flux:text>
                                        </div>
                                        <flux:text class="font-medium">RM {{ number_format($item->total_price, 2) }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Refund Summary -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Refund Summary</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between">
                        <flux:text class="text-gray-500">Refund Amount</flux:text>
                        <flux:text class="font-bold text-green-600 text-lg">RM {{ number_format($refund->refund_amount, 2) }}</flux:text>
                    </div>
                    <div class="flex justify-between">
                        <flux:text class="text-gray-500">Return Date</flux:text>
                        <flux:text>{{ $refund->return_date->format('M j, Y') }}</flux:text>
                    </div>
                    <div class="flex justify-between">
                        <flux:text class="text-gray-500">Action</flux:text>
                        <flux:badge size="sm" color="{{ $refund->getActionColor() }}">{{ $refund->getActionLabel() }}</flux:badge>
                    </div>
                </div>
            </flux:card>

            <!-- Bank Details -->
            @if($refund->bank_name || $refund->account_number)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Bank Details</flux:heading>

                    <div class="space-y-3">
                        @if($refund->bank_name)
                            <div>
                                <flux:text size="sm" class="text-gray-500">Bank Name</flux:text>
                                <flux:text>{{ $refund->bank_name }}</flux:text>
                            </div>
                        @endif
                        @if($refund->account_number)
                            <div>
                                <flux:text size="sm" class="text-gray-500">Account Number</flux:text>
                                <flux:text class="font-mono">{{ $refund->account_number }}</flux:text>
                            </div>
                        @endif
                        @if($refund->account_holder_name)
                            <div>
                                <flux:text size="sm" class="text-gray-500">Account Holder</flux:text>
                                <flux:text>{{ $refund->account_holder_name }}</flux:text>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endif

            <!-- Tracking -->
            @if($refund->tracking_number)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Return Tracking</flux:heading>

                    <div>
                        <flux:text size="sm" class="text-gray-500">Tracking Number</flux:text>
                        <flux:text class="font-mono">{{ $refund->tracking_number }}</flux:text>
                    </div>
                </flux:card>
            @endif

            <!-- Timeline -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Timeline</flux:heading>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <flux:text class="text-gray-500">Submitted</flux:text>
                        <flux:text>{{ $refund->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    @if($refund->action_date)
                        <div class="flex justify-between">
                            <flux:text class="text-gray-500">Reviewed</flux:text>
                            <flux:text>{{ $refund->action_date->format('M j, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <flux:text class="text-gray-500">Last Updated</flux:text>
                        <flux:text>{{ $refund->updated_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>
