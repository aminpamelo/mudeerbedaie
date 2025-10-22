<?php

use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component {
    // Current step
    public $currentStep = 1;

    // Step 1: Information
    public $name = '';
    public $type = 'standard';

    // Step 2: Contacts/Audiences
    public $selectedAudiences = [];
    public $audienceSearch = '';
    public $selectedStudents = [];
    public $studentSearch = '';
    public $showStudentList = false;

    // Step 3: Content
    public $from_name = '';
    public $from_email = '';
    public $reply_to_email = '';
    public $subject = '';
    public $preview_text = '';
    public $content = '';

    // Step 4: Schedule
    public $sendOption = 'now';
    public $scheduled_at = '';

    public function mount(): void
    {
        // Load default email settings
        $this->from_email = Setting::where('key', 'email.from_address')->value('value') ?? '';
        $this->from_name = Setting::where('key', 'email.from_name')->value('value') ?? '';
        $this->reply_to_email = $this->from_email;
    }

    public function with(): array
    {
        $audiencesQuery = Audience::query()
            ->where('status', 'active')
            ->withCount('students');

        if ($this->audienceSearch) {
            $audiencesQuery->where('name', 'like', '%' . $this->audienceSearch . '%');
        }

        $audiences = $audiencesQuery->orderBy('name')->get();

        // Get students from selected audiences
        $studentsFromAudiences = collect();
        if (!empty($this->selectedAudiences)) {
            $studentIds = collect();
            foreach (Audience::whereIn('id', $this->selectedAudiences)->get() as $audience) {
                $audienceStudentIds = $audience->students()->pluck('students.id');
                $studentIds = $studentIds->merge($audienceStudentIds);
            }

            $studentsQuery = \App\Models\Student::query()
                ->whereIn('id', $studentIds->unique())
                ->with(['user', 'paidOrders']);

            if ($this->studentSearch) {
                $studentsQuery->whereHas('user', function($q) {
                    $q->where('name', 'like', '%' . $this->studentSearch . '%')
                      ->orWhere('email', 'like', '%' . $this->studentSearch . '%');
                })
                ->orWhere('student_id', 'like', '%' . $this->studentSearch . '%');
            }

            $studentsFromAudiences = $studentsQuery->orderBy('created_at', 'desc')->get();

            // Auto-select all students from audiences if not manually deselected
            if (empty($this->selectedStudents) && !$this->showStudentList) {
                $this->selectedStudents = $studentsFromAudiences->pluck('id')->toArray();
            }
        }

        // Calculate total recipients based on selected students
        $totalRecipients = count($this->selectedStudents);

        return [
            'audiences' => $audiences,
            'studentsFromAudiences' => $studentsFromAudiences,
            'totalRecipients' => $totalRecipients,
        ];
    }

    public function nextStep(): void
    {
        // Validate current step before proceeding
        if ($this->currentStep === 1) {
            $this->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:standard,ab_test',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'selectedAudiences' => 'required|array|min:1',
            ], [
                'selectedAudiences.required' => 'Please select at least one audience.',
                'selectedAudiences.min' => 'Please select at least one audience.',
            ]);
        } elseif ($this->currentStep === 3) {
            $this->validate([
                'from_name' => 'required|string|max:255',
                'from_email' => 'required|email',
                'reply_to_email' => 'nullable|email',
                'subject' => 'required|string|max:255',
                'preview_text' => 'nullable|string|max:255',
                'content' => 'required|string',
            ]);
        }

        $this->currentStep++;
    }

    public function previousStep(): void
    {
        $this->currentStep--;
    }

    public function goToStep($step): void
    {
        $this->currentStep = $step;
    }

    public function toggleStudentList(): void
    {
        $this->showStudentList = !$this->showStudentList;
    }

    public function selectAllStudents(): void
    {
        $studentIds = collect();
        foreach (Audience::whereIn('id', $this->selectedAudiences)->get() as $audience) {
            $audienceStudentIds = $audience->students()->pluck('students.id');
            $studentIds = $studentIds->merge($audienceStudentIds);
        }
        $this->selectedStudents = $studentIds->unique()->toArray();
    }

    public function deselectAllStudents(): void
    {
        $this->selectedStudents = [];
    }

    public function saveDraft(): void
    {
        $broadcast = Broadcast::create([
            'name' => $this->name ?: 'Untitled Broadcast',
            'type' => $this->type,
            'status' => 'draft',
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to_email' => $this->reply_to_email,
            'subject' => $this->subject,
            'preview_text' => $this->preview_text,
            'content' => $this->content,
        ]);

        if (!empty($this->selectedAudiences)) {
            $broadcast->audiences()->attach($this->selectedAudiences);
        }

        session()->flash('message', 'Broadcast saved as draft.');
        $this->redirect(route('crm.broadcasts.index'));
    }

    public function send(): void
    {
        // Final validation
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:standard,ab_test',
            'selectedAudiences' => 'required|array|min:1',
            'selectedStudents' => 'required|array|min:1',
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ], [
            'selectedStudents.required' => 'Please select at least one student to send the broadcast.',
            'selectedStudents.min' => 'Please select at least one student to send the broadcast.',
        ]);

        // Use selected students count
        $totalRecipients = count($this->selectedStudents);

        $broadcast = Broadcast::create([
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->sendOption === 'now' ? 'sending' : 'scheduled',
            'from_name' => $this->from_name,
            'from_email' => $this->from_email,
            'reply_to_email' => $this->reply_to_email,
            'subject' => $this->subject,
            'preview_text' => $this->preview_text,
            'content' => $this->content,
            'scheduled_at' => $this->sendOption === 'schedule' ? $this->scheduled_at : null,
            'total_recipients' => $totalRecipients,
            'selected_students' => $this->selectedStudents,
        ]);

        $broadcast->audiences()->attach($this->selectedAudiences);

        if ($this->sendOption === 'now') {
            // Dispatch job to send emails
            \App\Jobs\SendBroadcastEmail::dispatch($broadcast);
            session()->flash('message', 'Broadcast is being sent!');
        } else {
            session()->flash('message', 'Broadcast scheduled successfully!');
        }

        $this->redirect(route('crm.broadcasts.index'));
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ $currentStep === 4 ? 'Review & Send' : 'Create Broadcast' }}</flux:heading>
        <flux:text class="mt-2">{{ $name ?: 'New Email Broadcast' }}</flux:text>
    </div>

    <!-- Step Indicator -->
    <div class="mb-8">
        <div class="flex items-center justify-center">
            @foreach([1 => 'Information', 2 => 'Contacts', 3 => 'Content', 4 => 'Review'] as $step => $label)
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $currentStep >= $step ? 'bg-blue-600 border-blue-600' : 'bg-gray-200 border-gray-300' }}">
                            @if($currentStep > $step)
                                <flux:icon name="check" class="w-5 h-5 text-white" />
                            @else
                                <span class="text-sm font-semibold {{ $currentStep === $step ? 'text-white' : 'text-gray-600' }}">{{ $step }}</span>
                            @endif
                        </div>
                        <span class="mt-2 text-xs font-medium {{ $currentStep === $step ? 'text-blue-600' : 'text-gray-600' }}">{{ $label }}</span>
                    </div>
                    @if($step < 4)
                        <div class="w-16 h-1 mx-2 {{ $currentStep > $step ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <flux:card class="space-y-6">
        <div class="p-6 space-y-6">
            <!-- Step 1: Information -->
            @if($currentStep === 1)
                <div>
                    <flux:heading size="lg" class="mb-4">Information</flux:heading>

                    <div class="space-y-4">
                        <div>
                            <flux:field>
                                <flux:label>Name</flux:label>
                                <flux:input wire:model="name" placeholder="Enter broadcast name" />
                                <flux:error name="name" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Type</flux:label>
                                <div class="flex gap-4 mt-2">
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer {{ $type === 'standard' ? 'border-blue-600 bg-blue-50' : 'border-gray-300' }}">
                                        <input type="radio" wire:model.live="type" value="standard" class="mr-3">
                                        <div>
                                            <div class="font-medium">Standard</div>
                                            <div class="text-sm text-gray-600">Send the same email to all contacts</div>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer opacity-50 {{ $type === 'ab_test' ? 'border-blue-600 bg-blue-50' : 'border-gray-300' }}">
                                        <input type="radio" wire:model.live="type" value="ab_test" class="mr-3" disabled>
                                        <div>
                                            <div class="font-medium">A/B Test</div>
                                            <div class="text-sm text-gray-600">Test different versions (Coming soon)</div>
                                        </div>
                                    </label>
                                </div>
                                <flux:error name="type" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 2: Contacts -->
            @if($currentStep === 2)
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Contacts</flux:heading>
                        <flux:button variant="outline" size="sm" href="{{ route('crm.audiences.create') }}" target="_blank">
                            Create New Audience
                        </flux:button>
                    </div>

                    <div class="mb-4">
                        <flux:input
                            wire:model.live.debounce.300ms="audienceSearch"
                            placeholder="Search audiences..."
                            icon="magnifying-glass" />
                    </div>

                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-md mb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:text class="font-semibold">Subscribed Contacts</flux:text>
                                <flux:text class="text-sm text-gray-600">
                                    {{ count($selectedAudiences) }} audience(s) selected |
                                    {{ count($selectedStudents) }} of {{ $studentsFromAudiences->count() }} students selected
                                </flux:text>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($totalRecipients) }}</div>
                                <flux:text class="text-xs text-gray-600">Total Recipients</flux:text>
                            </div>
                        </div>
                    </div>

                    <flux:heading size="base" class="mb-2">Select Audiences</flux:heading>
                    <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-300 rounded-md p-4 mb-4">
                        @forelse($audiences as $audience)
                            <label class="flex items-center p-3 hover:bg-gray-50 rounded cursor-pointer">
                                <flux:checkbox
                                    wire:model.live="selectedAudiences"
                                    value="{{ $audience->id }}"
                                />
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $audience->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $audience->description ?: 'No description' }}</div>
                                        </div>
                                        <div class="text-sm font-semibold text-blue-600">
                                            {{ number_format($audience->students_count) }} contacts
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-center py-8 text-gray-500">
                                <flux:icon.users class="mx-auto h-12 w-12 text-gray-400" />
                                <p class="mt-2">No audiences found</p>
                                <p class="text-sm">Create an audience first to send broadcasts</p>
                            </div>
                        @endforelse
                    </div>
                    <flux:error name="selectedAudiences" />

                    <!-- Student List from Selected Audiences -->
                    @if(count($selectedAudiences) > 0 && $studentsFromAudiences->count() > 0)
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <flux:heading size="base">Students from Selected Audiences</flux:heading>
                                <div class="flex gap-2">
                                    <flux:button variant="outline" size="sm" wire:click="selectAllStudents">
                                        Select All ({{ $studentsFromAudiences->count() }})
                                    </flux:button>
                                    <flux:button variant="outline" size="sm" wire:click="deselectAllStudents">
                                        Deselect All
                                    </flux:button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <flux:input
                                    wire:model.live.debounce.300ms="studentSearch"
                                    placeholder="Search students..."
                                    icon="magnifying-glass" />
                            </div>

                            <div class="space-y-2 max-h-96 overflow-y-auto border border-gray-300 rounded-md p-4">
                                @foreach($studentsFromAudiences as $student)
                                    <label class="flex items-center p-3 hover:bg-gray-50 rounded cursor-pointer">
                                        <flux:checkbox
                                            wire:model.live="selectedStudents"
                                            value="{{ $student->id }}"
                                        />
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $student->user->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $student->user->email }} • ID: {{ $student->student_id }}</div>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    @if($student->country)
                                                        <span class="text-xs text-gray-500">{{ $student->country }}</span>
                                                    @endif
                                                    <flux:badge size="sm" :class="match($student->status) {
                                                        'active' => 'badge-green',
                                                        'inactive' => 'badge-gray',
                                                        'graduated' => 'badge-blue',
                                                        'suspended' => 'badge-red',
                                                        default => 'badge-gray'
                                                    }">
                                                        {{ ucfirst($student->status) }}
                                                    </flux:badge>
                                                    @if($student->paidOrders->count() > 0)
                                                        <span class="text-xs text-green-600 font-semibold">RM {{ number_format($student->paidOrders->sum('total_amount'), 2) }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <flux:error name="selectedStudents" />
                        </div>
                    @endif
                </div>
            @endif

            <!-- Step 3: Content -->
            @if($currentStep === 3)
                <div>
                    <flux:heading size="lg" class="mb-4">Content</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <flux:field>
                                    <flux:label>From Name</flux:label>
                                    <flux:input wire:model="from_name" placeholder="Daruf Fateh" />
                                    <flux:error name="from_name" />
                                </flux:field>
                            </div>
                            <div>
                                <flux:field>
                                    <flux:label>From Email</flux:label>
                                    <flux:input wire:model="from_email" type="email" placeholder="admin@darulfateh.com" />
                                    <flux:error name="from_email" />
                                </flux:field>
                            </div>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Reply-To Email</flux:label>
                                <flux:input wire:model="reply_to_email" type="email" placeholder="admin@darulfateh.com" />
                                <flux:error name="reply_to_email" />
                            </flux:field>
                        </div>

                        <flux:separator />

                        <div>
                            <flux:field>
                                <flux:label>Subject</flux:label>
                                <flux:input wire:model="subject" placeholder="Enter subject" />
                                <flux:error name="subject" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Preview Text</flux:label>
                                <flux:input wire:model="preview_text" placeholder="Enter preview text" />
                                <flux:text class="text-xs text-gray-600">This appears in the inbox preview</flux:text>
                                <flux:error name="preview_text" />
                            </flux:field>
                        </div>

                        <div>
                            <flux:field>
                                <flux:label>Email Content</flux:label>
                                <flux:textarea wire:model="content" rows="10" placeholder="Enter your email content here..." />
                                <flux:text class="text-xs text-gray-600">You can use merge tags: @{{name}}, @{{email}}, @{{student_id}}</flux:text>
                                <flux:error name="content" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 4: Review & Schedule -->
            @if($currentStep === 4)
                <div>
                    <flux:heading size="lg" class="mb-4">Review & Schedule</flux:heading>

                    <div class="space-y-6">
                        <!-- Information Summary -->
                        <div>
                            <flux:heading size="base" class="mb-2">Information</flux:heading>
                            <div class="bg-gray-50 p-4 rounded-md space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Title:</span>
                                    <span class="text-sm font-medium">{{ $name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Type:</span>
                                    <span class="text-sm font-medium">{{ ucfirst($type) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Contacts Summary -->
                        <div>
                            <flux:heading size="base" class="mb-2">Contacts</flux:heading>
                            <div class="bg-gray-50 p-4 rounded-md">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Total Recipients:</span>
                                    <span class="text-xl font-bold text-blue-600">{{ number_format($totalRecipients) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Content Summary -->
                        <div>
                            <flux:heading size="base" class="mb-2">Content</flux:heading>
                            <div class="bg-gray-50 p-4 rounded-md space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">From Name:</span>
                                    <span class="text-sm font-medium">{{ $from_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">From Email:</span>
                                    <span class="text-sm font-medium">{{ $from_email }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Reply-To Email:</span>
                                    <span class="text-sm font-medium">{{ $reply_to_email ?: '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Subject:</span>
                                    <span class="text-sm font-medium">{{ $subject }}</span>
                                </div>
                                @if($preview_text)
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Preview Text:</span>
                                        <span class="text-sm font-medium">{{ $preview_text }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <flux:separator />

                        <!-- Schedule Options -->
                        <div>
                            <flux:heading size="base" class="mb-4">Schedule</flux:heading>
                            <div class="space-y-4">
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer {{ $sendOption === 'now' ? 'border-blue-600 bg-blue-50' : 'border-gray-300' }}">
                                    <input type="radio" wire:model.live="sendOption" value="now" class="mr-3">
                                    <div class="flex items-center">
                                        <flux:icon name="paper-airplane" class="w-5 h-5 mr-2 text-blue-600" />
                                        <div>
                                            <div class="font-medium">Broadcast Now</div>
                                            <div class="text-sm text-gray-600">Send immediately to all recipients</div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center p-4 border rounded-lg cursor-pointer {{ $sendOption === 'schedule' ? 'border-blue-600 bg-blue-50' : 'border-gray-300' }}">
                                    <input type="radio" wire:model.live="sendOption" value="schedule" class="mr-3">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <flux:icon name="clock" class="w-5 h-5 mr-2 text-blue-600" />
                                            <div>
                                                <div class="font-medium">Schedule For Later</div>
                                                <div class="text-sm text-gray-600">Choose a specific date and time</div>
                                            </div>
                                        </div>
                                        @if($sendOption === 'schedule')
                                            <div class="mt-3 ml-7">
                                                <flux:input type="datetime-local" wire:model="scheduled_at" />
                                            </div>
                                        @endif
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Navigation Buttons -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
            <div>
                @if($currentStep > 1)
                    <flux:button variant="ghost" wire:click="previousStep">
                        <div class="flex items-center">
                            <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                            Previous
                        </div>
                    </flux:button>
                @else
                    <flux:button variant="ghost" href="{{ route('crm.broadcasts.index') }}">
                        Cancel
                    </flux:button>
                @endif
            </div>

            <div class="flex space-x-3">
                @if($currentStep < 4)
                    <flux:button variant="outline" wire:click="saveDraft">
                        Save as Draft
                    </flux:button>
                @endif

                @if($currentStep < 4)
                    <flux:button variant="primary" wire:click="nextStep">
                        Next
                    </flux:button>
                @else
                    <flux:button variant="primary" wire:click="send">
                        <div class="flex items-center">
                            <flux:icon name="paper-airplane" class="w-4 h-4 mr-1" />
                            {{ $sendOption === 'now' ? 'Send Now' : 'Schedule Broadcast' }}
                        </div>
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>
</div>
