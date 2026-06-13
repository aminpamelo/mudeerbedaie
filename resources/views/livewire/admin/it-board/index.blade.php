<?php

use App\Models\ItTicket;
use App\Models\ItTicketCategory;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    // Filters
    public string $search = '';
    public string $typeFilter = '';
    public string $priorityFilter = '';
    public string $categoryFilter = '';
    public string $assigneeFilter = '';

    #[Url]
    public string $viewMode = 'board';

    #[Url(as: 'ticket')]
    public ?int $openTicketId = null;

    // Create modal
    public bool $showCreateModal = false;
    public string $cTitle = '';
    public string $cDescription = '';
    public string $cType = 'task';
    public string $cPriority = 'medium';
    public string $cStatus = 'backlog';
    public $cCategoryId = null;
    public $cAssigneeId = null;
    public ?string $cDueDate = null;

    // Edit flyout
    public bool $showEditModal = false;
    public ?int $editingId = null;
    public string $eTitle = '';
    public string $eDescription = '';
    public string $eType = 'task';
    public string $ePriority = 'medium';
    public string $eStatus = 'backlog';
    public $eCategoryId = null;
    public $eAssigneeId = null;
    public ?string $eDueDate = null;
    public string $commentBody = '';

    // Category manager
    public bool $showCategoryModal = false;
    public string $catName = '';
    public string $catColor = '#6366f1';
    public ?int $editingCatId = null;
    public string $editCatName = '';
    public string $editCatColor = '#6366f1';

    public function layout(): string
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(): void
    {
        if ($this->openTicketId) {
            $this->openEdit($this->openTicketId);
        }
    }

    // --- Static presentation maps (literal classes so Tailwind detects them) ---

    public function getColumnsProperty(): array
    {
        return [
            'backlog' => ['label' => 'Backlog', 'dot' => 'bg-slate-400', 'bar' => 'from-slate-400 via-slate-400/40 to-transparent'],
            'todo' => ['label' => 'To Do', 'dot' => 'bg-blue-500', 'bar' => 'from-blue-500 via-blue-500/40 to-transparent'],
            'in_progress' => ['label' => 'In Progress', 'dot' => 'bg-amber-500', 'bar' => 'from-amber-500 via-amber-500/40 to-transparent'],
            'review' => ['label' => 'Review', 'dot' => 'bg-violet-500', 'bar' => 'from-violet-500 via-violet-500/40 to-transparent'],
            'testing' => ['label' => 'Testing', 'dot' => 'bg-orange-500', 'bar' => 'from-orange-500 via-orange-500/40 to-transparent'],
            'done' => ['label' => 'Done', 'dot' => 'bg-emerald-500', 'bar' => 'from-emerald-500 via-emerald-500/40 to-transparent'],
        ];
    }

    public function typeMeta(string $type): array
    {
        return match ($type) {
            'bug' => ['label' => 'Bug', 'icon' => 'bug-ant', 'classes' => 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400'],
            'feature' => ['label' => 'Feature', 'icon' => 'sparkles', 'classes' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'improvement' => ['label' => 'Improvement', 'icon' => 'arrow-trending-up', 'classes' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400'],
            default => ['label' => 'Task', 'icon' => 'check-circle', 'classes' => 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400'],
        };
    }

    public function priorityMeta(string $priority): array
    {
        return match ($priority) {
            'urgent' => ['label' => 'Urgent', 'classes' => 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400', 'dot' => 'bg-red-500'],
            'high' => ['label' => 'High', 'classes' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400', 'dot' => 'bg-amber-500'],
            'medium' => ['label' => 'Medium', 'classes' => 'text-blue-600 bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400', 'dot' => 'bg-blue-500'],
            default => ['label' => 'Low', 'classes' => 'text-zinc-500 bg-zinc-100 dark:bg-zinc-700/50 dark:text-zinc-400', 'dot' => 'bg-zinc-400'],
        };
    }

    // --- Data ---

    private function filteredQuery()
    {
        return ItTicket::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->priorityFilter, fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->assigneeFilter, fn ($q) => $q->where('assignee_id', $this->assigneeFilter));
    }

    public function getTicketsProperty()
    {
        return $this->filteredQuery()
            ->with(['reporter', 'assignee', 'category'])
            ->orderBy('position')
            ->get()
            ->groupBy('status');
    }

    public function getListTicketsProperty()
    {
        $grouped = $this->tickets;

        return collect(array_keys($this->columns))
            ->flatMap(fn ($status) => $grouped[$status] ?? collect())
            ->values();
    }

    public function getStatsProperty(): array
    {
        $all = $this->tickets->flatten();

        return [
            'total' => $all->count(),
            'in_progress' => $all->where('status', 'in_progress')->count(),
            'urgent' => $all->where('priority', 'urgent')->where('status', '!=', 'done')->count(),
            'overdue' => $all->filter(fn ($t) => $t->isOverdue())->count(),
            'done' => $all->where('status', 'done')->count(),
        ];
    }

    public function getHasFiltersProperty(): bool
    {
        return $this->search !== '' || $this->typeFilter !== '' || $this->priorityFilter !== ''
            || $this->categoryFilter !== '' || $this->assigneeFilter !== '';
    }

    public function getCategoriesProperty()
    {
        return ItTicketCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function getSelectedTicketProperty(): ?ItTicket
    {
        if (! $this->editingId) {
            return null;
        }

        return ItTicket::with(['reporter', 'assignee', 'category', 'comments.user'])->find($this->editingId);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'priorityFilter', 'categoryFilter', 'assigneeFilter']);
    }

    // --- Drag & drop ---

    public function moveTicket(int $ticketId, string $newStatus, int $newPosition, array $orderedIds): void
    {
        $ticket = ItTicket::find($ticketId);

        if (! $ticket) {
            return;
        }

        $data = [
            'status' => $newStatus,
            'position' => $newPosition,
        ];

        if ($newStatus === 'done' && $ticket->status !== 'done') {
            $data['completed_at'] = now();
        } elseif ($newStatus !== 'done' && $ticket->status === 'done') {
            $data['completed_at'] = null;
        }

        $ticket->update($data);

        foreach ($orderedIds as $position => $id) {
            ItTicket::where('id', $id)->update(['position' => $position]);
        }
    }

    // --- Create ---

    public function openCreate(string $status = 'backlog'): void
    {
        $this->reset(['cTitle', 'cDescription', 'cCategoryId', 'cAssigneeId', 'cDueDate']);
        $this->cType = 'task';
        $this->cPriority = 'medium';
        $this->cStatus = $status;
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function createTicket(): void
    {
        $this->cCategoryId = $this->cCategoryId ?: null;
        $this->cAssigneeId = $this->cAssigneeId ?: null;
        $this->cDueDate = $this->cDueDate ?: null;

        $this->validate([
            'cTitle' => 'required|min:3|max:255',
            'cDescription' => 'nullable|max:5000',
            'cType' => 'required|in:'.implode(',', ItTicket::types()),
            'cPriority' => 'required|in:'.implode(',', ItTicket::priorities()),
            'cStatus' => 'required|in:'.implode(',', ItTicket::statuses()),
            'cCategoryId' => 'nullable|exists:it_ticket_categories,id',
            'cAssigneeId' => 'nullable|exists:users,id',
            'cDueDate' => 'nullable|date',
        ]);

        ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->cTitle,
            'description' => $this->cDescription,
            'type' => $this->cType,
            'priority' => $this->cPriority,
            'category_id' => $this->cCategoryId,
            'status' => $this->cStatus,
            'position' => (ItTicket::where('status', $this->cStatus)->max('position') ?? -1) + 1,
            'reporter_id' => auth()->id(),
            'assignee_id' => $this->cAssigneeId,
            'due_date' => $this->cDueDate,
        ]);

        $this->showCreateModal = false;
    }

    // --- Edit ---

    public function openEdit(int $ticketId): void
    {
        $ticket = ItTicket::find($ticketId);

        if (! $ticket) {
            return;
        }

        $this->editingId = $ticket->id;
        $this->eTitle = $ticket->title;
        $this->eDescription = $ticket->description ?? '';
        $this->eType = $ticket->type;
        $this->ePriority = $ticket->priority;
        $this->eStatus = $ticket->status;
        $this->eCategoryId = $ticket->category_id;
        $this->eAssigneeId = $ticket->assignee_id;
        $this->eDueDate = $ticket->due_date?->format('Y-m-d');
        $this->commentBody = '';
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function saveTicket(): void
    {
        $ticket = ItTicket::find($this->editingId);

        if (! $ticket) {
            return;
        }

        $this->eCategoryId = $this->eCategoryId ?: null;
        $this->eAssigneeId = $this->eAssigneeId ?: null;
        $this->eDueDate = $this->eDueDate ?: null;

        $this->validate([
            'eTitle' => 'required|min:3|max:255',
            'eDescription' => 'nullable|max:5000',
            'eType' => 'required|in:'.implode(',', ItTicket::types()),
            'ePriority' => 'required|in:'.implode(',', ItTicket::priorities()),
            'eStatus' => 'required|in:'.implode(',', ItTicket::statuses()),
            'eCategoryId' => 'nullable|exists:it_ticket_categories,id',
            'eAssigneeId' => 'nullable|exists:users,id',
            'eDueDate' => 'nullable|date',
        ]);

        $data = [
            'title' => $this->eTitle,
            'description' => $this->eDescription,
            'type' => $this->eType,
            'priority' => $this->ePriority,
            'category_id' => $this->eCategoryId,
            'status' => $this->eStatus,
            'assignee_id' => $this->eAssigneeId,
            'due_date' => $this->eDueDate,
        ];

        if ($this->eStatus === 'done' && $ticket->status !== 'done') {
            $data['completed_at'] = now();
        } elseif ($this->eStatus !== 'done' && $ticket->status === 'done') {
            $data['completed_at'] = null;
        }

        $ticket->update($data);

        $this->dispatch('saved');
    }

    public function addComment(): void
    {
        $this->validate(['commentBody' => 'required|min:1|max:5000']);

        ItTicket::find($this->editingId)?->comments()->create([
            'user_id' => auth()->id(),
            'body' => $this->commentBody,
        ]);

        $this->commentBody = '';
    }

    public function deleteComment(int $commentId): void
    {
        ItTicket::find($this->editingId)?->comments()->where('id', $commentId)->delete();
    }

    public function deleteTicket(): void
    {
        ItTicket::find($this->editingId)?->delete();
        $this->showEditModal = false;
        $this->editingId = null;
    }

    // --- Category manager ---

    public function openCategoryManager(): void
    {
        $this->reset(['catName', 'editingCatId', 'editCatName']);
        $this->catColor = '#6366f1';
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function addCategory(): void
    {
        $this->validate([
            'catName' => 'required|min:2|max:50',
            'catColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        ItTicketCategory::create([
            'name' => $this->catName,
            'color' => $this->catColor,
            'sort_order' => (ItTicketCategory::max('sort_order') ?? 0) + 1,
        ]);

        $this->reset(['catName']);
        $this->catColor = '#6366f1';
    }

    public function startEditCategory(int $id): void
    {
        $cat = ItTicketCategory::find($id);

        if (! $cat) {
            return;
        }

        $this->editingCatId = $id;
        $this->editCatName = $cat->name;
        $this->editCatColor = $cat->color;
    }

    public function updateCategory(): void
    {
        $this->validate([
            'editCatName' => 'required|min:2|max:50',
            'editCatColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        ItTicketCategory::where('id', $this->editingCatId)->update([
            'name' => $this->editCatName,
            'color' => $this->editCatColor,
        ]);

        $this->editingCatId = null;
    }

    public function cancelEditCategory(): void
    {
        $this->editingCatId = null;
    }

    public function deleteCategory(int $id): void
    {
        ItTicketCategory::find($id)?->delete();

        if ((int) $this->categoryFilter === $id) {
            $this->categoryFilter = '';
        }
    }
}; ?>

<div class="text-zinc-800 dark:text-zinc-200">
    {{-- Header --}}
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-lg shadow-indigo-500/20">
                <flux:icon name="rectangle-group" class="size-5" />
            </div>
            <div>
                <flux:heading size="xl">IT Board</flux:heading>
                <flux:text class="mt-0.5">Track IT tasks, bugs and development requests</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" size="sm" wire:click="openCategoryManager">
                <div class="flex items-center">
                    <flux:icon name="tag" class="mr-1.5 size-4" />
                    Categories
                </div>
            </flux:button>
            <flux:button variant="primary" size="sm" wire:click="openCreate">
                <div class="flex items-center">
                    <flux:icon name="plus" class="mr-1.5 size-4" />
                    New Ticket
                </div>
            </flux:button>
        </div>
    </div>

    {{-- Stats bar --}}
    @php $stats = $this->stats; @endphp
    <div class="mb-4 flex flex-wrap items-stretch gap-px overflow-hidden rounded-xl border border-zinc-200/70 bg-zinc-200/50 dark:border-white/[0.06] dark:bg-white/[0.06]">
        @foreach ([
            ['label' => 'Total', 'value' => $stats['total'], 'icon' => 'rectangle-stack', 'chip' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-300'],
            ['label' => 'In Progress', 'value' => $stats['in_progress'], 'icon' => 'bolt', 'chip' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'],
            ['label' => 'Urgent', 'value' => $stats['urgent'], 'icon' => 'fire', 'chip' => 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400'],
            ['label' => 'Overdue', 'value' => $stats['overdue'], 'icon' => 'exclamation-triangle', 'chip' => 'bg-orange-100 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400'],
            ['label' => 'Done', 'value' => $stats['done'], 'icon' => 'check-circle', 'chip' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400'],
        ] as $stat)
            <div class="flex min-w-[130px] flex-1 items-center gap-2.5 bg-white px-4 py-3 dark:bg-zinc-900">
                <span class="flex size-8 items-center justify-center rounded-lg {{ $stat['chip'] }}">
                    <flux:icon name="{{ $stat['icon'] }}" class="size-4" />
                </span>
                <div class="leading-tight">
                    <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $stat['value'] }}</div>
                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Toolbar: view toggle + filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-lg border border-zinc-200 bg-zinc-100/70 p-0.5 dark:border-white/10 dark:bg-zinc-800/60">
            <button type="button" wire:click="$set('viewMode', 'board')"
                class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition {{ $viewMode === 'board' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="view-columns" class="size-4" /> Board
            </button>
            <button type="button" wire:click="$set('viewMode', 'list')"
                class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition {{ $viewMode === 'list' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon name="list-bullet" class="size-4" /> List
            </button>
        </div>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="w-48">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search tickets..." icon="magnifying-glass" size="sm" />
            </div>
            <div class="w-32">
                <flux:select wire:model.live="typeFilter" size="sm">
                    <option value="">All Types</option>
                    @foreach (ItTicket::types() as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-32">
                <flux:select wire:model.live="priorityFilter" size="sm">
                    <option value="">Priority</option>
                    @foreach (ItTicket::priorities() as $priority)
                        <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-36">
                <flux:select wire:model.live="categoryFilter" size="sm">
                    <option value="">All Categories</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-36">
                <flux:select wire:model.live="assigneeFilter" size="sm">
                    <option value="">All Assignees</option>
                    @foreach ($this->adminUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            @if ($this->hasFilters)
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                    <div class="flex items-center">
                        <flux:icon name="x-mark" class="mr-1 size-4" /> Clear
                    </div>
                </flux:button>
            @endif
        </div>
    </div>

    {{-- ===================== BOARD VIEW ===================== --}}
    @if ($viewMode === 'board')
        <div class="kanban-board flex gap-3 overflow-x-auto pb-4">
            @foreach ($this->columns as $status => $column)
                @php $columnTickets = $this->tickets[$status] ?? collect(); @endphp
                <div wire:key="col-{{ $status }}" class="group/col flex min-w-[280px] flex-1 flex-col">
                    {{-- accent bar --}}
                    <div class="mb-2.5 h-1 rounded-full bg-gradient-to-r {{ $column['bar'] }}"></div>

                    {{-- column header --}}
                    <div class="mb-2 flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $column['dot'] }}"></span>
                            <h3 class="text-[13px] font-semibold text-zinc-700 dark:text-zinc-200">{{ $column['label'] }}</h3>
                            <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-zinc-500 dark:bg-zinc-700/60 dark:text-zinc-400">{{ $columnTickets->count() }}</span>
                        </div>
                        <button type="button" wire:click="openCreate('{{ $status }}')" aria-label="Add ticket to {{ $column['label'] }}"
                            class="flex size-6 items-center justify-center rounded-md text-zinc-400 opacity-0 transition hover:bg-zinc-100 hover:text-zinc-700 group-hover/col:opacity-100 dark:hover:bg-zinc-700 dark:hover:text-zinc-200">
                            <flux:icon name="plus" class="size-4" />
                        </button>
                    </div>

                    {{-- drop zone --}}
                    <div data-status="{{ $status }}"
                        class="kanban-col flex-1 space-y-2.5 overflow-y-auto rounded-xl border border-zinc-200/70 bg-zinc-100/40 p-2 dark:border-white/[0.05] dark:bg-white/[0.02]"
                        style="min-height: 140px; max-height: calc(100vh - 360px);">
                        @forelse ($columnTickets as $ticket)
                            @php $tMeta = $this->typeMeta($ticket->type); $pMeta = $this->priorityMeta($ticket->priority); $dMeta = $ticket->deadlineMeta(); @endphp
                            <div wire:key="ticket-{{ $ticket->id }}" data-ticket-id="{{ $ticket->id }}"
                                wire:click="openEdit({{ $ticket->id }})"
                                class="kanban-card group cursor-pointer rounded-xl border border-zinc-200/80 bg-white p-3 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-white/[0.06] dark:bg-zinc-800/80 dark:hover:border-white/15 {{ $ticket->priority === 'urgent' && $ticket->status !== 'done' ? 'ring-1 ring-red-500/30 dark:ring-red-500/25' : '' }}">
                                {{-- top row: type + ticket number --}}
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $tMeta['classes'] }}">
                                        <flux:icon name="{{ $tMeta['icon'] }}" class="size-3" /> {{ $tMeta['label'] }}
                                    </span>
                                    <span class="truncate font-mono text-[10px] text-zinc-400 dark:text-zinc-500" title="{{ $ticket->ticket_number }}">{{ $ticket->ticket_number }}</span>
                                </div>

                                {{-- title --}}
                                <p class="line-clamp-2 text-[13px] font-medium leading-snug text-zinc-800 group-hover:text-indigo-600 dark:text-zinc-100 dark:group-hover:text-indigo-400">
                                    {{ $ticket->title }}
                                </p>

                                {{-- category --}}
                                @if ($ticket->category)
                                    <div class="mt-2 inline-flex items-center gap-1.5">
                                        <span class="size-2 rounded-full" style="background-color: {{ $ticket->category->color }}"></span>
                                        <span class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $ticket->category->name }}</span>
                                    </div>
                                @endif

                                {{-- footer --}}
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        @if ($ticket->assignee)
                                            <flux:avatar size="xs" :name="$ticket->assignee->name" />
                                            <span class="truncate text-[11px] text-zinc-500 dark:text-zinc-400">{{ $ticket->assignee->name }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] text-zinc-400 dark:text-zinc-500">
                                                <flux:icon name="user-circle" class="size-4" /> Unassigned
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $pMeta['classes'] }}">
                                            <span class="size-1.5 rounded-full {{ $pMeta['dot'] }}"></span> {{ $pMeta['label'] }}
                                        </span>
                                        @if ($ticket->due_date)
                                            <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $dMeta['classes'] }}" title="{{ $dMeta['label'] }}">
                                                <flux:icon name="{{ $dMeta['icon'] }}" class="size-3" /> {{ $dMeta['short'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-placeholder flex flex-col items-center justify-center gap-1.5 py-8 text-center">
                                <flux:icon name="inbox" class="size-6 text-zinc-300 dark:text-zinc-600" />
                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">No tickets</span>
                                <button type="button" wire:click="openCreate('{{ $status }}')" class="text-[11px] font-medium text-indigo-500 hover:text-indigo-600 dark:text-indigo-400">+ Add ticket</button>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ===================== LIST VIEW ===================== --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-white/[0.06] dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200/80 bg-zinc-50/80 text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:border-white/[0.06] dark:bg-white/[0.02] dark:text-zinc-400">
                            <th class="px-4 py-3 font-semibold">Ticket</th>
                            <th class="px-3 py-3 font-semibold">Category</th>
                            <th class="px-3 py-3 font-semibold">Type</th>
                            <th class="px-3 py-3 font-semibold">Priority</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 font-semibold">Assignee</th>
                            <th class="px-3 py-3 font-semibold">Deadline</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-white/[0.05]">
                        @forelse ($this->listTickets as $ticket)
                            @php $tMeta = $this->typeMeta($ticket->type); $pMeta = $this->priorityMeta($ticket->priority); $dMeta = $ticket->deadlineMeta(); $col = $this->columns[$ticket->status]; @endphp
                            <tr wire:key="row-{{ $ticket->id }}" wire:click="openEdit({{ $ticket->id }})"
                                class="group cursor-pointer transition hover:bg-zinc-50 dark:hover:bg-white/[0.03]">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        @if ($ticket->priority === 'urgent' && $ticket->status !== 'done')
                                            <span class="size-1.5 shrink-0 rounded-full bg-red-500" title="Urgent"></span>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-zinc-800 group-hover:text-indigo-600 dark:text-zinc-100 dark:group-hover:text-indigo-400">{{ $ticket->title }}</div>
                                            <div class="font-mono text-[11px] text-zinc-400 dark:text-zinc-500">{{ $ticket->ticket_number }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($ticket->category)
                                        <span class="inline-flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">
                                            <span class="size-2 rounded-full" style="background-color: {{ $ticket->category->color }}"></span>
                                            {{ $ticket->category->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $tMeta['classes'] }}">
                                        <flux:icon name="{{ $tMeta['icon'] }}" class="size-3" /> {{ $tMeta['label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $pMeta['classes'] }}">
                                        <span class="size-1.5 rounded-full {{ $pMeta['dot'] }}"></span> {{ $pMeta['label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                        <span class="size-2 rounded-full {{ $col['dot'] }}"></span> {{ $col['label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($ticket->assignee)
                                        <div class="flex items-center gap-1.5">
                                            <flux:avatar size="xs" :name="$ticket->assignee->name" />
                                            <span class="truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $ticket->assignee->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($ticket->due_date)
                                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $dMeta['classes'] }}">
                                            <flux:icon name="{{ $dMeta['icon'] }}" class="size-3" /> {{ $dMeta['label'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-300 dark:text-zinc-600">No deadline</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon name="inbox" class="size-8 text-zinc-300 dark:text-zinc-600" />
                                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No tickets found</p>
                                        <button type="button" wire:click="openCreate" class="text-xs font-medium text-indigo-500 hover:text-indigo-600 dark:text-indigo-400">+ Create your first ticket</button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== EDIT MODAL ===================== --}}
    <flux:modal wire:model.self="showEditModal" class="md:w-[640px]" :dismissible="true">
        @if ($this->selectedTicket)
            @php $st = $this->selectedTicket; $stType = $this->typeMeta($st->type); @endphp
            <div class="flex h-full flex-col">
                {{-- header --}}
                <div class="border-b border-zinc-200 pb-4 dark:border-white/[0.08]">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium {{ $stType['classes'] }}">
                            <flux:icon name="{{ $stType['icon'] }}" class="size-3" /> {{ $stType['label'] }}
                        </span>
                        <span class="font-mono text-xs text-zinc-400 dark:text-zinc-500">{{ $st->ticket_number }}</span>
                        <a href="{{ route('admin.it-board.show', $st) }}" wire:navigate class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400">
                            Open full page <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                        </a>
                    </div>
                </div>

                <div class="-mr-2 flex-1 space-y-5 overflow-y-auto py-5 pr-2">
                    {{-- title + description --}}
                    <div class="space-y-3">
                        <flux:field>
                            <flux:label>Title</flux:label>
                            <flux:input wire:model="eTitle" placeholder="Ticket title" />
                            <flux:error name="eTitle" />
                        </flux:field>
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea wire:model="eDescription" rows="4" placeholder="Add more detail..." />
                            <flux:error name="eDescription" />
                        </flux:field>
                    </div>

                    {{-- meta grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>Status</flux:label>
                            <flux:select wire:model="eStatus">
                                @foreach ($this->columns as $statusKey => $col)
                                    <option value="{{ $statusKey }}">{{ $col['label'] }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Priority</flux:label>
                            <flux:select wire:model="ePriority">
                                @foreach (ItTicket::priorities() as $p)
                                    <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Type</flux:label>
                            <flux:select wire:model="eType">
                                @foreach (ItTicket::types() as $t)
                                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Category</flux:label>
                            <flux:select wire:model="eCategoryId">
                                <option value="">No category</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Assignee (in charge)</flux:label>
                            <flux:select wire:model="eAssigneeId">
                                <option value="">Unassigned</option>
                                @foreach ($this->adminUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                        <flux:field>
                            <flux:label>Deadline</flux:label>
                            <flux:input type="date" wire:model="eDueDate" />
                        </flux:field>
                    </div>

                    {{-- live deadline indicator --}}
                    @php $eMeta = $st->deadlineMeta(); @endphp
                    <div class="flex items-center gap-2 rounded-lg border border-zinc-200/70 bg-zinc-50 px-3 py-2 text-xs dark:border-white/[0.06] dark:bg-white/[0.02]">
                        <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-medium {{ $eMeta['classes'] }}">
                            <flux:icon name="{{ $eMeta['icon'] }}" class="size-3" /> {{ $eMeta['label'] }}
                        </span>
                        <span class="text-zinc-400 dark:text-zinc-500">Reported by {{ $st->reporter?->name ?? 'Unknown' }} · {{ $st->created_at->diffForHumans() }}</span>
                    </div>

                    {{-- comments --}}
                    <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-white/[0.08]">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Comments ({{ $st->comments->count() }})</h4>
                        <div class="space-y-3">
                            @forelse ($st->comments as $comment)
                                <div wire:key="comment-{{ $comment->id }}" class="flex items-start gap-2.5">
                                    <flux:avatar size="xs" :name="$comment->user->name" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ $comment->user->name }}</span>
                                                <span class="text-[11px] text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                                            </div>
                                            @if ($comment->user_id === auth()->id())
                                                <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" class="text-zinc-400 hover:text-red-500">
                                                    <flux:icon name="trash" class="size-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-300">{!! nl2br(e($comment->body)) !!}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 dark:text-zinc-500">No comments yet.</p>
                            @endforelse
                        </div>
                        <form wire:submit="addComment" class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:textarea wire:model="commentBody" rows="2" placeholder="Add a comment..." />
                                <flux:error name="commentBody" />
                            </div>
                            <flux:button type="submit" size="sm" variant="filled">
                                <span wire:loading.remove wire:target="addComment">Post</span>
                                <span wire:loading wire:target="addComment">...</span>
                            </flux:button>
                        </form>
                    </div>
                </div>

                {{-- footer actions --}}
                <div class="flex items-center justify-between gap-2 border-t border-zinc-200 pt-4 dark:border-white/[0.08]">
                    <flux:button variant="danger" size="sm" wire:click="deleteTicket" wire:confirm="Delete this ticket permanently?">
                        <div class="flex items-center"><flux:icon name="trash" class="mr-1.5 size-4" /> Delete</div>
                    </flux:button>
                    <div class="flex items-center gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" size="sm">Close</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" size="sm" wire:click="saveTicket">
                            <span wire:loading.remove wire:target="saveTicket">Save changes</span>
                            <span wire:loading wire:target="saveTicket">Saving...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ===================== CREATE MODAL ===================== --}}
    <flux:modal wire:model.self="showCreateModal" class="md:w-[560px]">
        <form wire:submit="createTicket" class="space-y-5">
            <div>
                <flux:heading size="lg">New Ticket</flux:heading>
                <flux:text class="mt-1">Adding to <span class="font-medium">{{ $this->columns[$cStatus]['label'] ?? 'Backlog' }}</span></flux:text>
            </div>

            <flux:field>
                <flux:label>Title *</flux:label>
                <flux:input wire:model="cTitle" placeholder="What needs to be done?" />
                <flux:error name="cTitle" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="cDescription" rows="3" placeholder="Optional details..." />
                <flux:error name="cDescription" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="cType">
                        @foreach (ItTicket::types() as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Priority</flux:label>
                    <flux:select wire:model="cPriority">
                        @foreach (ItTicket::priorities() as $p)
                            <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="cCategoryId">
                        <option value="">No category</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="cStatus">
                        @foreach ($this->columns as $statusKey => $col)
                            <option value="{{ $statusKey }}">{{ $col['label'] }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Assignee</flux:label>
                    <flux:select wire:model="cAssigneeId">
                        <option value="">Unassigned</option>
                        @foreach ($this->adminUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Deadline</flux:label>
                    <flux:input type="date" wire:model="cDueDate" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-white/[0.08]">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="createTicket">Create Ticket</span>
                    <span wire:loading wire:target="createTicket">Creating...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ===================== CATEGORY MANAGER ===================== --}}
    <flux:modal wire:model.self="showCategoryModal" class="md:w-[520px]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Manage Categories</flux:heading>
                <flux:text class="mt-1">Organize tickets by category. Deleting a category leaves its tickets uncategorized.</flux:text>
            </div>

            {{-- list --}}
            <div class="space-y-2">
                @forelse ($this->categories as $category)
                    <div wire:key="cat-{{ $category->id }}" class="rounded-lg border border-zinc-200/80 bg-white px-3 py-2.5 dark:border-white/[0.06] dark:bg-zinc-800/50">
                        @if ($editingCatId === $category->id)
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model="editCatColor" class="size-8 shrink-0 cursor-pointer rounded border border-zinc-200 bg-transparent dark:border-white/10" />
                                <flux:input wire:model="editCatName" size="sm" class="flex-1" />
                                <flux:button size="sm" variant="primary" wire:click="updateCategory">Save</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelEditCategory">Cancel</flux:button>
                            </div>
                            <flux:error name="editCatName" />
                        @else
                            <div class="flex items-center gap-2.5">
                                <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $category->color }}"></span>
                                <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $category->name }}</span>
                                <span class="text-[11px] tabular-nums text-zinc-400">{{ $category->tickets()->count() }} tickets</span>
                                <button type="button" wire:click="startEditCategory({{ $category->id }})" class="text-zinc-400 hover:text-indigo-500" aria-label="Edit category">
                                    <flux:icon name="pencil-square" class="size-4" />
                                </button>
                                <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Delete '{{ $category->name }}'? Tickets will become uncategorized." class="text-zinc-400 hover:text-red-500" aria-label="Delete category">
                                    <flux:icon name="trash" class="size-4" />
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-2 text-sm text-zinc-400 dark:text-zinc-500">No categories yet. Add one below.</p>
                @endforelse
            </div>

            {{-- add --}}
            <div class="border-t border-zinc-200 pt-4 dark:border-white/[0.08]">
                <flux:label class="mb-2">Add category</flux:label>
                <div class="flex items-center gap-2">
                    <input type="color" wire:model="catColor" class="size-9 shrink-0 cursor-pointer rounded border border-zinc-200 bg-transparent dark:border-white/10" />
                    <flux:input wire:model="catName" placeholder="Category name" size="sm" class="flex-1" />
                    <flux:button variant="primary" size="sm" wire:click="addCategory">
                        <div class="flex items-center"><flux:icon name="plus" class="mr-1 size-4" /> Add</div>
                    </flux:button>
                </div>
                <flux:error name="catName" />
            </div>

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-white/[0.08]">
                <flux:modal.close>
                    <flux:button variant="ghost">Done</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>

@assets
<style>
    .kanban-ghost {
        opacity: 1;
        background-color: rgba(99, 102, 241, 0.08);
        border: 1px dashed rgba(99, 102, 241, 0.45) !important;
        border-radius: 0.75rem;
    }
    .kanban-ghost > * { visibility: hidden; }
    .kanban-chosen { cursor: grabbing !important; }
    .kanban-drag {
        transform: rotate(2.5deg);
        box-shadow: 0 16px 32px -8px rgba(0, 0, 0, 0.45);
        cursor: grabbing !important;
    }
    .kanban-col::-webkit-scrollbar { width: 6px; }
    .kanban-col::-webkit-scrollbar-thumb { background: rgba(120, 120, 130, 0.3); border-radius: 9999px; }
    @media (prefers-reduced-motion: reduce) {
        .kanban-card { transition: none !important; }
        .kanban-drag { transform: none; }
    }
</style>
@endassets

@script
<script>
    (() => {
        const board = $wire.$el;

        const buildSortable = (col) => new window.Sortable(col, {
            group: 'it-kanban',
            draggable: '[data-ticket-id]',
            animation: 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            forceFallback: true,
            fallbackOnBody: true,
            ghostClass: 'kanban-ghost',
            chosenClass: 'kanban-chosen',
            dragClass: 'kanban-drag',
            onEnd: (evt) => {
                const ticketId = parseInt(evt.item.dataset.ticketId);
                const newStatus = evt.to.dataset.status;
                const newPosition = evt.newIndex;
                const orderedIds = Array.from(evt.to.querySelectorAll('[data-ticket-id]'))
                    .map(el => parseInt(el.dataset.ticketId));

                $wire.moveTicket(ticketId, newStatus, newPosition, orderedIds);
            },
        });

        const boot = () => {
            board.querySelectorAll('[data-status]').forEach(col => {
                if (col._sortable) {
                    return;
                }
                col._sortable = buildSortable(col);
            });
        };

        boot();
        Livewire.hook('morphed', () => boot());
    })();
</script>
@endscript
