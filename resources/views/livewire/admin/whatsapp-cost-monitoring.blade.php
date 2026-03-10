<?php

use App\Services\WhatsApp\WhatsAppCostService;
use App\Models\WhatsAppMessage;
use Livewire\WithPagination;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $period = 'month';
    public string $startDate = '';
    public string $endDate = '';
    public bool $syncing = false;

    public function mount(): void
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->setPeriodDates();
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $this->setPeriodDates();
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function syncNow(): void
    {
        $this->syncing = true;

        try {
            $costService = app(WhatsAppCostService::class);
            $count = $costService->syncDailyAnalytics();

            $this->dispatch('notify', type: 'success', message: "Synced {$count} records from Meta API.");
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Sync failed: ' . $e->getMessage());
        }

        $this->syncing = false;
    }

    public function with(): array
    {
        $costService = app(WhatsAppCostService::class);

        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $dashboardData = $costService->getDashboardData($start, $end);

        $recentMessages = WhatsAppMessage::query()
            ->outbound()
            ->whereNotNull('estimated_cost_usd')
            ->with('conversation')
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->paginate(15);

        return [
            ...$dashboardData,
            'recentMessages' => $recentMessages,
        ];
    }

    private function setPeriodDates(): void
    {
        match ($this->period) {
            'today' => $this->setDates(now()->startOfDay(), now()->endOfDay()),
            'week' => $this->setDates(now()->startOfWeek(), now()->endOfWeek()),
            'month' => $this->setDates(now()->startOfMonth(), now()->endOfMonth()),
            default => null,
        };
    }

    private function setDates(Carbon $start, Carbon $end): void
    {
        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
    }
} ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Cost Monitoring</flux:heading>
            <flux:text class="mt-2">
                Track WhatsApp messaging costs from Meta Business API.
                @if($lastSyncedAt)
                    Last synced: {{ \Carbon\Carbon::parse($lastSyncedAt)->diffForHumans() }}
                @else
                    No data synced yet.
                @endif
            </flux:text>
        </div>
        <flux:button variant="primary" wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow">
            <div class="flex items-center justify-center">
                <flux:icon name="arrow-path" class="w-4 h-4 mr-1" wire:loading.class="animate-spin" wire:target="syncNow" />
                Sync Now
            </div>
        </flux:button>
    </div>

    <!-- Period Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <flux:button size="sm" :variant="$period === 'today' ? 'primary' : 'outline'" wire:click="setPeriod('today')">
            Today
        </flux:button>
        <flux:button size="sm" :variant="$period === 'week' ? 'primary' : 'outline'" wire:click="setPeriod('week')">
            This Week
        </flux:button>
        <flux:button size="sm" :variant="$period === 'month' ? 'primary' : 'outline'" wire:click="setPeriod('month')">
            This Month
        </flux:button>

        <flux:separator vertical class="h-6" />

        <div class="flex items-center gap-2">
            <flux:input type="date" wire:model.live="startDate" size="sm" />
            <flux:text>to</flux:text>
            <flux:input type="date" wire:model.live="endDate" size="sm" />
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Total Cost</flux:heading>
                    <flux:heading size="xl" class="text-emerald-600">RM {{ number_format($summary['totalCostMyr'], 2) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">USD {{ number_format($summary['totalCostUsd'], 2) }}</flux:text>
                </div>
                <flux:icon name="currency-dollar" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Total Messages</flux:heading>
                    <flux:heading size="xl" class="text-blue-600">{{ number_format($summary['totalMessages']) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">All categories</flux:text>
                </div>
                <flux:icon name="chat-bubble-left-right" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Avg Cost / Message</flux:heading>
                    <flux:heading size="xl" class="text-amber-600">RM {{ number_format($summary['avgCostPerMessage'], 4) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">Excluding free messages</flux:text>
                </div>
                <flux:icon name="calculator" class="w-8 h-8 text-amber-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Free Service Msgs</flux:heading>
                    <flux:heading size="xl" class="text-green-600">{{ number_format($summary['freeServiceMessages']) }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500">Replies within 24h</flux:text>
                </div>
                <flux:icon name="chat-bubble-bottom-center-text" class="w-8 h-8 text-green-500" />
            </div>
        </flux:card>
    </div>

    <!-- Category Breakdown -->
    <div class="grid gap-6 md:grid-cols-3 mb-6">
        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="sm">Marketing</flux:heading>
                <flux:badge color="purple" size="sm">{{ number_format($categories['MARKETING']['volume']) }} msgs</flux:badge>
            </div>
            <flux:heading size="lg" class="text-purple-600">RM {{ number_format($categories['MARKETING']['costMyr'], 2) }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">USD {{ number_format($categories['MARKETING']['costUsd'], 4) }}</flux:text>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="sm">Utility</flux:heading>
                <flux:badge color="blue" size="sm">{{ number_format($categories['UTILITY']['volume']) }} msgs</flux:badge>
            </div>
            <flux:heading size="lg" class="text-blue-600">RM {{ number_format($categories['UTILITY']['costMyr'], 2) }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">USD {{ number_format($categories['UTILITY']['costUsd'], 4) }}</flux:text>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between mb-2">
                <flux:heading size="sm">Authentication</flux:heading>
                <flux:badge color="amber" size="sm">{{ number_format($categories['AUTHENTICATION']['volume']) }} msgs</flux:badge>
            </div>
            <flux:heading size="lg" class="text-amber-600">RM {{ number_format($categories['AUTHENTICATION']['costMyr'], 2) }}</flux:heading>
            <flux:text size="sm" class="text-gray-500">USD {{ number_format($categories['AUTHENTICATION']['costUsd'], 4) }}</flux:text>
        </flux:card>
    </div>

    <!-- Daily Cost Trend -->
    @if($dailyTrend->isNotEmpty())
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Daily Cost Trend</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-2 text-left font-medium text-gray-600">Date</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-600">Messages</th>
                        <th class="px-4 py-2 text-right font-medium text-purple-600">Marketing</th>
                        <th class="px-4 py-2 text-right font-medium text-blue-600">Utility</th>
                        <th class="px-4 py-2 text-right font-medium text-amber-600">Auth</th>
                        <th class="px-4 py-2 text-right font-medium text-gray-900">Total (MYR)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyTrend as $day)
                    <tr class="border-b border-gray-100 hover:bg-gray-50" wire:key="trend-{{ $day['date'] }}">
                        <td class="px-4 py-2 text-gray-900">{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-right text-gray-600">{{ number_format($day['volume']) }}</td>
                        <td class="px-4 py-2 text-right text-purple-600">
                            RM {{ number_format($day['categories']['MARKETING']['costMyr'] ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-2 text-right text-blue-600">
                            RM {{ number_format($day['categories']['UTILITY']['costMyr'] ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-2 text-right text-amber-600">
                            RM {{ number_format($day['categories']['AUTHENTICATION']['costMyr'] ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-gray-900">
                            RM {{ number_format($day['costMyr'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                        <td class="px-4 py-2 text-gray-900">Total</td>
                        <td class="px-4 py-2 text-right text-gray-900">{{ number_format($dailyTrend->sum('volume')) }}</td>
                        <td class="px-4 py-2 text-right text-purple-600">
                            RM {{ number_format($dailyTrend->sum(fn($d) => $d['categories']['MARKETING']['costMyr'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-2 text-right text-blue-600">
                            RM {{ number_format($dailyTrend->sum(fn($d) => $d['categories']['UTILITY']['costMyr'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-2 text-right text-amber-600">
                            RM {{ number_format($dailyTrend->sum(fn($d) => $d['categories']['AUTHENTICATION']['costMyr'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-2 text-right text-gray-900">
                            RM {{ number_format($dailyTrend->sum('costMyr'), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </flux:card>
    @endif

    <!-- Recent Messages with Cost -->
    <flux:card>
        <flux:heading size="lg" class="mb-4">Recent Messages (with Estimated Cost)</flux:heading>

        @if($recentMessages->isEmpty())
            <div class="text-center py-8">
                <flux:icon name="chat-bubble-left-right" class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <flux:text class="text-gray-500">No outbound messages with cost data for this period.</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Date</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Phone</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Template</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Type</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Est. Cost (USD)</th>
                            <th class="px-4 py-2 text-right font-medium text-gray-600">Est. Cost (MYR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentMessages as $message)
                        <tr class="border-b border-gray-100 hover:bg-gray-50" wire:key="msg-{{ $message->id }}">
                            <td class="px-4 py-2 text-gray-600">{{ $message->created_at->format('d M H:i') }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $message->conversation->phone_number ?? '-' }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $message->template_name ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <flux:badge size="sm" color="{{ $message->type === 'template' ? 'blue' : 'gray' }}">
                                    {{ $message->type }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-2">
                                <flux:badge size="sm" color="{{ $message->status === 'delivered' || $message->status === 'read' ? 'emerald' : ($message->status === 'failed' ? 'red' : 'gray') }}">
                                    {{ $message->status }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-2 text-right text-gray-900">
                                ${{ number_format($message->estimated_cost_usd, 4) }}
                            </td>
                            <td class="px-4 py-2 text-right font-medium text-gray-900">
                                RM {{ number_format($message->estimated_cost_usd * config('whatsapp-pricing.usd_to_myr', 4.50), 4) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $recentMessages->links() }}
            </div>
        @endif
    </flux:card>

    <!-- Pricing Reference -->
    <flux:card class="mt-6">
        <flux:heading size="sm" class="mb-2">Malaysia Pricing Reference (per message)</flux:heading>
        <div class="flex flex-wrap gap-4 text-sm">
            <div class="flex items-center gap-2">
                <flux:badge color="purple" size="sm">Marketing</flux:badge>
                <span class="text-gray-600">~RM 0.39 ($0.086)</span>
            </div>
            <div class="flex items-center gap-2">
                <flux:badge color="blue" size="sm">Utility</flux:badge>
                <span class="text-gray-600">~RM 0.06 ($0.014)</span>
            </div>
            <div class="flex items-center gap-2">
                <flux:badge color="amber" size="sm">Authentication</flux:badge>
                <span class="text-gray-600">~RM 0.06 ($0.014)</span>
            </div>
            <div class="flex items-center gap-2">
                <flux:badge color="green" size="sm">Service</flux:badge>
                <span class="text-gray-600">FREE (within 24h)</span>
            </div>
        </div>
    </flux:card>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = true;
            message = $event.detail.message || 'Operation successful';
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 4000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div x-show="type === 'success'" class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg">
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div x-show="type === 'error'" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg">
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
        <div x-show="type === 'info'" class="flex items-center gap-2 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg shadow-lg">
            <flux:icon.information-circle class="w-5 h-5 text-blue-600" />
            <span x-text="message"></span>
        </div>
    </div>
</div>
