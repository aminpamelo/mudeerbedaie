<?php

use App\Models\Audience;
use App\Models\Broadcast;
use App\Models\FunnelEmailTemplate;
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
    public string $design_json = '';
    public string $html_content = '';
    public string $editor_type = 'text';
    public ?int $selectedTemplateId = null;
    public string $templateSearch = '';

    // Step 4: Schedule
    public $sendOption = 'now';
    public $scheduled_at = '';

    public function mount(): void
    {
        // Load default email settings
        $this->from_email = Setting::where('key', 'email.from_address')->value('value') ?? '';
        $this->from_name = Setting::where('key', 'email.from_name')->value('value') ?? '';
        $this->reply_to_email = $this->from_email;

        $resumeId = request()->query('resume');
        if ($resumeId) {
            $broadcast = Broadcast::find($resumeId);
            if ($broadcast && $broadcast->status === 'draft') {
                $this->name = $broadcast->name ?: '';
                $this->type = $broadcast->type ?: 'standard';
                $this->from_name = $broadcast->from_name ?: $this->from_name;
                $this->from_email = $broadcast->from_email ?: $this->from_email;
                $this->reply_to_email = $broadcast->reply_to_email ?: '';
                $this->subject = $broadcast->subject ?: '';
                $this->preview_text = $broadcast->preview_text ?: '';
                $this->content = $broadcast->content ?: '';
                $this->design_json = $broadcast->design_json ? json_encode($broadcast->design_json) : '';
                $this->html_content = $broadcast->html_content ?: '';
                $this->editor_type = $broadcast->editor_type ?: 'text';
                $this->currentStep = 3;
                $this->selectedAudiences = $broadcast->audiences()->pluck('audiences.id')->toArray();
                $this->selectedStudents = $broadcast->selected_students ?: [];
            }
        }
    }

    public function selectTemplate(int $templateId): void
    {
        $template = FunnelEmailTemplate::find($templateId);
        if (! $template) {
            return;
        }

        $this->selectedTemplateId = $templateId;
        $this->subject = $template->subject ?: $this->subject;
        $this->html_content = $template->html_content ?: '';
        $this->design_json = $template->design_json ? json_encode($template->design_json) : '';
        $this->editor_type = 'visual';
        $this->content = $template->getEffectiveContent();
    }

    public function clearTemplate(): void
    {
        $this->selectedTemplateId = null;
        $this->html_content = '';
        $this->design_json = '';
        $this->editor_type = 'text';
        $this->content = '';
    }

    public function openBuilder(): void
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
            'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
            'html_content' => $this->html_content,
            'editor_type' => $this->editor_type,
            'selected_students' => $this->selectedStudents,
        ]);

        if (! empty($this->selectedAudiences)) {
            $broadcast->audiences()->attach($this->selectedAudiences);
        }

        $this->redirect(route('crm.broadcasts.builder', $broadcast));
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
            'emailTemplates' => FunnelEmailTemplate::where('is_active', true)
                ->when($this->templateSearch, fn ($q) => $q->where('name', 'like', "%{$this->templateSearch}%"))
                ->orderBy('name')
                ->get(),
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
                'content' => $this->editor_type === 'visual' ? 'nullable' : 'required|string',
            ]);

            if ($this->editor_type === 'visual' && empty($this->html_content)) {
                $this->addError('content', 'Please create email content using the builder.');
                return;
            }
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
            'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
            'html_content' => $this->html_content,
            'editor_type' => $this->editor_type,
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
            'content' => $this->editor_type === 'visual' ? 'nullable' : 'required|string',
        ], [
            'selectedStudents.required' => 'Please select at least one student to send the broadcast.',
            'selectedStudents.min' => 'Please select at least one student to send the broadcast.',
        ]);

        if ($this->editor_type === 'visual' && empty($this->html_content)) {
            $this->addError('content', 'Please create email content using the builder.');
            return;
        }

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
            'design_json' => $this->design_json ? json_decode($this->design_json, true) : null,
            'html_content' => $this->html_content,
            'editor_type' => $this->editor_type,
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
        <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $currentStep === 4 ? 'Review & Send' : 'Create Broadcast' }}</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $name ?: 'New Email Broadcast' }}</p>
    </div>

    <!-- Step Indicator -->
    <div class="mb-8">
        <div class="flex items-center justify-center">
            @foreach([1 => 'Information', 2 => 'Contacts', 3 => 'Content', 4 => 'Review'] as $step => $label)
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2
                            {{ $currentStep > $step ? 'bg-zinc-900 border-zinc-900 dark:bg-zinc-100 dark:border-zinc-100' : '' }}
                            {{ $currentStep === $step ? 'bg-zinc-900 border-zinc-900 dark:bg-zinc-100 dark:border-zinc-100' : '' }}
                            {{ $currentStep < $step ? 'bg-zinc-100 border-zinc-200 dark:bg-zinc-800 dark:border-zinc-700' : '' }}
                        ">
                            @if($currentStep > $step)
                                <flux:icon name="check" class="w-5 h-5 text-white dark:text-zinc-900" />
                            @else
                                <span class="text-sm font-semibold {{ $currentStep === $step ? 'text-white dark:text-zinc-900' : 'text-zinc-400 dark:text-zinc-500' }}">{{ $step }}</span>
                            @endif
                        </div>
                        <span class="mt-2 text-xs font-medium {{ $currentStep === $step ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-400 dark:text-zinc-500' }}">{{ $label }}</span>
                    </div>
                    @if($step < 4)
                        <div class="w-16 h-1 mx-2 rounded-full {{ $currentStep > $step ? 'bg-zinc-900 dark:bg-zinc-100' : 'bg-zinc-200 dark:bg-zinc-700' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="p-6 space-y-6">
            <!-- Step 1: Information -->
            @if($currentStep === 1)
                <div>
                    <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 mb-4">Information</h2>

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
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors {{ $type === 'standard' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-300 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        <input type="radio" wire:model.live="type" value="standard" class="mr-3">
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Standard</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">Send the same email to all contacts</div>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer opacity-50 transition-colors {{ $type === 'ab_test' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-300 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        <input type="radio" wire:model.live="type" value="ab_test" class="mr-3" disabled>
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">A/B Test</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">Test different versions (Coming soon)</div>
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
                        <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Contacts</h2>
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

                    <div class="rounded-md border border-zinc-200 bg-zinc-50/50 p-4 mb-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Subscribed Contacts</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ count($selectedAudiences) }} audience(s) selected |
                                    {{ count($selectedStudents) }} of {{ $studentsFromAudiences->count() }} students selected
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($totalRecipients) }}</div>
                                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Recipients</p>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Select Audiences</h3>
                    <div class="space-y-1 max-h-64 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-md p-3 mb-4">
                        @forelse($audiences as $audience)
                            <label class="flex items-center p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded cursor-pointer transition-colors">
                                <flux:checkbox
                                    wire:model.live="selectedAudiences"
                                    value="{{ $audience->id }}"
                                />
                                <div class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $audience->name }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $audience->description ?: 'No description' }}</div>
                                        </div>
                                        <div class="text-sm font-semibold tabular-nums text-zinc-500 dark:text-zinc-400">
                                            {{ number_format($audience->students_count) }} contacts
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.users class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No audiences found</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Create an audience first to send broadcasts</p>
                            </div>
                        @endforelse
                    </div>
                    <flux:error name="selectedAudiences" />

                    <!-- Student List from Selected Audiences -->
                    @if(count($selectedAudiences) > 0 && $studentsFromAudiences->count() > 0)
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Students from Selected Audiences</h3>
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

                            <div class="space-y-1 max-h-96 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-md p-3">
                                @foreach($studentsFromAudiences as $student)
                                    <label class="flex items-center p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 rounded cursor-pointer transition-colors">
                                        <flux:checkbox
                                            wire:model.live="selectedStudents"
                                            value="{{ $student->id }}"
                                        />
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->user->name }}</div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $student->user->email }} · ID: {{ $student->student_id }}</div>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    @if($student->country)
                                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $student->country }}</span>
                                                    @endif
                                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider
                                                        {{ match($student->status) {
                                                            'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                                            'inactive' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400',
                                                            'graduated' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
                                                            'suspended' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
                                                            default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400'
                                                        } }}
                                                    ">
                                                        {{ ucfirst($student->status) }}
                                                    </span>
                                                    @if($student->paidOrders->count() > 0)
                                                        <span class="text-xs font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">RM {{ number_format($student->paidOrders->sum('total_amount'), 2) }}</span>
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
                    <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 mb-4">Content</h2>

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

                        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

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
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">This appears in the inbox preview</p>
                                <flux:error name="preview_text" />
                            </flux:field>
                        </div>

                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4"></div>

                        <!-- Content Mode Selector -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Email Content</h3>
                                @if($editor_type === 'visual')
                                    <flux:badge color="emerald" size="sm">Visual Builder</flux:badge>
                                @endif
                            </div>

                            <!-- Template Picker -->
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">Choose a Template</flux:text>
                                    <flux:input wire:model.live.debounce.300ms="templateSearch" placeholder="Search templates..." size="sm" class="!w-48" />
                                </div>

                                @if($emailTemplates->isNotEmpty())
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-56 overflow-y-auto">
                                        @foreach($emailTemplates as $tmpl)
                                            <button
                                                wire:click="selectTemplate({{ $tmpl->id }})"
                                                wire:key="tmpl-{{ $tmpl->id }}"
                                                type="button"
                                                class="text-left p-3 rounded-lg border transition-all {{ $selectedTemplateId === $tmpl->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-500' : 'border-zinc-200 dark:border-zinc-600 hover:border-zinc-300 dark:hover:border-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-700/50' }}"
                                            >
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $tmpl->name }}</div>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <flux:badge size="sm" color="zinc">{{ ucfirst($tmpl->category) }}</flux:badge>
                                                    @if($tmpl->editor_type === 'visual')
                                                        <flux:badge size="sm" color="blue">Visual</flux:badge>
                                                    @endif
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                @else
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">No email templates available.</flux:text>
                                @endif

                                @if($selectedTemplateId)
                                    <div class="mt-3 flex items-center gap-2">
                                        <flux:text size="sm" class="text-emerald-600 dark:text-emerald-400">
                                            Template selected — content loaded
                                        </flux:text>
                                        <flux:button variant="ghost" size="sm" wire:click="clearTemplate">Clear</flux:button>
                                    </div>
                                @endif
                            </div>

                            <!-- Builder Actions -->
                            <div class="flex flex-wrap items-center gap-3">
                                <flux:button variant="primary" size="sm" wire:click="openBuilder">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="paint-brush" class="w-4 h-4 mr-1" />
                                        {{ $editor_type === 'visual' ? 'Edit in Builder' : 'Open Visual Builder' }}
                                    </div>
                                </flux:button>

                                @if($editor_type === 'visual' && $html_content)
                                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                                        Content created with visual builder
                                    </flux:text>
                                @endif
                            </div>

                            <!-- Fallback: Manual HTML content -->
                            @if($editor_type !== 'visual')
                                <div>
                                    <flux:field>
                                        <flux:label>Email Content (HTML)</flux:label>
                                        <flux:textarea wire:model="content" rows="10" placeholder="Enter your email content here..." />
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">You can use merge tags: @{{name}}, @{{email}}, @{{student_id}}</p>
                                        <flux:error name="content" />
                                    </flux:field>
                                </div>
                            @else
                                <!-- Visual content preview -->
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                    <div class="bg-zinc-50 dark:bg-zinc-800 px-4 py-2 border-b border-zinc-200 dark:border-zinc-700">
                                        <flux:text size="sm" class="font-medium text-zinc-600 dark:text-zinc-400">Email Preview</flux:text>
                                    </div>
                                    <div class="p-4 bg-white dark:bg-zinc-900">
                                        <iframe
                                            srcdoc="{{ $html_content }}"
                                            class="w-full h-64 border-0 rounded"
                                            sandbox="allow-same-origin"
                                        ></iframe>
                                    </div>
                                </div>
                                <flux:error name="content" />
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 4: Review & Schedule -->
            @if($currentStep === 4)
                <div>
                    <h2 class="text-base font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 mb-4">Review & Schedule</h2>

                    <div class="space-y-6">
                        <!-- Information Summary -->
                        <div>
                            <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Information</h3>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50/50 p-4 space-y-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Title</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Type</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($type) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Contacts Summary -->
                        <div>
                            <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Contacts</h3>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex justify-between items-center">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Recipients</span>
                                    <span class="text-xl font-bold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($totalRecipients) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Content Summary -->
                        <div>
                            <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Content</h3>
                            <div class="rounded-md border border-zinc-200 bg-zinc-50/50 p-4 space-y-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From Name</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $from_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">From Email</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $from_email }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Reply-To Email</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $reply_to_email ?: '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Subject</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $subject }}</span>
                                </div>
                                @if($preview_text)
                                    <div class="flex justify-between">
                                        <span class="text-[11px] uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Preview Text</span>
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $preview_text }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>

                        <!-- Schedule Options -->
                        <div>
                            <h3 class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-4">Schedule</h3>
                            <div class="space-y-3">
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors {{ $sendOption === 'now' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-300 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                                    <input type="radio" wire:model.live="sendOption" value="now" class="mr-3">
                                    <div class="flex items-center">
                                        <flux:icon name="paper-airplane" class="w-5 h-5 mr-2 text-zinc-600 dark:text-zinc-400" />
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">Broadcast Now</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">Send immediately to all recipients</div>
                                        </div>
                                    </div>
                                </label>

                                <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors {{ $sendOption === 'schedule' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-300 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                                    <input type="radio" wire:model.live="sendOption" value="schedule" class="mr-3">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <flux:icon name="clock" class="w-5 h-5 mr-2 text-zinc-600 dark:text-zinc-400" />
                                            <div>
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">Schedule For Later</div>
                                                <div class="text-sm text-zinc-500 dark:text-zinc-400">Choose a specific date and time</div>
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
        <div class="px-6 py-4 border-t border-zinc-200 bg-zinc-50/50 dark:border-zinc-700 dark:bg-zinc-800/30 flex justify-between rounded-b-lg">
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
    </div>
</div>
