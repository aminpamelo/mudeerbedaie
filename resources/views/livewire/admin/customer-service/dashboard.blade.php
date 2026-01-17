<?php

use App\Models\ReturnRefund;
use App\Models\CustomerFeedback;
use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getStats(): array
    {
        return [
            'total_refunds' => ReturnRefund::count(),
            'pending_review' => ReturnRefund::where('status', 'pending_review')->count(),
            'approved' => ReturnRefund::where('action', 'approved')->count(),
            'rejected' => ReturnRefund::where('action', 'rejected')->count(),
            'refund_completed' => ReturnRefund::where('status', 'refund_completed')->count(),
            'total_refund_amount' => ReturnRefund::where('action', 'approved')->sum('refund_amount'),
            'this_month' => ReturnRefund::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'this_week' => ReturnRefund::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
        ];
    }

    public function getFeedbackStats(): array
    {
        return [
            'total' => CustomerFeedback::count(),
            'pending' => CustomerFeedback::where('status', 'pending')->count(),
            'responded' => CustomerFeedback::where('status', 'responded')->count(),
            'average_rating' => CustomerFeedback::getAverageRating(),
            'complaints' => CustomerFeedback::where('type', 'complaint')->count(),
            'compliments' => CustomerFeedback::where('type', 'compliment')->count(),
        ];
    }

    public function getRecentFeedback()
    {
        return CustomerFeedback::with(['order', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getRecentRefunds()
    {
        return ReturnRefund::with(['order', 'package', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getPendingRefunds()
    {
        return ReturnRefund::with(['order', 'package', 'customer'])
            ->where('action', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();
    }

    public function getRefundsByStatus(): array
    {
        return [
            'pending_review' => ReturnRefund::where('status', 'pending_review')->count(),
            'approved_pending_return' => ReturnRefund::where('status', 'approved_pending_return')->count(),
            'item_received' => ReturnRefund::where('status', 'item_received')->count(),
            'refund_processing' => ReturnRefund::where('status', 'refund_processing')->count(),
            'refund_completed' => ReturnRefund::where('status', 'refund_completed')->count(),
            'rejected' => ReturnRefund::where('status', 'rejected')->count(),
            'cancelled' => ReturnRefund::where('status', 'cancelled')->count(),
        ];
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Customer Service</flux:heading>
            <flux:text class="mt-2">Manage return and refund requests for orders and packages</flux:text>
        </div>

        <div class="flex gap-3">
            <flux:button variant="primary" :href="route('admin.customer-service.return-refunds.create')" wire:navigate>
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                    New Return Request
                </div>
            </flux:button>
        </div>
    </div>

    @php
        $stats = $this->getStats();
        $statusBreakdown = $this->getRefundsByStatus();
        $feedbackStats = $this->getFeedbackStats();
    @endphp

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Total Refund Requests</flux:text>
                    <flux:text class="text-3xl font-bold mt-1">{{ number_format($stats['total_refunds']) }}</flux:text>
                </div>
                <div class="bg-gray-100 rounded-full p-3">
                    <flux:icon name="arrow-path" class="w-6 h-6 text-gray-600" />
                </div>
            </div>
            <div class="mt-2">
                <flux:text size="sm" class="text-gray-500">{{ $stats['this_month'] }} this month</flux:text>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Pending Review</flux:text>
                    <flux:text class="text-3xl font-bold mt-1 text-yellow-600">{{ number_format($stats['pending_review']) }}</flux:text>
                </div>
                <div class="bg-yellow-100 rounded-full p-3">
                    <flux:icon name="clock" class="w-6 h-6 text-yellow-600" />
                </div>
            </div>
            <div class="mt-2">
                <flux:text size="sm" class="text-gray-500">Requires action</flux:text>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Approved</flux:text>
                    <flux:text class="text-3xl font-bold mt-1 text-green-600">{{ number_format($stats['approved']) }}</flux:text>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <flux:icon name="check-circle" class="w-6 h-6 text-green-600" />
                </div>
            </div>
            <div class="mt-2">
                <flux:text size="sm" class="text-gray-500">{{ $stats['refund_completed'] }} completed</flux:text>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Total Refunded</flux:text>
                    <flux:text class="text-3xl font-bold mt-1 text-blue-600">RM {{ number_format($stats['total_refund_amount'], 2) }}</flux:text>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <flux:icon name="banknotes" class="w-6 h-6 text-blue-600" />
                </div>
            </div>
            <div class="mt-2">
                <flux:text size="sm" class="text-gray-500">Approved refunds</flux:text>
            </div>
        </div>
    </div>

    <!-- Action Needed Section -->
    @if($stats['pending_review'] > 0)
        <div class="mb-6 bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-400 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600" />
                    <flux:heading size="lg" class="text-yellow-900">Action Needed</flux:heading>
                </div>
                <flux:button variant="outline" size="sm" :href="route('admin.customer-service.return-refunds.index', ['status' => 'pending_review'])" wire:navigate>
                    View All Pending
                </flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'pending_review']) }}" wire:navigate class="bg-white rounded-lg p-4 hover:shadow-md transition-shadow border border-gray-200">
                    <flux:text size="sm" class="text-gray-600">Pending Review</flux:text>
                    <div class="flex items-center justify-between mt-2">
                        <flux:text class="text-2xl font-bold text-yellow-600">{{ $statusBreakdown['pending_review'] }}</flux:text>
                        <flux:badge color="yellow" size="sm">Needs Review</flux:badge>
                    </div>
                </a>

                <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'approved_pending_return']) }}" wire:navigate class="bg-white rounded-lg p-4 hover:shadow-md transition-shadow border border-gray-200">
                    <flux:text size="sm" class="text-gray-600">Awaiting Return</flux:text>
                    <div class="flex items-center justify-between mt-2">
                        <flux:text class="text-2xl font-bold text-blue-600">{{ $statusBreakdown['approved_pending_return'] }}</flux:text>
                        <flux:badge color="blue" size="sm">Track Return</flux:badge>
                    </div>
                </a>

                <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'refund_processing']) }}" wire:navigate class="bg-white rounded-lg p-4 hover:shadow-md transition-shadow border border-gray-200">
                    <flux:text size="sm" class="text-gray-600">Refund Processing</flux:text>
                    <div class="flex items-center justify-between mt-2">
                        <flux:text class="text-2xl font-bold text-purple-600">{{ $statusBreakdown['refund_processing'] }}</flux:text>
                        <flux:badge color="purple" size="sm">Processing</flux:badge>
                    </div>
                </a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pending Refunds -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <flux:heading size="lg">Pending Review</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.return-refunds.index', ['action' => 'pending'])" wire:navigate>
                    View All
                </flux:button>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($this->getPendingRefunds() as $refund)
                    <a href="{{ route('admin.customer-service.return-refunds.show', $refund) }}" wire:navigate class="block px-6 py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:text class="font-medium">{{ $refund->refund_number }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $refund->getCustomerName() }}</flux:text>
                            </div>
                            <div class="text-right">
                                <flux:text class="font-semibold">RM {{ number_format($refund->refund_amount, 2) }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $refund->return_date->format('M j, Y') }}</flux:text>
                            </div>
                        </div>
                        <div class="mt-2">
                            <flux:text size="sm" class="text-gray-600">{{ Str::limit($refund->reason, 60) }}</flux:text>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center">
                        <flux:icon name="check-circle" class="w-12 h-12 mx-auto text-green-300 mb-2" />
                        <flux:text class="text-gray-500">No pending refunds</flux:text>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <flux:heading size="lg">Recent Activity</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.return-refunds.index')" wire:navigate>
                    View All
                </flux:button>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($this->getRecentRefunds() as $refund)
                    <a href="{{ route('admin.customer-service.return-refunds.show', $refund) }}" wire:navigate class="block px-6 py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center {{
                                    $refund->action === 'approved' ? 'bg-green-100' :
                                    ($refund->action === 'rejected' ? 'bg-red-100' : 'bg-yellow-100')
                                }}">
                                    <flux:icon name="{{
                                        $refund->action === 'approved' ? 'check' :
                                        ($refund->action === 'rejected' ? 'x-mark' : 'clock')
                                    }}" class="w-5 h-5 {{
                                        $refund->action === 'approved' ? 'text-green-600' :
                                        ($refund->action === 'rejected' ? 'text-red-600' : 'text-yellow-600')
                                    }}" />
                                </div>
                                <div>
                                    <flux:text class="font-medium">{{ $refund->refund_number }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500">{{ $refund->getOrderNumber() }}</flux:text>
                                </div>
                            </div>
                            <div class="text-right">
                                <flux:badge size="sm" color="{{ $refund->getStatusColor() }}">
                                    {{ $refund->getStatusLabel() }}
                                </flux:badge>
                                <flux:text size="sm" class="text-gray-500 mt-1">{{ $refund->created_at->diffForHumans() }}</flux:text>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-8 text-center">
                        <flux:icon name="inbox" class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                        <flux:text class="text-gray-500">No refund requests yet</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="mt-6 bg-white rounded-lg border border-gray-200 p-6">
        <flux:heading size="lg" class="mb-4">Refund Status Breakdown</flux:heading>
        <div class="grid grid-cols-2 md:grid-cols-7 gap-4">
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'pending_review']) }}" wire:navigate class="text-center p-4 rounded-lg bg-yellow-50 hover:bg-yellow-100 transition-colors">
                <flux:text class="text-2xl font-bold text-yellow-600">{{ number_format($statusBreakdown['pending_review']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Pending Review</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'approved_pending_return']) }}" wire:navigate class="text-center p-4 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                <flux:text class="text-2xl font-bold text-blue-600">{{ number_format($statusBreakdown['approved_pending_return']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Awaiting Return</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'item_received']) }}" wire:navigate class="text-center p-4 rounded-lg bg-purple-50 hover:bg-purple-100 transition-colors">
                <flux:text class="text-2xl font-bold text-purple-600">{{ number_format($statusBreakdown['item_received']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Item Received</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'refund_processing']) }}" wire:navigate class="text-center p-4 rounded-lg bg-cyan-50 hover:bg-cyan-100 transition-colors">
                <flux:text class="text-2xl font-bold text-cyan-600">{{ number_format($statusBreakdown['refund_processing']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Processing</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'refund_completed']) }}" wire:navigate class="text-center p-4 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
                <flux:text class="text-2xl font-bold text-green-600">{{ number_format($statusBreakdown['refund_completed']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Completed</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'rejected']) }}" wire:navigate class="text-center p-4 rounded-lg bg-red-50 hover:bg-red-100 transition-colors">
                <flux:text class="text-2xl font-bold text-red-600">{{ number_format($statusBreakdown['rejected']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Rejected</flux:text>
            </a>
            <a href="{{ route('admin.customer-service.return-refunds.index', ['status' => 'cancelled']) }}" wire:navigate class="text-center p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                <flux:text class="text-2xl font-bold text-gray-600">{{ number_format($statusBreakdown['cancelled']) }}</flux:text>
                <flux:text size="sm" class="text-gray-600">Cancelled</flux:text>
            </a>
        </div>
    </div>

    <!-- Customer Feedback Section -->
    <div class="mt-6 bg-white rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">Customer Feedback</flux:heading>
                @if($feedbackStats['average_rating'] > 0)
                    <div class="flex items-center gap-1 text-yellow-500">
                        <span class="text-lg font-bold">{{ number_format($feedbackStats['average_rating'], 1) }}</span>
                        <flux:icon name="star" variant="solid" class="w-5 h-5" />
                    </div>
                @endif
            </div>
            <flux:button variant="ghost" size="sm" :href="route('admin.customer-service.feedback.index')" wire:navigate>
                View All
            </flux:button>
        </div>

        <!-- Feedback Stats -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <a href="{{ route('admin.customer-service.feedback.index') }}" wire:navigate class="text-center p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                    <flux:text class="text-xl font-bold text-gray-700">{{ number_format($feedbackStats['total']) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">Total</flux:text>
                </a>
                <a href="{{ route('admin.customer-service.feedback.index', ['status' => 'pending']) }}" wire:navigate class="text-center p-3 rounded-lg bg-yellow-50 hover:bg-yellow-100 transition-colors">
                    <flux:text class="text-xl font-bold text-yellow-600">{{ number_format($feedbackStats['pending']) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">Pending</flux:text>
                </a>
                <a href="{{ route('admin.customer-service.feedback.index', ['status' => 'responded']) }}" wire:navigate class="text-center p-3 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
                    <flux:text class="text-xl font-bold text-green-600">{{ number_format($feedbackStats['responded']) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">Responded</flux:text>
                </a>
                <a href="{{ route('admin.customer-service.feedback.index', ['type' => 'complaint']) }}" wire:navigate class="text-center p-3 rounded-lg bg-red-50 hover:bg-red-100 transition-colors">
                    <flux:text class="text-xl font-bold text-red-600">{{ number_format($feedbackStats['complaints']) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">Complaints</flux:text>
                </a>
                <a href="{{ route('admin.customer-service.feedback.index', ['type' => 'compliment']) }}" wire:navigate class="text-center p-3 rounded-lg bg-emerald-50 hover:bg-emerald-100 transition-colors">
                    <flux:text class="text-xl font-bold text-emerald-600">{{ number_format($feedbackStats['compliments']) }}</flux:text>
                    <flux:text size="sm" class="text-gray-500">Compliments</flux:text>
                </a>
            </div>
        </div>

        <!-- Recent Feedback -->
        <div class="divide-y divide-gray-200">
            @forelse($this->getRecentFeedback() as $feedback)
                <a href="{{ route('admin.customer-service.feedback.show', $feedback) }}" wire:navigate class="block px-6 py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @php
                                $typeColors = [
                                    'complaint' => 'bg-red-100 text-red-600',
                                    'suggestion' => 'bg-blue-100 text-blue-600',
                                    'compliment' => 'bg-green-100 text-green-600',
                                    'question' => 'bg-purple-100 text-purple-600',
                                    'other' => 'bg-gray-100 text-gray-600',
                                ];
                                $typeIcons = [
                                    'complaint' => 'exclamation-circle',
                                    'suggestion' => 'light-bulb',
                                    'compliment' => 'heart',
                                    'question' => 'question-mark-circle',
                                    'other' => 'chat-bubble-left',
                                ];
                            @endphp
                            <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $typeColors[$feedback->type] ?? 'bg-gray-100' }}">
                                <flux:icon name="{{ $typeIcons[$feedback->type] ?? 'chat-bubble-left' }}" class="w-5 h-5" />
                            </div>
                            <div>
                                <flux:text class="font-medium">{{ Str::limit($feedback->subject, 40) }}</flux:text>
                                <flux:text size="sm" class="text-gray-500">{{ $feedback->getCustomerName() }}</flux:text>
                            </div>
                        </div>
                        <div class="text-right">
                            @if($feedback->rating)
                                <span class="text-yellow-500 text-sm">{{ $feedback->getRatingStars() }}</span>
                            @endif
                            <flux:text size="sm" class="text-gray-500">{{ $feedback->created_at->diffForHumans() }}</flux:text>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-6 py-8 text-center">
                    <flux:icon name="chat-bubble-left-right" class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                    <flux:text class="text-gray-500">No feedback received yet</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Quick Links -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <a href="{{ route('admin.customer-service.return-refunds.index') }}" wire:navigate class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="bg-cyan-100 rounded-full p-3">
                    <flux:icon name="clipboard-document-list" class="w-6 h-6 text-cyan-600" />
                </div>
                <div>
                    <flux:heading size="lg">All Return Requests</flux:heading>
                    <flux:text size="sm" class="text-gray-500">View and manage all refund requests</flux:text>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.customer-service.return-refunds.create') }}" wire:navigate class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="bg-green-100 rounded-full p-3">
                    <flux:icon name="plus-circle" class="w-6 h-6 text-green-600" />
                </div>
                <div>
                    <flux:heading size="lg">New Return Request</flux:heading>
                    <flux:text size="sm" class="text-gray-500">Create a new refund request</flux:text>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.customer-service.feedback.index') }}" wire:navigate class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="bg-yellow-100 rounded-full p-3">
                    <flux:icon name="chat-bubble-left-right" class="w-6 h-6 text-yellow-600" />
                </div>
                <div>
                    <flux:heading size="lg">Customer Feedback</flux:heading>
                    <flux:text size="sm" class="text-gray-500">View and respond to customer feedback</flux:text>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.orders.index') }}" wire:navigate class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="bg-purple-100 rounded-full p-3">
                    <flux:icon name="shopping-bag" class="w-6 h-6 text-purple-600" />
                </div>
                <div>
                    <flux:heading size="lg">View Orders</flux:heading>
                    <flux:text size="sm" class="text-gray-500">Manage product orders</flux:text>
                </div>
            </div>
        </a>
    </div>
</div>
