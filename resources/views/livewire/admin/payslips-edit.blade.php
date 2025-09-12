<?php

use Livewire\Volt\Component;
use App\Models\Payslip;
use App\Models\ClassSession;
use App\Services\PayslipGenerationService;

new class extends Component {
    public Payslip $payslip;
    public string $notes = '';
    public array $sessionIds = [];
    public $availableSessions;
    
    public function mount(Payslip $payslip)
    {
        if (! $payslip->canBeEdited()) {
            session()->flash('error', 'Only draft payslips can be edited.');
            return redirect()->route('admin.payslips.show', $payslip);
        }
        
        $this->payslip = $payslip->load(['teacher', 'sessions.class.course', 'sessions.attendances']);
        $this->notes = $payslip->notes ?? '';
        $this->sessionIds = $payslip->sessions->pluck('id')->toArray();
        
        // Get all available sessions for this teacher and month
        // Include both eligible sessions and sessions already in this payslip
        $startOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $payslip->month)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $payslip->month)->endOfMonth();
        
        $this->availableSessions = \App\Models\ClassSession::with(['class.course', 'class.teacher.user', 'attendances'])
            ->whereHas('class', function ($query) use ($payslip) {
                $query->where('teacher_id', $payslip->teacher->teacher->id);
            })
            ->whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->where('status', 'completed')
            ->whereNotNull('verified_at')
            ->whereNotNull('allowance_amount')
            ->where(function($query) use ($payslip) {
                $query->where('payout_status', 'unpaid')
                      ->orWhere(function($subQuery) use ($payslip) {
                          $subQuery->where('payout_status', 'included_in_payslip')
                                   ->whereHas('payslips', function($payslipQuery) use ($payslip) {
                                       $payslipQuery->where('payslip_id', $payslip->id);
                                   });
                      });
            })
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
    }
    
    public function save()
    {
        $this->validate([
            'notes' => 'nullable|string|max:1000',
            'sessionIds' => 'array',
            'sessionIds.*' => 'exists:class_sessions,id',
        ]);
        
        try {
            // Update notes
            $this->payslip->update([
                'notes' => $this->notes,
            ]);
            
            // Sync sessions
            $this->payslip->syncSessions($this->sessionIds);
            
            session()->flash('success', 'Payslip updated successfully.');
            return redirect()->route('admin.payslips.show', $this->payslip);
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update payslip: ' . $e->getMessage());
        }
    }
    
    public function cancel()
    {
        return redirect()->route('admin.payslips.show', $this->payslip);
    }
    
    public function toggleSession($sessionId)
    {
        if (in_array($sessionId, $this->sessionIds)) {
            $this->sessionIds = array_values(array_diff($this->sessionIds, [$sessionId]));
        } else {
            $this->sessionIds[] = $sessionId;
        }
        
        // Update session count and amount preview
        $this->dispatch('sessions-updated');
    }
    
    public function getTotalSessionsProperty()
    {
        return count($this->sessionIds);
    }
    
    public function getTotalAmountProperty()
    {
        if (empty($this->sessionIds)) {
            return 0;
        }
        
        return $this->availableSessions
            ->whereIn('id', $this->sessionIds)
            ->sum(fn($session) => $session->getTeacherAllowanceAmount());
    }
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Payslip - {{ $payslip->teacher->name }}</flux:heading>
            <flux:text class="mt-2">{{ $payslip->formatted_month }} • Modify sessions and notes</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" wire:click="cancel">
                <div class="flex items-center justify-center">
                    <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                    Cancel
                </div>
            </flux:button>
            
            <flux:button variant="primary" wire:click="save">
                <div class="flex items-center justify-center">
                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                    Save Changes
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Payslip Preview -->
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Payslip Preview</flux:heading>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <flux:text variant="muted" size="sm">Selected Sessions</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $this->totalSessions }}</flux:heading>
            </div>
            
            <div>
                <flux:text variant="muted" size="sm">Total Amount</flux:text>
                <flux:heading size="lg" class="mt-1">RM{{ number_format($this->totalAmount, 2) }}</flux:heading>
            </div>
            
            <div>
                <flux:text variant="muted" size="sm">Status</flux:text>
                <flux:badge variant="warning" class="mt-1">Draft</flux:badge>
            </div>
        </div>
    </flux:card>

    <!-- Notes Section -->
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Payslip Notes</flux:heading>
        
        <flux:field>
            <flux:label>Notes (Optional)</flux:label>
            <flux:textarea 
                wire:model="notes" 
                placeholder="Add any notes or comments about this payslip..."
                rows="4"
            />
            <flux:error name="notes" />
        </flux:field>
    </flux:card>

    <!-- Sessions Selection -->
    <flux:card>
        <flux:heading size="lg" class="mb-4">Session Selection</flux:heading>
        <flux:text variant="muted" class="mb-4">Select which sessions to include in this payslip for {{ $payslip->teacher->name }} ({{ $payslip->formatted_month }})</flux:text>
        
        @if($availableSessions->count() > 0)
            <div class="space-y-3">
                @foreach($availableSessions as $session)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-3">
                                <flux:checkbox 
                                    wire:click="toggleSession({{ $session->id }})"
                                    :checked="in_array($session->id, $sessionIds)"
                                />
                                
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <flux:text class="font-medium">{{ $session->class->course->name }}</flux:text>
                                            <flux:text variant="muted" size="sm" class="block">{{ $session->class->title }}</flux:text>
                                        </div>
                                        
                                        <div class="text-right">
                                            <flux:text class="font-medium">RM{{ number_format($session->getTeacherAllowanceAmount(), 2) }}</flux:text>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 flex items-center space-x-6 text-sm text-gray-600 dark:text-gray-400">
                                        <div class="flex items-center">
                                            <flux:icon name="calendar" class="w-4 h-4 mr-1" />
                                            {{ $session->session_date->format('M d, Y') }}
                                        </div>
                                        <div class="flex items-center">
                                            <flux:icon name="clock" class="w-4 h-4 mr-1" />
                                            {{ $session->session_time->format('g:i A') }}
                                        </div>
                                        <div class="flex items-center">
                                            <flux:icon name="users" class="w-4 h-4 mr-1" />
                                            {{ $session->attendances->count() }} students
                                        </div>
                                        <div class="flex items-center">
                                            <flux:icon name="check" class="w-4 h-4 mr-1" />
                                            {{ $session->attendances->where('status', 'present')->count() }} present
                                        </div>
                                    </div>
                                    
                                    @if($session->verified_at)
                                        <div class="mt-2 flex items-center text-sm text-green-600 dark:text-green-400">
                                            <flux:icon name="check" class="w-4 h-4 mr-1" />
                                            Verified on {{ $session->verified_at->format('M d, Y g:i A') }}
                                        </div>
                                    @else
                                        <div class="mt-2 flex items-center text-sm text-yellow-600 dark:text-yellow-400">
                                            <span class="w-4 h-4 mr-1">⚠️</span>
                                            Not yet verified
                                        </div>
                                    @endif
                                    
                                    @if($session->payout_status !== 'unpaid')
                                        <div class="mt-2">
                                            <flux:badge variant="primary" size="sm">
                                                {{ ucfirst(str_replace('_', ' ', $session->payout_status)) }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <div class="flex items-center">
                    <span class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2">ℹ️</span>
                    <flux:text size="sm" variant="muted">
                        Only completed and verified sessions are eligible for payslips. Unchecking sessions will reset their payout status to "unpaid".
                    </flux:text>
                </div>
            </div>
        @else
            <div class="text-center py-8">
                <flux:icon name="calendar" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                <flux:text variant="muted">No eligible sessions found for {{ $payslip->teacher->name }} in {{ $payslip->formatted_month }}.</flux:text>
            </div>
        @endif
    </flux:card>
</div>