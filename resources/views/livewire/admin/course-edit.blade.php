<?php

use App\Models\Course;
use App\Models\CourseFeeSettings;
use App\Models\CourseClassSettings;
use App\Models\Teacher;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public Course $course;
    
    // Course basic info
    public $name = '';
    public $description = '';
    public $status = '';
    public $teacher_id = '';
    
    // Fee settings
    public $fee_amount = '';
    public $billing_cycle = 'monthly';
    public $is_recurring = true;
    public $trial_period_days = 0;
    public $setup_fee = 0;
    
    // Class settings
    public $teaching_mode = 'online';
    public $billing_type = 'per_month';
    public $sessions_per_month = '';
    public $session_duration_hours = 0;
    public $session_duration_minutes = 60;
    public $price_per_session = '';
    public $price_per_month = '';
    public $price_per_minute = '';
    public $class_description = '';
    public $class_instructions = '';

    public function mount(): void
    {
        $this->course->load(['feeSettings', 'classSettings']);
        
        // Load course data
        $this->name = $this->course->name;
        $this->description = $this->course->description ?? '';
        $this->status = $this->course->status;
        $this->teacher_id = $this->course->teacher_id ?? '';
        
        // Load fee settings
        if ($this->course->feeSettings) {
            $this->fee_amount = $this->course->feeSettings->fee_amount;
            $this->billing_cycle = $this->course->feeSettings->billing_cycle;
            $this->is_recurring = $this->course->feeSettings->is_recurring;
            $this->trial_period_days = $this->course->feeSettings->trial_period_days ?? 0;
            $this->setup_fee = $this->course->feeSettings->setup_fee ?? 0;
        }
        
        // Load class settings
        if ($this->course->classSettings) {
            $this->teaching_mode = $this->course->classSettings->teaching_mode;
            $this->billing_type = $this->course->classSettings->billing_type;
            $this->sessions_per_month = $this->course->classSettings->sessions_per_month ?? '';
            $this->session_duration_hours = $this->course->classSettings->session_duration_hours;
            $this->session_duration_minutes = $this->course->classSettings->session_duration_minutes;
            $this->price_per_session = $this->course->classSettings->price_per_session ?? '';
            $this->price_per_month = $this->course->classSettings->price_per_month ?? '';
            $this->price_per_minute = $this->course->classSettings->price_per_minute ?? '';
            $this->class_description = $this->course->classSettings->class_description ?? '';
            $this->class_instructions = $this->course->classSettings->class_instructions ?? '';
        }
    }

    public function with(): array
    {
        return [
            'teachers' => Teacher::with('user')->where('status', 'active')->get(),
        ];
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,archived',
            'teacher_id' => 'nullable|exists:teachers,id',
            'fee_amount' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'trial_period_days' => 'nullable|integer|min:0|max:365',
            'setup_fee' => 'nullable|numeric|min:0',
            'teaching_mode' => 'required|in:online,offline,hybrid',
            'billing_type' => 'required|in:per_month,per_session,per_minute',
            'session_duration_hours' => 'required|integer|min:0|max:23',
            'session_duration_minutes' => 'required|integer|min:0|max:59',
            'sessions_per_month' => $this->billing_type === 'per_session' ? 'required|integer|min:1' : 'nullable',
            'price_per_session' => $this->billing_type === 'per_session' ? 'required|numeric|min:0' : 'nullable',
            'price_per_month' => $this->billing_type === 'per_month' ? 'required|numeric|min:0' : 'nullable',
            'price_per_minute' => $this->billing_type === 'per_minute' ? 'required|numeric|min:0' : 'nullable',
        ]);

        // Update course
        $this->course->update([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'teacher_id' => $this->teacher_id ?: null,
        ]);

        // Update or create fee settings
        $this->course->feeSettings()->updateOrCreate(
            ['course_id' => $this->course->id],
            [
                'fee_amount' => $this->fee_amount,
                'billing_cycle' => $this->billing_cycle,
                'is_recurring' => $this->is_recurring,
                'trial_period_days' => $this->trial_period_days ?: 0,
                'setup_fee' => $this->setup_fee ?: 0,
            ]
        );

        // Update or create class settings
        $this->course->classSettings()->updateOrCreate(
            ['course_id' => $this->course->id],
            [
                'teaching_mode' => $this->teaching_mode,
                'billing_type' => $this->billing_type,
                'sessions_per_month' => $this->sessions_per_month ?: null,
                'session_duration_hours' => $this->session_duration_hours,
                'session_duration_minutes' => $this->session_duration_minutes,
                'price_per_session' => $this->price_per_session ?: null,
                'price_per_month' => $this->price_per_month ?: null,
                'price_per_minute' => $this->price_per_minute ?: null,
                'class_description' => $this->class_description,
                'class_instructions' => $this->class_instructions,
            ]
        );

        session()->flash('success', 'Course updated successfully!');
        
        return $this->redirect(route('courses.index'));
    }

    public function syncToStripe()
    {
        try {
            $stripeService = app(StripeService::class);
            
            // Log the start of sync operation
            \Illuminate\Support\Facades\Log::info('Starting Stripe sync', [
                'course_id' => $this->course->id,
                'course_name' => $this->course->name,
                'has_stripe_product_id' => !empty($this->course->stripe_product_id),
                'stripe_product_id' => $this->course->stripe_product_id,
                'current_sync_status' => $this->course->stripe_sync_status,
            ]);
            
            // Mark sync as pending
            $this->course->markStripeSyncAsPending();
            
            // Create or update Stripe product
            if ($this->course->stripe_product_id) {
                \Illuminate\Support\Facades\Log::info('Updating existing Stripe product', [
                    'course_id' => $this->course->id,
                    'stripe_product_id' => $this->course->stripe_product_id,
                ]);
                $stripeService->updateProduct($this->course);
                
                // Check if we need to update or create a price for fee changes
                if ($this->course->feeSettings) {
                    if ($this->course->feeSettings->stripe_price_id) {
                        // Price exists, check if we need to update it (prices are immutable in Stripe)
                        \Illuminate\Support\Facades\Log::info('Checking if price needs to be updated', [
                            'course_id' => $this->course->id,
                            'fee_settings_id' => $this->course->feeSettings->id,
                            'stripe_price_id' => $this->course->feeSettings->stripe_price_id,
                        ]);
                        $this->updateStripePrice($stripeService);
                    } else {
                        // Create new price
                        \Illuminate\Support\Facades\Log::info('Creating new Stripe price for existing product', [
                            'course_id' => $this->course->id,
                            'fee_settings_id' => $this->course->feeSettings->id,
                        ]);
                        $priceId = $stripeService->createPrice($this->course->feeSettings);
                        \Illuminate\Support\Facades\Log::info('New price created', ['stripe_price_id' => $priceId]);
                    }
                }
                
                session()->flash('success', 'Course synced to Stripe successfully!');
            } else {
                \Illuminate\Support\Facades\Log::info('Creating new Stripe product', [
                    'course_id' => $this->course->id,
                ]);
                $productId = $stripeService->createProduct($this->course);
                \Illuminate\Support\Facades\Log::info('New product created', ['stripe_product_id' => $productId]);
                
                // Create Stripe price if fee settings exist
                if ($this->course->feeSettings) {
                    \Illuminate\Support\Facades\Log::info('Creating price for new product', [
                        'course_id' => $this->course->id,
                        'fee_settings_id' => $this->course->feeSettings->id,
                    ]);
                    $priceId = $stripeService->createPrice($this->course->feeSettings);
                    \Illuminate\Support\Facades\Log::info('Price created for new product', ['stripe_price_id' => $priceId]);
                    session()->flash('success', 'Course and pricing synced to Stripe successfully!');
                } else {
                    session()->flash('success', 'Course created in Stripe successfully!');
                }
            }
            
            // Mark sync as completed after successful operations
            if ($this->course->stripe_product_id) {
                \Illuminate\Support\Facades\Log::info('Marking sync as completed', [
                    'course_id' => $this->course->id,
                    'stripe_product_id' => $this->course->stripe_product_id,
                ]);
                $this->course->update(['stripe_sync_status' => 'synced']);
            }
            
            // Refresh the course model to get updated sync status
            $this->course->refresh();
            
            \Illuminate\Support\Facades\Log::info('Stripe sync completed successfully', [
                'course_id' => $this->course->id,
                'final_sync_status' => $this->course->stripe_sync_status,
                'last_synced_at' => $this->course->stripe_last_synced_at,
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe sync failed', [
                'course_id' => $this->course->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->course->markStripeSyncAsFailed();
            session()->flash('error', 'Failed to sync to Stripe: ' . $e->getMessage());
        }
    }

    public function createStripePrice()
    {
        try {
            $stripeService = app(StripeService::class);
            
            if (!$this->course->stripe_product_id) {
                session()->flash('error', 'Course must be synced to Stripe before creating price.');
                return;
            }
            
            if (!$this->course->feeSettings) {
                session()->flash('error', 'Course must have fee settings before creating Stripe price.');
                return;
            }
            
            $priceId = $stripeService->createPrice($this->course->feeSettings);
            session()->flash('success', 'Stripe price created successfully!');
            
            // Refresh the course model
            $this->course->refresh();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create Stripe price: ' . $e->getMessage());
        }
    }

    private function updateStripePrice($stripeService)
    {
        try {
            // Check if current price matches what's needed
            $needsNewPrice = $this->checkIfPriceNeedsUpdate($stripeService);
            
            if ($needsNewPrice) {
                \Illuminate\Support\Facades\Log::info('Creating new price due to fee changes', [
                    'course_id' => $this->course->id,
                    'current_stripe_price_id' => $this->course->feeSettings->stripe_price_id,
                ]);
                
                // Create new price (Stripe prices are immutable)
                $newPriceId = $stripeService->createPrice($this->course->feeSettings);
                
                \Illuminate\Support\Facades\Log::info('New price created for updated fees', [
                    'course_id' => $this->course->id,
                    'old_stripe_price_id' => $this->course->feeSettings->stripe_price_id,
                    'new_stripe_price_id' => $newPriceId,
                ]);
                
                session()->flash('success', 'Course and updated pricing synced to Stripe successfully!');
            } else {
                \Illuminate\Support\Facades\Log::info('Price unchanged, no update needed', [
                    'course_id' => $this->course->id,
                    'stripe_price_id' => $this->course->feeSettings->stripe_price_id,
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update Stripe price', [
                'course_id' => $this->course->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to be caught by parent method
        }
    }

    private function checkIfPriceNeedsUpdate($stripeService): bool
    {
        try {
            // Use the StripeService to get the configured client
            $settingsService = app(\App\Services\SettingsService::class);
            $secretKey = $settingsService->get('stripe_secret_key');
            $stripe = new \Stripe\StripeClient($secretKey);
            $currentPrice = $stripe->prices->retrieve($this->course->feeSettings->stripe_price_id);
            
            $expectedAmount = (int) round($this->course->feeSettings->fee_amount * 100);
            $expectedInterval = $this->course->feeSettings->getStripeInterval();
            $expectedIntervalCount = $this->course->feeSettings->getStripeIntervalCount();
            
            \Illuminate\Support\Facades\Log::info('Comparing price details', [
                'course_id' => $this->course->id,
                'current_amount' => $currentPrice->unit_amount,
                'expected_amount' => $expectedAmount,
                'current_interval' => $currentPrice->recurring->interval,
                'expected_interval' => $expectedInterval,
                'current_interval_count' => $currentPrice->recurring->interval_count,
                'expected_interval_count' => $expectedIntervalCount,
            ]);
            
            return $currentPrice->unit_amount !== $expectedAmount ||
                   $currentPrice->recurring->interval !== $expectedInterval ||
                   $currentPrice->recurring->interval_count !== $expectedIntervalCount;
                   
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error checking if price needs update', [
                'course_id' => $this->course->id,
                'error' => $e->getMessage(),
            ]);
            // If we can't check, assume it needs update
            return true;
        }
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Course: {{ $course->name }}</flux:heading>
            <flux:text class="mt-2">Update course information and settings</flux:text>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Course Basic Info -->
        <flux:card>
            <flux:heading size="lg">Course Information</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:input wire:model="name" label="Course Name" placeholder="Enter course name" />

                <flux:textarea wire:model="description" label="Description" placeholder="Course description (optional)" rows="4" />

                <flux:field>
                    <flux:label>Assign Teacher (Optional)</flux:label>
                    <flux:select wire:model="teacher_id" placeholder="Select a teacher">
                        @foreach($teachers as $teacher)
                            <flux:select.option value="{{ $teacher->id }}">{{ $teacher->user->name }} ({{ $teacher->teacher_id }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="teacher_id" />
                </flux:field>

                <flux:select wire:model="status" label="Status">
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="archived">Archived</flux:select.option>
                </flux:select>
            </div>
        </flux:card>

        <!-- Fee Settings -->
        <flux:card>
            <flux:heading size="lg">Fee Settings</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:input type="number" step="0.01" wire:model="fee_amount" label="Fee Amount (MYR)" placeholder="0.00" />

                <flux:select wire:model="billing_cycle" label="Billing Cycle">
                    <flux:select.option value="monthly">Monthly</flux:select.option>
                    <flux:select.option value="quarterly">Quarterly</flux:select.option>
                    <flux:select.option value="yearly">Yearly</flux:select.option>
                </flux:select>

                <flux:field variant="inline">
                    <flux:checkbox wire:model="is_recurring" />
                    <flux:label>Enable recurring billing</flux:label>
                </flux:field>

                <flux:input type="number" wire:model="trial_period_days" label="Trial Period (Days)" placeholder="0" />

                <flux:input type="number" step="0.01" wire:model="setup_fee" label="Setup Fee (MYR)" placeholder="0.00" />
            </div>
        </flux:card>

        <!-- Stripe Settings -->
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Stripe Integration</flux:heading>
                @if($course->isSyncedToStripe())
                    <flux:badge variant="success">Synced</flux:badge>
                @elseif($course->isStripeSyncPending())
                    <flux:badge variant="warning">Syncing...</flux:badge>
                @elseif($course->hasStripeSyncFailed())
                    <flux:badge variant="danger">Sync Failed</flux:badge>
                @else
                    <flux:badge variant="gray">Not Synced</flux:badge>
                @endif
            </div>
            
            <div class="mt-6 space-y-4">
                @if($course->stripe_product_id)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Stripe Product ID</flux:label>
                            <flux:input value="{{ $course->stripe_product_id }}" readonly />
                        </flux:field>
                        
                        @if($course->feeSettings?->stripe_price_id)
                            <flux:field>
                                <flux:label>Stripe Price ID</flux:label>
                                <flux:input value="{{ $course->feeSettings->stripe_price_id }}" readonly />
                            </flux:field>
                        @endif
                    </div>

                    @if($course->stripe_last_synced_at)
                        <flux:text size="sm" class="text-gray-600">
                            Last synced: {{ $course->stripe_last_synced_at->format('M j, Y g:i A') }}
                        </flux:text>
                    @endif

                    <div class="flex gap-3">
                        <flux:button wire:click="syncToStripe" variant="outline" size="sm">
                            Update in Stripe
                        </flux:button>
                        
                        @if(!$course->feeSettings?->stripe_price_id)
                            <flux:button wire:click="createStripePrice" variant="outline" size="sm">
                                Create Stripe Price
                            </flux:button>
                        @endif
                    </div>
                @else
                    <div class="text-center py-6">
                        <flux:text class="text-gray-600 mb-4">
                            This course is not synced with Stripe. Sync to enable subscription billing.
                        </flux:text>
                        <flux:button wire:click="syncToStripe" variant="primary">
                            Sync to Stripe
                        </flux:button>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Class Settings -->
        <flux:card>
            <flux:heading size="lg">Class Settings</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:select wire:model="teaching_mode" label="Teaching Mode">
                    <flux:select.option value="online">Online</flux:select.option>
                    <flux:select.option value="offline">Offline</flux:select.option>
                    <flux:select.option value="hybrid">Hybrid</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="billing_type" label="Billing Type">
                    <flux:select.option value="per_month">Per Month</flux:select.option>
                    <flux:select.option value="per_session">Per Session</flux:select.option>
                    <flux:select.option value="per_minute">Per Minute</flux:select.option>
                </flux:select>

                @if($billing_type === 'per_session')
                    <flux:input type="number" wire:model="sessions_per_month" label="Sessions Per Month" placeholder="4" />
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="number" wire:model="session_duration_hours" label="Session Duration (Hours)" placeholder="1" />
                    <flux:input type="number" wire:model="session_duration_minutes" label="Session Duration (Minutes)" placeholder="30" />
                </div>

                @if($billing_type === 'per_session')
                    <flux:input type="number" step="0.01" wire:model="price_per_session" label="Price Per Session (MYR)" placeholder="0.00" />
                @elseif($billing_type === 'per_month')
                    <flux:input type="number" step="0.01" wire:model="price_per_month" label="Price Per Month (MYR)" placeholder="0.00" />
                @elseif($billing_type === 'per_minute')
                    <flux:input type="number" step="0.01" wire:model="price_per_minute" label="Price Per Minute (MYR)" placeholder="0.00" />
                @endif

                <flux:textarea wire:model="class_description" label="Class Description" placeholder="Describe what this class covers..." rows="3" />

                <flux:textarea wire:model="class_instructions" label="Class Instructions" placeholder="Special instructions for students..." rows="3" />
            </div>
        </flux:card>

        <div class="flex justify-between">
            <flux:button variant="ghost" href="{{ route('courses.index') }}">Cancel</flux:button>
            <flux:button wire:click="update" variant="primary">Update Course</flux:button>
        </div>
    </div>
</div>