<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayslipGenerationService;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public string $monthFilter = '';
    public string $teacherFilter = 'all';
    public string $statusFilter = 'all';
    public string $search = '';
    public bool $showGenerateModal = false;
    public bool $showPreviewModal = false;
    public bool $showResultsModal = false;
    public bool $isGenerating = false;
    public string $generateMonth = '';
    public array $selectedTeachers = [];
    public array $generationPreview = [];
    public array $generationResults = [];
    
    protected $queryString = [
        'search' => ['except' => ''],
        'monthFilter' => ['except' => ''],
        'teacherFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
    ];
    
    public function with()
    {
        // Get all teachers and available months for filters
        $teachers = User::whereHas('teacher')->orderBy('name')->get();
        $availableMonths = app(PayslipGenerationService::class)->getAvailableMonthsForPayslips();
        
        // Build payslips query
        $query = Payslip::with(['teacher', 'generatedBy']);
        
        // Apply filters
        if ($this->monthFilter) {
            $query->where('month', $this->monthFilter);
        }
        
        if ($this->teacherFilter !== 'all') {
            $query->where('teacher_id', $this->teacherFilter);
        }
        
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply search
        if ($this->search) {
            $query->whereHas('teacher', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }
        
        $payslips = $query->orderBy('year', 'desc')
                         ->orderBy('month', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->paginate(15);
        
        // Calculate statistics
        $totalPayslips = Payslip::count();
        $draftPayslips = Payslip::where('status', 'draft')->count();
        $finalizedPayslips = Payslip::where('status', 'finalized')->count();
        $paidPayslips = Payslip::where('status', 'paid')->count();
        
        $statistics = [
            'total_payslips' => $totalPayslips,
            'draft_payslips' => $draftPayslips,
            'finalized_payslips' => $finalizedPayslips,
            'paid_payslips' => $paidPayslips,
        ];
        
        return [
            'payslips' => $payslips,
            'teachers' => $teachers,
            'availableMonths' => $availableMonths,
            'statistics' => $statistics
        ];
    }
    
    public function updatedMonthFilter()
    {
        $this->resetPage();
    }
    
    public function updatedTeacherFilter()
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    
    public function updatedSearch()
    {
        $this->resetPage();
    }
    
    public function clearSearch()
    {
        $this->search = '';
        $this->resetPage();
    }
    
    public function openGenerateModal()
    {
        $this->showGenerateModal = true;
        $this->generateMonth = now()->format('Y-m');
        $this->selectedTeachers = [];
    }
    
    public function closeGenerateModal()
    {
        $this->showGenerateModal = false;
        $this->showPreviewModal = false;
        $this->showResultsModal = false;
        $this->generateMonth = '';
        $this->selectedTeachers = [];
        $this->generationPreview = [];
        $this->generationResults = [];
        $this->isGenerating = false;
    }
    
    public function selectAllTeachers()
    {
        $teachers = User::whereHas('teacher')->orderBy('name')->get();
        $this->selectedTeachers = $teachers->pluck('id')->toArray();
    }
    
    public function deselectAllTeachers()
    {
        $this->selectedTeachers = [];
    }
    
    public function generatePreview()
    {
        $this->validate([
            'generateMonth' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'selectedTeachers' => 'required|array|min:1',
            'selectedTeachers.*' => 'exists:users,id',
        ]);

        $service = app(PayslipGenerationService::class);
        $this->generationPreview = [];
        
        foreach ($this->selectedTeachers as $teacherId) {
            $teacher = User::find($teacherId);
            if (!$teacher) continue;
            
            $canGenerate = $service->canGeneratePayslipForTeacher($teacher, $this->generateMonth);
            $eligibleSessions = $service->getEligibleSessionsForTeacherAndMonth($teacher, $this->generateMonth);
            
            $this->generationPreview[] = [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'can_generate' => $canGenerate['can_generate'],
                'reason' => $canGenerate['reason'] ?? null,
                'sessions_count' => $canGenerate['eligible_sessions_count'] ?? 0,
                'total_amount' => $canGenerate['total_amount'] ?? 0,
                'existing_payslip_id' => $canGenerate['existing_payslip_id'] ?? null,
            ];
        }
        
        $this->showGenerateModal = false;
        $this->showPreviewModal = true;
    }
    
    public function confirmGeneration()
    {
        $this->showPreviewModal = false;
        $this->isGenerating = true;
        $this->generationResults = [
            'successful' => [],
            'failed' => [],
            'total_amount' => 0,
            'total_sessions' => 0
        ];
        
        $service = app(PayslipGenerationService::class);
        
        foreach ($this->generationPreview as $preview) {
            if (!$preview['can_generate']) {
                $this->generationResults['failed'][] = [
                    'teacher_name' => $preview['teacher_name'],
                    'error' => $this->getReadableError($preview['reason']),
                    'details' => $preview['reason']
                ];
                continue;
            }
            
            try {
                $teacher = User::find($preview['teacher_id']);
                $payslip = $service->generateForTeacher($teacher, $this->generateMonth, auth()->user());
                
                $this->generationResults['successful'][] = [
                    'teacher_name' => $teacher->name,
                    'sessions_count' => $payslip->total_sessions,
                    'amount' => $payslip->total_amount,
                    'payslip_id' => $payslip->id
                ];
                
                $this->generationResults['total_amount'] += $payslip->total_amount;
                $this->generationResults['total_sessions'] += $payslip->total_sessions;
                
            } catch (\Exception $e) {
                $this->generationResults['failed'][] = [
                    'teacher_name' => $preview['teacher_name'],
                    'error' => $this->getReadableError($e->getMessage()),
                    'details' => $e->getMessage()
                ];
            }
        }
        
        $this->isGenerating = false;
        $this->showResultsModal = true;
    }
    
    public function getReadableError(string $error): string
    {
        if (str_contains($error, 'already exists')) {
            return 'Payslip already exists for this month';
        }
        
        if (str_contains($error, 'No eligible sessions')) {
            return 'No eligible sessions found for this teacher this month';
        }
        
        if (str_contains($error, 'teacher.teacher')) {
            return 'Teacher profile not properly configured';
        }
        
        return 'An unexpected error occurred';
    }
    
    public function closePreviewModal()
    {
        $this->showPreviewModal = false;
        $this->generationPreview = [];
        $this->showGenerateModal = true;
    }
    
    public function closeResultsModal()
    {
        $this->showResultsModal = false;
        $this->generationResults = [];
        $this->closeGenerateModal();
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payslips</flux:heading>
            <flux:text class="mt-2">Manage teacher payslips and payout processing</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button wire:click="openGenerateModal" variant="primary">
                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                Generate Payslips
            </flux:button>
        </div>
    </div>

    @if(session('success'))
        <flux:card class="p-4 mb-6 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
            <flux:text class="text-green-800 dark:text-green-200">{{ session('success') }}</flux:text>
        </flux:card>
    @endif
    
    @if(session('error'))
        <flux:card class="p-4 mb-6 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
            <flux:text class="text-red-800 dark:text-red-200">{{ session('error') }}</flux:text>
        </flux:card>
    @endif

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $statistics['total_payslips'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Payslips</div>
                </div>
                <flux:icon name="document-text" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $statistics['draft_payslips'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Draft</div>
                </div>
                <flux:icon name="pencil-square" class="h-8 w-8 text-yellow-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $statistics['finalized_payslips'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Finalized</div>
                </div>
                <flux:icon name="check-circle" class="h-8 w-8 text-blue-500" />
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $statistics['paid_payslips'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Paid</div>
                </div>
                <flux:icon name="banknotes" class="h-8 w-8 text-green-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters and Search -->
    <flux:card class="p-6 mb-6">
        <div class="space-y-4">
            <!-- Search Bar -->
            <div class="flex gap-3">
                <div class="flex-1 relative">
                    <flux:input 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search by teacher name..."
                        class="w-full"
                    />
                    @if($search)
                        <flux:button 
                            wire:click="clearSearch" 
                            variant="ghost" 
                            size="sm" 
                            class="absolute right-2 top-1/2 -translate-y-1/2"
                        >
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
            </div>
            
            <!-- Filters -->
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="flex flex-col sm:flex-row gap-3">
                    <flux:select wire:model.live="monthFilter" placeholder="All Months" class="min-w-40">
                        <option value="">All Months</option>
                        @foreach($availableMonths as $month)
                            <option value="{{ $month['value'] }}">{{ $month['label'] }}</option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select wire:model.live="teacherFilter" placeholder="All Teachers" class="min-w-40">
                        <option value="all">All Teachers</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select wire:model.live="statusFilter" placeholder="All Status" class="min-w-32">
                        <option value="all">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="finalized">Finalized</option>
                        <option value="paid">Paid</option>
                    </flux:select>
                </div>
            </div>
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
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <flux:heading size="sm" class="mb-2">{{ $payslip->teacher->name }}</flux:heading>
                                            <flux:text size="sm" class="text-gray-600 dark:text-gray-400 mb-2">
                                                {{ $payslip->formatted_month }}
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
                                </div>
                            </div>
                            
                            <!-- Payslip Details Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="calendar-days" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $payslip->total_sessions }} sessions</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="banknotes" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>RM{{ number_format($payslip->total_amount, 2) }}</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="user" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>By {{ $payslip->generatedBy->name }}</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <flux:icon name="clock" class="w-4 h-4 mr-2 text-gray-400" />
                                    <span>{{ $payslip->generated_at->format('M d, Y') }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex flex-col items-end gap-3 lg:min-w-fit">
                            <div class="text-lg font-bold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-3 py-1 rounded-md">
                                RM{{ number_format($payslip->total_amount, 2) }}
                            </div>
                            
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" class="hover:bg-gray-100 dark:hover:bg-gray-800" />
                                
                                <flux:menu class="min-w-48">
                                    <flux:menu.item icon="eye" href="{{ route('admin.payslips.show', $payslip) }}" wire:navigate>
                                        View Details
                                    </flux:menu.item>
                                    
                                    @if($payslip->canBeEdited())
                                        <flux:menu.item icon="pencil" href="{{ route('admin.payslips.edit', $payslip) }}" wire:navigate>
                                            Edit Payslip
                                        </flux:menu.item>
                                    @endif
                                    
                                    <flux:menu.item icon="user" href="{{ route('teachers.show', $payslip->teacher) }}" wire:navigate>
                                        View Teacher
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
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
            @if($monthFilter || $teacherFilter !== 'all' || $statusFilter !== 'all' || $search)
                <flux:heading size="lg" class="mb-4">No Payslips Found</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    No payslips match your current filter criteria. Try adjusting your filters.
                </flux:text>
                <flux:button variant="ghost" wire:click="$set('monthFilter', ''); $set('teacherFilter', 'all'); $set('statusFilter', 'all'); $set('search', '')">
                    Clear All Filters
                </flux:button>
            @else
                <flux:heading size="lg" class="mb-4">No Payslips Generated</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    No payslips have been generated yet. Start by generating payslips for your teachers.
                </flux:text>
                <flux:button variant="primary" wire:click="openGenerateModal">
                    Generate First Payslip
                </flux:button>
            @endif
        </flux:card>
    @endif

    <!-- Generate Payslips Modal -->
    @if($showGenerateModal)
        <flux:modal wire:model.boolean="showGenerateModal" class="md:w-2/3 lg:w-1/2">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Generate Payslips</flux:heading>
                
                <div class="space-y-4">
                    <div>
                        <flux:field>
                            <flux:label for="generateMonth">Month</flux:label>
                            <flux:select wire:model="generateMonth" id="generateMonth">
                                @foreach($availableMonths->take(12) as $month)
                                    <option value="{{ $month['value'] }}">{{ $month['label'] }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <div class="flex items-center justify-between mb-2">
                                <flux:label for="selectedTeachers">Select Teachers</flux:label>
                                <div class="flex items-center gap-2">
                                    <flux:button size="xs" variant="ghost" wire:click="selectAllTeachers" class="text-blue-600 hover:text-blue-700">
                                        Select All
                                    </flux:button>
                                    <flux:button size="xs" variant="ghost" wire:click="deselectAllTeachers" class="text-red-600 hover:text-red-700">
                                        Clear All
                                    </flux:button>
                                </div>
                            </div>
                            <div class="mt-2 max-h-60 overflow-y-auto border rounded-md p-4">
                                @foreach($teachers as $teacher)
                                    <label class="flex items-center mb-2 hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded">
                                        <input type="checkbox" wire:model="selectedTeachers" value="{{ $teacher->id }}" 
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <div class="ml-3 flex-1">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $teacher->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $teacher->email }} • ID: {{ $teacher->id }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </flux:field>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <flux:button variant="ghost" wire:click="closeGenerateModal">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="generatePreview">Preview Generation</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Preview Modal -->
    @if($showPreviewModal)
        <flux:modal wire:model.boolean="showPreviewModal" class="md:w-3/4 lg:w-2/3">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Generation Preview - {{ Carbon::createFromFormat('Y-m', $generateMonth)->format('F Y') }}</flux:heading>
                
                @if(count($generationPreview) > 0)
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        @foreach($generationPreview as $preview)
                            <div class="border rounded-lg p-4 {{ $preview['can_generate'] ? 'border-green-200 bg-green-50 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:bg-red-900/20' }}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            @if($preview['can_generate'])
                                                <span class="text-green-600 dark:text-green-400">✅</span>
                                            @else
                                                <span class="text-red-600 dark:text-red-400">❌</span>
                                            @endif
                                            <flux:text class="font-medium">{{ $preview['teacher_name'] }}</flux:text>
                                        </div>
                                        
                                        @if($preview['can_generate'])
                                            <flux:text size="sm" class="text-green-700 dark:text-green-300">
                                                {{ $preview['sessions_count'] }} sessions • RM{{ number_format($preview['total_amount'], 2) }}
                                            </flux:text>
                                        @else
                                            <flux:text size="sm" class="text-red-700 dark:text-red-300">
                                                {{ $this->getReadableError($preview['reason']) }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    @php
                        $validTeachers = collect($generationPreview)->where('can_generate', true);
                        $invalidTeachers = collect($generationPreview)->where('can_generate', false);
                    @endphp
                    
                    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-blue-600 dark:text-blue-400">ℹ️</span>
                            <flux:text class="font-medium">Summary</flux:text>
                        </div>
                        <flux:text size="sm" class="text-blue-700 dark:text-blue-300">
                            {{ $validTeachers->count() }} teachers ready for generation • 
                            {{ $invalidTeachers->count() }} teachers have issues • 
                            Total: RM{{ number_format($validTeachers->sum('total_amount'), 2) }}
                        </flux:text>
                    </div>
                @endif
                
                <div class="flex justify-end gap-3 mt-6">
                    <flux:button variant="ghost" wire:click="closePreviewModal">Back</flux:button>
                    @if(collect($generationPreview)->where('can_generate', true)->count() > 0)
                        <flux:button variant="primary" wire:click="confirmGeneration">
                            Generate {{ collect($generationPreview)->where('can_generate', true)->count() }} Payslips
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Results Modal -->
    @if($showResultsModal || $isGenerating)
        <flux:modal wire:model.boolean="showResultsModal" class="md:w-3/4 lg:w-2/3">
            <div class="p-6">
                @if($isGenerating)
                    <div class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                        <flux:heading size="lg" class="mb-2">Generating Payslips...</flux:heading>
                        <flux:text class="text-gray-600 dark:text-gray-400">Please wait while we process the payslips.</flux:text>
                    </div>
                @else
                    <flux:heading size="lg" class="mb-4">Generation Results</flux:heading>
                    
                    @if(count($generationResults['successful']) > 0)
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-green-600 dark:text-green-400">✅</span>
                                <flux:text class="font-medium text-green-700 dark:text-green-300">
                                    Successfully Generated ({{ count($generationResults['successful']) }})
                                </flux:text>
                            </div>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($generationResults['successful'] as $success)
                                    <div class="border border-green-200 bg-green-50 dark:bg-green-900/20 rounded p-3">
                                        <div class="flex justify-between items-center">
                                            <flux:text class="font-medium">{{ $success['teacher_name'] }}</flux:text>
                                            <div class="text-right">
                                                <flux:text size="sm" class="text-green-700 dark:text-green-300">
                                                    {{ $success['sessions_count'] }} sessions
                                                </flux:text>
                                                <flux:text class="font-medium text-green-800 dark:text-green-200">
                                                    RM{{ number_format($success['amount'], 2) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if(count($generationResults['failed']) > 0)
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-red-600 dark:text-red-400">❌</span>
                                <flux:text class="font-medium text-red-700 dark:text-red-300">
                                    Failed to Generate ({{ count($generationResults['failed']) }})
                                </flux:text>
                            </div>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($generationResults['failed'] as $failure)
                                    <div class="border border-red-200 bg-red-50 dark:bg-red-900/20 rounded p-3">
                                        <flux:text class="font-medium">{{ $failure['teacher_name'] }}</flux:text>
                                        <flux:text size="sm" class="text-red-700 dark:text-red-300 mt-1">
                                            {{ $failure['error'] }}
                                        </flux:text>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if(count($generationResults['successful']) > 0)
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-blue-600 dark:text-blue-400">📊</span>
                                <flux:text class="font-medium">Total Summary</flux:text>
                            </div>
                            <flux:text size="sm" class="text-blue-700 dark:text-blue-300">
                                {{ $generationResults['total_sessions'] }} sessions • 
                                RM{{ number_format($generationResults['total_amount'], 2) }} total amount • 
                                {{ count($generationResults['successful']) }} payslips created
                            </flux:text>
                        </div>
                    @endif
                @endif
                
                @if(!$isGenerating)
                    <div class="flex justify-end gap-3 mt-6">
                        <flux:button variant="primary" wire:click="closeResultsModal">Done</flux:button>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endif
</div>