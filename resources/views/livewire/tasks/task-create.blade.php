<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskType;
use Livewire\Volt\Component;

new class extends Component {
    public ?Department $department = null;

    public string $title = '';
    public string $description = '';
    public string $task_type = 'adhoc';
    public string $status = 'todo';
    public string $priority = 'medium';
    public ?int $assigned_to = null;
    public ?string $due_date = null;
    public ?string $due_time = null;
    public ?float $estimated_hours = null;
    public int $department_id = 0;
    public int $template_id = 0;

    public function mount(): void
    {
        $user = auth()->user();

        // Check if user is PIC of any department (only PICs can create tasks)
        if (! $user->picDepartments()->exists()) {
            abort(403, 'Only PICs can create tasks.');
        }

        // If department is provided via query string
        if (request()->has('department')) {
            $dept = Department::where('slug', request()->get('department'))->first();
            if ($dept && $user->canCreateTasks($dept)) {
                $this->department = $dept;
                $this->department_id = $dept->id;
            }
        }

        // Default to first PIC department if no specific one
        if (! $this->department) {
            $this->department = $user->picDepartments()->first();
            if ($this->department) {
                $this->department_id = $this->department->id;
            }
        }
    }

    public function getAvailableDepartments()
    {
        // Only return departments where user is PIC
        return auth()->user()->picDepartments;
    }

    public function getDepartmentUsers()
    {
        if (! $this->department_id) {
            return collect();
        }

        $department = Department::find($this->department_id);
        return $department ? $department->users : collect();
    }

    public function updatedDepartmentId(): void
    {
        $this->department = Department::find($this->department_id);
        $this->assigned_to = null;
        $this->template_id = 0;
    }

    public function getTemplates()
    {
        if (! $this->department_id) {
            return collect();
        }

        return TaskTemplate::where('department_id', $this->department_id)
            ->orderBy('name')
            ->get();
    }

    public function updatedTemplateId(): void
    {
        if (! $this->template_id) {
            return;
        }

        $template = TaskTemplate::find($this->template_id);
        if (! $template) {
            return;
        }

        $this->task_type = $template->task_type->value;
        $this->priority = $template->priority->value;
        $this->estimated_hours = $template->estimated_hours ? (float) $template->estimated_hours : null;

        if (! empty($template->template_data['title'])) {
            $this->title = $template->template_data['title'];
        }
        if (! empty($template->template_data['description'])) {
            $this->description = $template->template_data['description'];
        }
    }

    public function create(): void
    {
        $user = auth()->user();

        // Validate
        $this->validate([
            'department_id' => 'required|exists:departments,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'required|in:kpi,adhoc',
            'status' => 'required|in:todo,in_progress,review,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'estimated_hours' => 'nullable|numeric|min:0',
        ]);

        // Check permission for selected department (only PICs can create)
        $department = Department::findOrFail($this->department_id);
        if (! $user->canCreateTasks($department)) {
            abort(403, 'You cannot create tasks in this department.');
        }

        // Create task
        $task = Task::create([
            'department_id' => $this->department_id,
            'title' => $this->title,
            'description' => $this->description,
            'task_type' => $this->task_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'created_by' => $user->id,
            'due_date' => $this->due_date,
            'due_time' => $this->due_time,
            'estimated_hours' => $this->estimated_hours,
        ]);

        // Log activity
        $task->logActivity('created', null, null, 'Task created');

        // Redirect to task detail
        $this->redirect(route('tasks.show', $task), navigate: true);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Create New Task') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add a new task to a department') }}</flux:text>
            </div>
            <flux:button variant="ghost" :href="$department ? route('tasks.department.board', $department->slug) : route('tasks.dashboard')">
                <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto">
        <form wire:submit="create" class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="p-6 space-y-6">
                {{-- Department Selection --}}
                <flux:field>
                    <flux:label>{{ __('Department') }}</flux:label>
                    <flux:select wire:model.live="department_id">
                        <flux:select.option value="">{{ __('Select Department') }}</flux:select.option>
                        @foreach($this->getAvailableDepartments() as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="department_id" />
                </flux:field>

                {{-- Template Selection --}}
                @if($department_id)
                @php $templates = $this->getTemplates(); @endphp
                @if($templates->count() > 0)
                <flux:field>
                    <flux:label>{{ __('Load from Template') }}</flux:label>
                    <flux:select wire:model.live="template_id">
                        <flux:select.option value="0">{{ __('— No template —') }}</flux:select.option>
                        @foreach($templates as $template)
                        <flux:select.option value="{{ $template->id }}">{{ $template->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Select a template to pre-fill the form fields') }}</flux:description>
                </flux:field>
                @endif
                @endif

                {{-- Title --}}
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:input wire:model="title" placeholder="{{ __('Enter task title...') }}" />
                    <flux:error name="title" />
                </flux:field>

                {{-- Description --}}
                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="4" placeholder="{{ __('Enter task description...') }}" />
                    <flux:error name="description" />
                </flux:field>

                {{-- Type, Status, Priority --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Type') }}</flux:label>
                        <flux:select wire:model="task_type">
                            @foreach(TaskType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="task_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Status') }}</flux:label>
                        <flux:select wire:model="status">
                            @foreach(TaskStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Priority') }}</flux:label>
                        <flux:select wire:model="priority">
                            @foreach(TaskPriority::cases() as $priority)
                            <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="priority" />
                    </flux:field>
                </div>

                {{-- Assignee --}}
                <flux:field>
                    <flux:label>{{ __('Assign To') }}</flux:label>
                    <flux:select wire:model="assigned_to">
                        <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                        @foreach($this->getDepartmentUsers() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Select a department member to assign this task to') }}</flux:description>
                    <flux:error name="assigned_to" />
                </flux:field>

                {{-- Due Date & Time --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Due Date') }}</flux:label>
                        <flux:input type="date" wire:model="due_date" />
                        <flux:error name="due_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Due Time') }}</flux:label>
                        <flux:input type="time" wire:model="due_time" />
                        <flux:error name="due_time" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Estimated Hours') }}</flux:label>
                        <flux:input type="number" step="0.5" min="0" wire:model="estimated_hours" placeholder="0" />
                        <flux:error name="estimated_hours" />
                    </flux:field>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 rounded-b-lg flex justify-end gap-2">
                <flux:button variant="ghost" :href="$department ? route('tasks.department.board', $department->slug) : route('tasks.dashboard')">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Create Task') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
