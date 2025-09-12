<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Payslip;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public string $statusFilter = 'all';
    public string $monthFilter = '';
    
    protected $queryString = [
        'statusFilter' => ['except' => 'all'],
        'monthFilter' => ['except' => ''],
    ];
    
    public function with()
    {
        // Get payslips for current teacher
        $query = Payslip::with(['generatedBy', 'sessions.class.course'])
                        ->where('teacher_id', auth()->id());
        
        // Apply filters
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }
        
        $payslips = $query->orderBy('year', 'desc')
                         ->orderBy('month', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->paginate(10);
        
        // Get available months for filter
        $availableMonths = Payslip::where('teacher_id', auth()->id())
                                  ->selectRaw('month, MAX(year) as year')
                                  ->groupBy('month')
                                  ->orderBy('year', 'desc')
                                  ->orderBy('month', 'desc')
                                  ->get()
                                  ->map(function ($payslip) {
                                      $carbon = Carbon::createFromFormat('Y-m', $payslip->month);
                                      return [
                                          'value' => $payslip->month,
                                          'label' => $carbon->format('F Y'),
                                      ];
                                  });
        
        // Calculate statistics
        $totalEarnings = Payslip::where('teacher_id', auth()->id())->sum('total_amount');
        $paidEarnings = Payslip::where('teacher_id', auth()->id())->where('status', 'paid')->sum('total_amount');
        $pendingEarnings = Payslip::where('teacher_id', auth()->id())->whereIn('status', ['draft', 'finalized'])->sum('total_amount');
        $totalSessions = Payslip::where('teacher_id', auth()->id())->sum('total_sessions');
        
        $statistics = [
            'total_earnings' => $totalEarnings,
            'paid_earnings' => $paidEarnings,
            'pending_earnings' => $pendingEarnings,
            'total_sessions' => $totalSessions,
        ];
        
        return [
            'payslips' => $payslips,
            'availableMonths' => $availableMonths,
            'statistics' => $statistics
        ];
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatedMonthFilter()
    {
        $this->resetPage();
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6">
        <flux:heading size="xl">My Payslips</flux:heading>
        <flux:text class="mt-2">View your monthly payslips and earnings</flux:text>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">RM{{ number_format($statistics['total_earnings'], 2) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Earnings</div>
                </div>
                <flux:icon name="currency-dollar" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">RM{{ number_format($statistics['paid_earnings'], 2) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Paid Out</div>
                </div>
                <flux:icon name="check-circle" class="h-8 w-8 text-green-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">RM{{ number_format($statistics['pending_earnings'], 2) }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Pending</div>
                </div>
                <flux:icon name="clock" class="h-8 w-8 text-yellow-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $statistics['total_sessions'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Sessions</div>
                </div>
                <flux:icon name="calendar-days" class="h-8 w-8 text-purple-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <flux:select wire:model.live="statusFilter" placeholder="All Status" class="min-w-40">
                <option value="all">All Status</option>
                <option value="draft">Draft</option>
                <option value="finalized">Finalized</option>
                <option value="paid">Paid</option>
            </flux:select>
            
            <flux:select wire:model.live="monthFilter" placeholder="All Months" class="min-w-40">
                <option value="">All Months</option>
                @foreach($availableMonths as $month)
                    <option value="{{ $month['value'] }}">{{ $month['label'] }}</option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    @if($payslips->count() > 0)
        <!-- Payslips List -->
        <div class="space-y-4">
            @foreach($payslips as $payslip)
                <flux:card class="p-6 hover:shadow-lg transition-all duration-200">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                        <!-- Payslip Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <flux:heading size="sm" class="mb-2">{{ $payslip->formatted_month }}</flux:heading>
                                    <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-2">
                                        Generated by {{ $payslip->generatedBy->name }}
                                    </flux:text>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="flex flex-wrap gap-1 justify-end">
                                    @if($payslip->status === 'draft')
                                        <flux:badge color="yellow" size="sm">Draft</flux:badge>
                                    @elseif($payslip->status === 'finalized')
                                        <flux:badge color="blue" size="sm">Finalized</flux:badge>
                                    @elseif($payslip->status === 'paid')
                                        <flux:badge color="green" size="sm">Paid</flux:badge>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Payslip Details Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="calendar-days" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $payslip->total_sessions }} sessions</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="clock" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $payslip->generated_at->format('M d, Y') }}</span>
                                </div>
                                @if($payslip->status === 'paid' && $payslip->paid_at)
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <flux:icon name="check-circle" class="w-4 h-4 mr-2 text-gray-400" />
                                        <span>Paid {{ $payslip->paid_at->format('M d, Y') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Amount and Actions -->
                        <div class="flex flex-col items-end gap-3 lg:min-w-fit">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-4 py-2 rounded-lg">
                                RM{{ number_format($payslip->total_amount, 2) }}
                            </div>
                            
                            <flux:button size="sm" variant="outline" href="{{ route('teacher.payslips.show', $payslip) }}" wire:navigate>
                                <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                View Details
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-6">
            {{ $payslips->links() }}
        </div>
    @else
        <!-- Empty State -->
        <flux:card class="text-center py-12">
            <flux:icon name="document-text" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
            @if($statusFilter !== 'all' || $monthFilter)
                <flux:heading size="lg" class="mb-4">No Payslips Found</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    No payslips match your current filter criteria. Try adjusting your filters.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('statusFilter', 'all'); $set('monthFilter', '')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Payslips Yet</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    You don't have any payslips yet. Payslips will be generated once you have completed and verified sessions.
                </flux:text>
            @endif
        </flux:card>
    @endif
</div>