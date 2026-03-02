# IT Ticket Board Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an IT Task Management module with a Kanban board where any authenticated user can submit IT requests, and admins can manage tasks via drag-and-drop columns.

**Architecture:** New `ItTicket` and `ItTicketComment` models with Livewire Volt class-based components. SortableJS handles drag-and-drop on the Kanban board, with Alpine.js dispatching Livewire calls on drop events. The board is admin-only; submission form is available to all authenticated users.

**Tech Stack:** Laravel 12, Livewire Volt (class-based), SortableJS (npm), Alpine.js, Flux UI Free, Tailwind CSS v4

---

## Task 1: Install SortableJS

**Files:**
- Modify: `package.json`

**Step 1: Install the npm package**

Run: `npm install sortablejs`

**Step 2: Verify installation**

Run: `cat package.json | grep sortablejs`
Expected: `"sortablejs": "^1.x.x"` in dependencies

**Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: add sortablejs dependency for kanban board"
```

---

## Task 2: Create Migration for `it_tickets`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_it_tickets_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_it_tickets_table --no-interaction`

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::create('it_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('ticket_number')->unique();
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('type')->default('task'); // bug, feature, task, improvement
        $table->string('priority')->default('medium'); // low, medium, high, urgent
        $table->string('status')->default('backlog'); // backlog, todo, in_progress, review, testing, done
        $table->integer('position')->default(0);
        $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
        $table->date('due_date')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->index(['status', 'position']);
    });
}

public function down(): void
{
    Schema::dropIfExists('it_tickets');
}
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: `DONE` for `create_it_tickets_table`

**Step 4: Commit**

```bash
git add database/migrations/*create_it_tickets_table*
git commit -m "feat: add it_tickets migration"
```

---

## Task 3: Create Migration for `it_ticket_comments`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_it_ticket_comments_table.php`

**Step 1: Generate migration**

Run: `php artisan make:migration create_it_ticket_comments_table --no-interaction`

**Step 2: Write the migration**

```php
public function up(): void
{
    Schema::create('it_ticket_comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('it_ticket_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('body');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('it_ticket_comments');
}
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: `DONE` for `create_it_ticket_comments_table`

**Step 4: Commit**

```bash
git add database/migrations/*create_it_ticket_comments_table*
git commit -m "feat: add it_ticket_comments migration"
```

---

## Task 4: Create ItTicket Model + Factory

**Files:**
- Create: `app/Models/ItTicket.php`
- Create: `database/factories/ItTicketFactory.php`

**Step 1: Generate model with factory**

Run: `php artisan make:model ItTicket --factory --no-interaction`

**Step 2: Write the ItTicket model**

Reference pattern: `app/Models/Ticket.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ItTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'position',
        'reporter_id',
        'assignee_id',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    // --- Relationships ---

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItTicketComment::class)->orderBy('created_at', 'asc');
    }

    // --- Helper Methods ---

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'IT-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    public static function statuses(): array
    {
        return ['backlog', 'todo', 'in_progress', 'review', 'testing', 'done'];
    }

    public static function types(): array
    {
        return ['bug', 'feature', 'task', 'improvement'];
    }

    public static function priorities(): array
    {
        return ['low', 'medium', 'high', 'urgent'];
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'testing' => 'Testing',
            'done' => 'Done',
            default => ucfirst($this->status),
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'bug' => 'Bug',
            'feature' => 'Feature',
            'task' => 'Task',
            'improvement' => 'Improvement',
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            'bug' => 'red',
            'feature' => 'green',
            'task' => 'blue',
            'improvement' => 'yellow',
            default => 'gray',
        };
    }

    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'low' => 'gray',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'backlog' => 'gray',
            'todo' => 'blue',
            'in_progress' => 'yellow',
            'review' => 'purple',
            'testing' => 'orange',
            'done' => 'green',
            default => 'gray',
        };
    }

    public function markDone(): void
    {
        $this->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'done';
    }

    public function scopeSearch($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('reporter', fn ($r) => $r->where('name', 'like', "%{$search}%"))
                ->orWhereHas('assignee', fn ($a) => $a->where('name', 'like', "%{$search}%"));
        });
    }
}
```

**Step 3: Write the ItTicketFactory**

```php
<?php

namespace Database\Factories;

use App\Models\ItTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItTicketFactory extends Factory
{
    protected $model = ItTicket::class;

    public function definition(): array
    {
        return [
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(ItTicket::types()),
            'priority' => fake()->randomElement(ItTicket::priorities()),
            'status' => 'backlog',
            'position' => 0,
            'reporter_id' => User::factory(),
            'assignee_id' => null,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }

    public function bug(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'bug']);
    }

    public function feature(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'feature']);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => ['priority' => 'urgent']);
    }

    public function assigned(User $user): static
    {
        return $this->state(fn (array $attributes) => ['assignee_id' => $user->id]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }
}
```

**Step 4: Commit**

```bash
git add app/Models/ItTicket.php database/factories/ItTicketFactory.php
git commit -m "feat: add ItTicket model with factory"
```

---

## Task 5: Create ItTicketComment Model

**Files:**
- Create: `app/Models/ItTicketComment.php`

**Step 1: Generate model**

Run: `php artisan make:model ItTicketComment --no-interaction`

**Step 2: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItTicketComment extends Model
{
    protected $fillable = [
        'it_ticket_id',
        'user_id',
        'body',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ItTicket::class, 'it_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/ItTicketComment.php
git commit -m "feat: add ItTicketComment model"
```

---

## Task 6: Add Routes

**Files:**
- Modify: `routes/web.php` (lines ~419-425 for admin, lines ~47-56 for auth)

**Step 1: Add admin IT Board routes**

Inside the `Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {` block, after the Customer Service Tickets routes (line 419), add:

```php
    // IT Ticket Board
    Volt::route('it-board', 'admin.it-board.index')->name('admin.it-board.index');
    Volt::route('it-board/create', 'admin.it-board.create')->name('admin.it-board.create');
    Volt::route('it-board/{itTicket}', 'admin.it-board.show')->name('admin.it-board.show');
```

**Step 2: Add authenticated IT request route**

Inside the `Route::middleware(['auth'])->group(function () {` block (line 47), add:

```php
    // IT Request - accessible by all authenticated users
    Volt::route('it-request', 'it-request.create')->name('it-request.create');
    Volt::route('it-request/my', 'it-request.index')->name('it-request.index');
```

**Step 3: Verify routes**

Run: `php artisan route:list --path=it`
Expected: Routes for `admin/it-board`, `admin/it-board/create`, `admin/it-board/{itTicket}`, `it-request`, `it-request/my`

**Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: add IT board and IT request routes"
```

---

## Task 7: Add Sidebar Navigation

**Files:**
- Modify: `resources/views/components/layouts/app/sidebar.blade.php`

**Step 1: Add `itBoard` to sectionRoutes Alpine data**

In the `sectionRoutes` object (line 13-39), add:

```javascript
'itBoard': ['admin.it-board.*'],
```

**Step 2: Add IT Board nav group in admin sidebar**

After the Customer Service `</flux:navlist.group>` (after line 203), add:

```blade
                <flux:navlist.group
                    expandable
                    :heading="__('IT Board')"
                    data-section='itBoard' x-init="if (!isExpanded('itBoard')) { $nextTick(() => { const btn = $el.querySelector('button'); if (btn && $el.hasAttribute('open')) btn.click(); }); }"
                    @click="saveState('itBoard', $event)"
                >
                    <flux:navlist.item icon="clipboard-document-list" :href="route('admin.it-board.index')" :current="request()->routeIs('admin.it-board.*')" wire:navigate>{{ __('Kanban Board') }}</flux:navlist.item>
                </flux:navlist.group>
```

**Step 3: Add IT Request link to non-admin user sidebars**

Find the student sidebar section and add an "IT Request" item. Also add for teacher, live-host, and class-admin sidebar sections. Each gets:

```blade
<flux:navlist.item icon="clipboard-document-list" :href="route('it-request.create')" :current="request()->routeIs('it-request.*')" wire:navigate>{{ __('IT Request') }}</flux:navlist.item>
```

**Step 4: Commit**

```bash
git add resources/views/components/layouts/app/sidebar.blade.php
git commit -m "feat: add IT Board sidebar navigation for all roles"
```

---

## Task 8: Create IT Request Submission Form (All Users)

**Files:**
- Create: `resources/views/livewire/it-request/create.blade.php`

**Step 1: Create directory**

Run: `mkdir -p resources/views/livewire/it-request`

**Step 2: Write the Volt component**

Reference pattern: `resources/views/livewire/admin/customer-service/tickets-create.blade.php`

```php
<?php

use App\Models\ItTicket;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';
    public string $description = '';
    public string $type = 'task';
    public string $priority = 'medium';

    public bool $submitted = false;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function submit(): void
    {
        $this->validate([
            'title' => 'required|min:5|max:255',
            'description' => 'nullable|max:5000',
            'type' => 'required|in:' . implode(',', ItTicket::types()),
            'priority' => 'required|in:' . implode(',', ItTicket::priorities()),
        ]);

        ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => 'backlog',
            'position' => ItTicket::where('status', 'backlog')->max('position') + 1,
            'reporter_id' => auth()->id(),
        ]);

        $this->submitted = true;
    }

    public function submitAnother(): void
    {
        $this->reset(['title', 'description', 'type', 'priority', 'submitted']);
        $this->type = 'task';
        $this->priority = 'medium';
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">Submit IT Request</flux:heading>
        <flux:text class="mt-2">Report a bug, request a feature, or submit a task for the IT team</flux:text>
    </div>

    @if($submitted)
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-8 text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <flux:icon name="check-circle" class="w-8 h-8 text-green-600" />
            </div>
            <flux:heading size="lg">Request Submitted!</flux:heading>
            <flux:text class="mt-2">Your IT request has been added to the backlog. The team will review it shortly.</flux:text>
            <div class="mt-6 flex items-center justify-center gap-3">
                <flux:button variant="primary" wire:click="submitAnother">Submit Another</flux:button>
                <flux:button variant="ghost" :href="route('it-request.index')" wire:navigate>View My Requests</flux:button>
            </div>
        </div>
    @else
        <form wire:submit="submit">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-700">
                    <div class="flex items-center gap-2 mb-1">
                        <flux:icon name="clipboard-document-list" class="w-5 h-5 text-gray-400" />
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Request Details</h3>
                    </div>
                </div>
                <div class="px-6 py-5 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <flux:label for="type" class="mb-1.5">Type *</flux:label>
                            <flux:select wire:model="type" id="type">
                                <option value="bug">Bug - Something is broken</option>
                                <option value="feature">Feature - New functionality</option>
                                <option value="task">Task - General work item</option>
                                <option value="improvement">Improvement - Enhance existing feature</option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:label for="priority" class="mb-1.5">Priority *</flux:label>
                            <flux:select wire:model="priority" id="priority">
                                <option value="low">Low - No rush</option>
                                <option value="medium">Medium - Normal priority</option>
                                <option value="high">High - Important</option>
                                <option value="urgent">Urgent - Needs immediate attention</option>
                            </flux:select>
                        </div>
                    </div>

                    <div>
                        <flux:label for="title" class="mb-1.5">Title *</flux:label>
                        <flux:input wire:model="title" id="title" placeholder="Brief description of the request" />
                        @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <flux:label for="description" class="mb-1.5">Description</flux:label>
                        <flux:textarea wire:model="description" id="description" rows="5" placeholder="Provide detailed information about your request..." />
                        @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <flux:button variant="primary" type="submit">
                        <span wire:loading.remove>Submit Request</span>
                        <span wire:loading>Submitting...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    @endif
</div>
```

**Step 3: Commit**

```bash
git add resources/views/livewire/it-request/create.blade.php
git commit -m "feat: add IT request submission form for all users"
```

---

## Task 9: Create "My IT Requests" Page (All Users)

**Files:**
- Create: `resources/views/livewire/it-request/index.blade.php`

**Step 1: Write the Volt component**

This page shows the authenticated user's own submitted IT requests with their current statuses.

```php
<?php

use App\Models\ItTicket;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getTicketsProperty()
    {
        return ItTicket::where('reporter_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">My IT Requests</flux:heading>
            <flux:text class="mt-2">Track the status of your submitted IT requests</flux:text>
        </div>
        <flux:button variant="primary" :href="route('it-request.create')" wire:navigate>
            New Request
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        @forelse($this->tickets as $ticket)
            <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700 last:border-b-0">
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $ticket->title }}</p>
                            <flux:badge size="sm" :color="$ticket->getTypeColor()">{{ $ticket->getTypeLabel() }}</flux:badge>
                            <flux:badge size="sm" :color="$ticket->getPriorityColor()">{{ ucfirst($ticket->priority) }}</flux:badge>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-xs text-gray-500">{{ $ticket->ticket_number }}</span>
                            <span class="text-xs text-gray-500">{{ $ticket->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <flux:badge size="sm" :color="$ticket->getStatusColor()">{{ $ticket->getStatusLabel() }}</flux:badge>
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <flux:icon name="inbox" class="w-10 h-10 mx-auto text-gray-300 mb-3" />
                <flux:text>No IT requests submitted yet.</flux:text>
                <flux:button variant="primary" :href="route('it-request.create')" wire:navigate class="mt-4">
                    Submit Your First Request
                </flux:button>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->tickets->links() }}
    </div>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/it-request/index.blade.php
git commit -m "feat: add My IT Requests listing page"
```

---

## Task 10: Create Admin Kanban Board Page

**Files:**
- Create: `resources/views/livewire/admin/it-board/index.blade.php`

This is the main Kanban board — the most complex component. It uses SortableJS via Alpine.js for drag-and-drop.

**Step 1: Create directory**

Run: `mkdir -p resources/views/livewire/admin/it-board`

**Step 2: Write the Volt component**

```php
<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public string $search = '';
    public string $typeFilter = '';
    public string $priorityFilter = '';
    public string $assigneeFilter = '';

    // Quick-create modal
    public bool $showCreateModal = false;
    public string $newTitle = '';
    public string $newType = 'task';
    public string $newPriority = 'medium';
    public string $createInStatus = 'backlog';

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getColumnsProperty(): array
    {
        return [
            'backlog' => ['label' => 'Backlog', 'color' => 'gray'],
            'todo' => ['label' => 'To Do', 'color' => 'blue'],
            'in_progress' => ['label' => 'In Progress', 'color' => 'yellow'],
            'review' => ['label' => 'Review', 'color' => 'purple'],
            'testing' => ['label' => 'Testing', 'color' => 'orange'],
            'done' => ['label' => 'Done', 'color' => 'green'],
        ];
    }

    public function getTicketsProperty()
    {
        $query = ItTicket::with(['reporter', 'assignee']);

        if ($this->search) {
            $query->search($this->search);
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }
        if ($this->assigneeFilter) {
            $query->where('assignee_id', $this->assigneeFilter);
        }

        return $query->orderBy('position')->get()->groupBy('status');
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function updateTicketStatus(int $ticketId, string $newStatus, int $newPosition): void
    {
        $ticket = ItTicket::findOrFail($ticketId);
        $ticket->update([
            'status' => $newStatus,
            'position' => $newPosition,
            'completed_at' => $newStatus === 'done' ? now() : ($ticket->status === 'done' && $newStatus !== 'done' ? null : $ticket->completed_at),
        ]);
    }

    public function reorderColumn(string $status, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $id) {
            ItTicket::where('id', $id)->update(['position' => $position]);
        }
    }

    public function openQuickCreate(string $status = 'backlog'): void
    {
        $this->createInStatus = $status;
        $this->reset(['newTitle', 'newType', 'newPriority']);
        $this->newType = 'task';
        $this->newPriority = 'medium';
        $this->showCreateModal = true;
    }

    public function quickCreate(): void
    {
        $this->validate([
            'newTitle' => 'required|min:3|max:255',
            'newType' => 'required|in:' . implode(',', ItTicket::types()),
            'newPriority' => 'required|in:' . implode(',', ItTicket::priorities()),
        ]);

        ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->newTitle,
            'type' => $this->newType,
            'priority' => $this->newPriority,
            'status' => $this->createInStatus,
            'position' => ItTicket::where('status', $this->createInStatus)->max('position') + 1,
            'reporter_id' => auth()->id(),
        ]);

        $this->showCreateModal = false;
        $this->reset(['newTitle']);
    }

    public function deleteTicket(int $ticketId): void
    {
        ItTicket::findOrFail($ticketId)->delete();
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">IT Board</flux:heading>
            <flux:text class="mt-2">Manage IT tasks and development requests</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="primary" :href="route('admin.it-board.create')" wire:navigate>
                <div class="flex items-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    New Ticket
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-64">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search tickets..." icon="magnifying-glass" size="sm" />
        </div>
        <flux:select wire:model.live="typeFilter" size="sm">
            <option value="">All Types</option>
            @foreach(ItTicket::types() as $type)
                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="priorityFilter" size="sm">
            <option value="">All Priorities</option>
            @foreach(ItTicket::priorities() as $priority)
                <option value="{{ $priority }}">{{ ucfirst($priority) }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="assigneeFilter" size="sm">
            <option value="">All Assignees</option>
            @foreach($this->adminUsers as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </flux:select>
    </div>

    <!-- Kanban Board -->
    <div class="flex gap-4 overflow-x-auto pb-4" x-data="kanbanBoard()" x-init="initBoard()">
        @foreach($this->columns as $status => $column)
            @php $columnTickets = $this->tickets[$status] ?? collect(); @endphp
            <div class="flex-shrink-0 w-72">
                <!-- Column Header -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $column['label'] }}</h3>
                        <span class="text-xs text-gray-400 bg-gray-100 dark:bg-zinc-700 px-1.5 py-0.5 rounded-full">{{ $columnTickets->count() }}</span>
                    </div>
                    <button wire:click="openQuickCreate('{{ $status }}')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <flux:icon name="plus" class="w-4 h-4" />
                    </button>
                </div>

                <!-- Column Body (drop zone) -->
                <div
                    class="space-y-2 min-h-[200px] p-2 bg-gray-50 dark:bg-zinc-800/50 rounded-lg border border-gray-200 dark:border-zinc-700"
                    data-status="{{ $status }}"
                    x-ref="column_{{ $status }}"
                >
                    @foreach($columnTickets as $ticket)
                        <div
                            wire:key="ticket-{{ $ticket->id }}"
                            data-ticket-id="{{ $ticket->id }}"
                            class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-3 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-shadow"
                        >
                            <!-- Card Header: Type + Priority -->
                            <div class="flex items-center justify-between mb-2">
                                <flux:badge size="sm" :color="$ticket->getTypeColor()">{{ $ticket->getTypeLabel() }}</flux:badge>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-{{ $ticket->getPriorityColor() }}-500" title="{{ ucfirst($ticket->priority) }} priority"></div>
                                    <span class="text-[10px] text-gray-400">{{ $ticket->ticket_number }}</span>
                                </div>
                            </div>

                            <!-- Title -->
                            <a href="{{ route('admin.it-board.show', $ticket) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 line-clamp-2 block" wire:navigate>
                                {{ $ticket->title }}
                            </a>

                            <!-- Footer: Assignee + Due Date -->
                            <div class="flex items-center justify-between mt-3">
                                <div>
                                    @if($ticket->assignee)
                                        <flux:avatar size="xs" :name="$ticket->assignee->name" />
                                    @else
                                        <span class="text-[10px] text-gray-400">Unassigned</span>
                                    @endif
                                </div>
                                @if($ticket->due_date)
                                    <span class="text-[10px] {{ $ticket->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        <flux:icon name="calendar" class="w-3 h-3 inline" />
                                        {{ $ticket->due_date->format('M j') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- Quick Create Modal -->
    <flux:modal wire:model="showCreateModal">
        <div class="p-6">
            <flux:heading size="lg">Quick Create Ticket</flux:heading>
            <flux:text class="mt-1">Adding to: {{ $this->columns[$createInStatus]['label'] ?? 'Backlog' }}</flux:text>

            <form wire:submit="quickCreate" class="mt-4 space-y-4">
                <div>
                    <flux:label for="newTitle" class="mb-1.5">Title *</flux:label>
                    <flux:input wire:model="newTitle" id="newTitle" placeholder="What needs to be done?" />
                    @error('newTitle') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:label for="newType" class="mb-1.5">Type</flux:label>
                        <flux:select wire:model="newType" id="newType">
                            <option value="bug">Bug</option>
                            <option value="feature">Feature</option>
                            <option value="task">Task</option>
                            <option value="improvement">Improvement</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="newPriority" class="mb-1.5">Priority</flux:label>
                        <flux:select wire:model="newPriority" id="newPriority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" @click="$wire.showCreateModal = false">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Create</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

@script
<script>
    function kanbanBoard() {
        return {
            sortables: [],
            initBoard() {
                this.$nextTick(() => {
                    const columns = this.$el.querySelectorAll('[data-status]');
                    columns.forEach(column => {
                        const sortable = new Sortable(column, {
                            group: 'kanban',
                            animation: 150,
                            ghostClass: 'opacity-50',
                            dragClass: 'rotate-2',
                            handle: '.cursor-grab',
                            onEnd: (evt) => {
                                const ticketId = parseInt(evt.item.dataset.ticketId);
                                const newStatus = evt.to.dataset.status;
                                const newPosition = evt.newIndex;

                                // Get all ticket IDs in the target column
                                const orderedIds = Array.from(evt.to.children).map(el => parseInt(el.dataset.ticketId));

                                this.$wire.updateTicketStatus(ticketId, newStatus, newPosition);
                                this.$wire.reorderColumn(newStatus, orderedIds);
                            },
                        });
                        this.sortables.push(sortable);
                    });
                });
            }
        };
    }
</script>
@endscript
```

**Step 3: Import SortableJS in app.js**

Modify `resources/js/app.js` to make SortableJS globally available:

```javascript
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

**Step 4: Build assets**

Run: `npm run build`

**Step 5: Commit**

```bash
git add resources/views/livewire/admin/it-board/index.blade.php resources/js/app.js
git commit -m "feat: add IT Board kanban page with drag-and-drop"
```

---

## Task 11: Create Admin Ticket Create Page

**Files:**
- Create: `resources/views/livewire/admin/it-board/create.blade.php`

**Step 1: Write the Volt component**

This is a full-featured create form for admins (includes assignment and due date).

```php
<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public string $title = '';
    public string $description = '';
    public string $type = 'task';
    public string $priority = 'medium';
    public string $status = 'backlog';
    public ?int $assigneeId = null;
    public ?string $dueDate = null;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate([
            'title' => 'required|min:3|max:255',
            'description' => 'nullable|max:5000',
            'type' => 'required|in:' . implode(',', ItTicket::types()),
            'priority' => 'required|in:' . implode(',', ItTicket::priorities()),
            'status' => 'required|in:' . implode(',', ItTicket::statuses()),
            'assigneeId' => 'nullable|exists:users,id',
            'dueDate' => 'nullable|date',
        ]);

        $ticket = ItTicket::create([
            'ticket_number' => ItTicket::generateTicketNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'position' => ItTicket::where('status', $this->status)->max('position') + 1,
            'reporter_id' => auth()->id(),
            'assignee_id' => $this->assigneeId,
            'due_date' => $this->dueDate,
        ]);

        session()->flash('success', 'IT Ticket created successfully.');
        $this->redirect(route('admin.it-board.show', $ticket), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Create IT Ticket</flux:heading>
            <flux:text class="mt-2">Create a new task for the IT board</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Board
            </div>
        </flux:button>
    </div>

    <form wire:submit="create">
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-700">
                <div class="flex items-center gap-2 mb-1">
                    <flux:icon name="clipboard-document-list" class="w-5 h-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Ticket Details</h3>
                </div>
            </div>
            <div class="px-6 py-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <flux:label for="type" class="mb-1.5">Type *</flux:label>
                        <flux:select wire:model="type" id="type">
                            <option value="bug">Bug</option>
                            <option value="feature">Feature</option>
                            <option value="task">Task</option>
                            <option value="improvement">Improvement</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="priority" class="mb-1.5">Priority *</flux:label>
                        <flux:select wire:model="priority" id="priority">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="status" class="mb-1.5">Status</flux:label>
                        <flux:select wire:model="status" id="status">
                            @foreach(ItTicket::statuses() as $s)
                                <option value="{{ $s }}">{{ (new ItTicket(['status' => $s]))->getStatusLabel() }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <flux:label for="assigneeId" class="mb-1.5">Assign To</flux:label>
                        <flux:select wire:model="assigneeId" id="assigneeId">
                            <option value="">Unassigned</option>
                            @foreach($this->adminUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div>
                        <flux:label for="dueDate" class="mb-1.5">Due Date</flux:label>
                        <flux:input wire:model="dueDate" id="dueDate" type="date" />
                    </div>
                </div>

                <div>
                    <flux:label for="title" class="mb-1.5">Title *</flux:label>
                    <flux:input wire:model="title" id="title" placeholder="Brief description of the task" />
                    @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <flux:label for="description" class="mb-1.5">Description</flux:label>
                    <flux:textarea wire:model="description" id="description" rows="5" placeholder="Detailed information about the task..." />
                    @error('description') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>Cancel</flux:button>
                <flux:button variant="primary" type="submit">
                    <span wire:loading.remove>Create Ticket</span>
                    <span wire:loading>Creating...</span>
                </flux:button>
            </div>
        </div>
    </form>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/admin/it-board/create.blade.php
git commit -m "feat: add admin IT ticket create page"
```

---

## Task 12: Create Admin Ticket Detail/Show Page

**Files:**
- Create: `resources/views/livewire/admin/it-board/show.blade.php`

**Step 1: Write the Volt component**

This page shows ticket details and a comments thread.

```php
<?php

use App\Models\ItTicket;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public ItTicket $itTicket;
    public string $commentBody = '';

    // Editable fields
    public string $status;
    public string $type;
    public string $priority;
    public ?int $assigneeId;
    public ?string $dueDate;

    public function layout()
    {
        return 'components.layouts.app.sidebar';
    }

    public function mount(ItTicket $itTicket): void
    {
        $this->itTicket = $itTicket->load(['reporter', 'assignee', 'comments.user']);
        $this->status = $itTicket->status;
        $this->type = $itTicket->type;
        $this->priority = $itTicket->priority;
        $this->assigneeId = $itTicket->assignee_id;
        $this->dueDate = $itTicket->due_date?->format('Y-m-d');
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->orderBy('name')->get();
    }

    public function updateField(string $field): void
    {
        $data = [$field => $this->{$field}];

        if ($field === 'status' && $this->status === 'done') {
            $data['completed_at'] = now();
        } elseif ($field === 'status' && $this->itTicket->status === 'done' && $this->status !== 'done') {
            $data['completed_at'] = null;
        }

        if ($field === 'assigneeId') {
            $data = ['assignee_id' => $this->assigneeId];
        }

        if ($field === 'dueDate') {
            $data = ['due_date' => $this->dueDate];
        }

        $this->itTicket->update($data);
        $this->itTicket->refresh();
    }

    public function addComment(): void
    {
        $this->validate([
            'commentBody' => 'required|min:1|max:5000',
        ]);

        $this->itTicket->comments()->create([
            'user_id' => auth()->id(),
            'body' => $this->commentBody,
        ]);

        $this->commentBody = '';
        $this->itTicket->load('comments.user');
    }

    public function deleteComment(int $commentId): void
    {
        $this->itTicket->comments()->where('id', $commentId)->delete();
        $this->itTicket->load('comments.user');
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $itTicket->title }}</flux:heading>
                <flux:badge :color="$itTicket->getTypeColor()">{{ $itTicket->getTypeLabel() }}</flux:badge>
            </div>
            <flux:text class="mt-1">{{ $itTicket->ticket_number }} &bull; Reported by {{ $itTicket->reporter->name }} &bull; {{ $itTicket->created_at->diffForHumans() }}</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('admin.it-board.index')" wire:navigate>
            <div class="flex items-center">
                <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                Back to Board
            </div>
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content (Left 2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Description -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Description</h3>
                @if($itTicket->description)
                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-400">
                        {!! nl2br(e($itTicket->description)) !!}
                    </div>
                @else
                    <flux:text class="text-gray-400 italic">No description provided.</flux:text>
                @endif
            </div>

            <!-- Comments -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Comments ({{ $itTicket->comments->count() }})
                    </h3>
                </div>

                <!-- Comment List -->
                <div class="divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse($itTicket->comments as $comment)
                        <div class="px-6 py-4" wire:key="comment-{{ $comment->id }}">
                            <div class="flex items-start gap-3">
                                <flux:avatar size="sm" :name="$comment->user->name" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $comment->user->name }}</span>
                                            <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        @if($comment->user_id === auth()->id())
                                            <button wire:click="deleteComment({{ $comment->id }})" wire:confirm="Delete this comment?" class="text-gray-400 hover:text-red-500">
                                                <flux:icon name="trash" class="w-3.5 h-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{!! nl2br(e($comment->body)) !!}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center">
                            <flux:text class="text-gray-400">No comments yet.</flux:text>
                        </div>
                    @endforelse
                </div>

                <!-- Add Comment Form -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-900/50 rounded-b-lg">
                    <form wire:submit="addComment" class="flex gap-3">
                        <div class="flex-1">
                            <flux:textarea wire:model="commentBody" rows="2" placeholder="Add a comment..." />
                            @error('commentBody') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                        <flux:button variant="primary" type="submit" class="self-end">
                            <span wire:loading.remove>Comment</span>
                            <span wire:loading>Sending...</span>
                        </flux:button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar (Right 1/3) -->
        <div class="space-y-4">
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-5 space-y-4">
                <div>
                    <flux:label class="mb-1.5">Status</flux:label>
                    <flux:select wire:model="status" wire:change="updateField('status')">
                        @foreach(ItTicket::statuses() as $s)
                            <option value="{{ $s }}">{{ (new ItTicket(['status' => $s]))->getStatusLabel() }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Type</flux:label>
                    <flux:select wire:model="type" wire:change="updateField('type')">
                        @foreach(ItTicket::types() as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Priority</flux:label>
                    <flux:select wire:model="priority" wire:change="updateField('priority')">
                        @foreach(ItTicket::priorities() as $p)
                            <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Assignee</flux:label>
                    <flux:select wire:model="assigneeId" wire:change="updateField('assigneeId')">
                        <option value="">Unassigned</option>
                        @foreach($this->adminUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:label class="mb-1.5">Due Date</flux:label>
                    <flux:input wire:model="dueDate" wire:change="updateField('dueDate')" type="date" />
                </div>

                @if($itTicket->completed_at)
                    <div class="pt-2 border-t border-gray-200 dark:border-zinc-700">
                        <flux:text class="text-xs text-green-600">
                            Completed {{ $itTicket->completed_at->diffForHumans() }}
                        </flux:text>
                    </div>
                @endif
            </div>

            <!-- Danger Zone -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg border border-red-200 dark:border-red-900/50 p-5">
                <h4 class="text-sm font-semibold text-red-600 mb-3">Danger Zone</h4>
                <flux:button variant="danger" size="sm" wire:click="$parent.deleteTicket({{ $itTicket->id }})" wire:confirm="Are you sure you want to delete this ticket?">
                    Delete Ticket
                </flux:button>
            </div>
        </div>
    </div>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/admin/it-board/show.blade.php
git commit -m "feat: add IT ticket detail page with comments"
```

---

## Task 13: Write Tests

**Files:**
- Create: `tests/Feature/ItTicketTest.php`

**Step 1: Generate test file**

Run: `php artisan make:test ItTicketTest --pest --no-interaction`

**Step 2: Write comprehensive tests**

```php
<?php

use App\Models\ItTicket;
use App\Models\ItTicketComment;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->teacher = User::factory()->create(['role' => 'teacher']);
});

// --- Model Tests ---

it('generates unique ticket numbers', function () {
    $number1 = ItTicket::generateTicketNumber();
    $number2 = ItTicket::generateTicketNumber();

    expect($number1)->toStartWith('IT-');
    expect($number1)->not->toBe($number2);
});

it('detects overdue tickets', function () {
    $overdue = ItTicket::factory()->create([
        'due_date' => now()->subDay(),
        'status' => 'in_progress',
    ]);

    $notOverdue = ItTicket::factory()->create([
        'due_date' => now()->addDay(),
        'status' => 'in_progress',
    ]);

    $done = ItTicket::factory()->create([
        'due_date' => now()->subDay(),
        'status' => 'done',
    ]);

    expect($overdue->isOverdue())->toBeTrue();
    expect($notOverdue->isOverdue())->toBeFalse();
    expect($done->isOverdue())->toBeFalse();
});

it('returns correct type and priority colors', function () {
    $ticket = new ItTicket(['type' => 'bug', 'priority' => 'urgent']);

    expect($ticket->getTypeColor())->toBe('red');
    expect($ticket->getPriorityColor())->toBe('red');
});

// --- IT Request Submission (All Users) ---

it('allows any authenticated user to access the IT request form', function () {
    $this->actingAs($this->student)
        ->get(route('it-request.create'))
        ->assertSuccessful();
});

it('allows students to submit IT requests', function () {
    Volt::test('it-request.create')
        ->actingAs($this->student)
        ->set('title', 'Login page is broken')
        ->set('description', 'Cannot login after update')
        ->set('type', 'bug')
        ->set('priority', 'high')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(ItTicket::where('title', 'Login page is broken')->exists())->toBeTrue();
    expect(ItTicket::first()->reporter_id)->toBe($this->student->id);
    expect(ItTicket::first()->status)->toBe('backlog');
});

it('validates required fields on IT request form', function () {
    Volt::test('it-request.create')
        ->actingAs($this->student)
        ->set('title', '')
        ->call('submit')
        ->assertHasErrors(['title']);
});

it('allows teachers to submit IT requests', function () {
    Volt::test('it-request.create')
        ->actingAs($this->teacher)
        ->set('title', 'Need grade export feature')
        ->set('type', 'feature')
        ->set('priority', 'medium')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ItTicket::where('title', 'Need grade export feature')->exists())->toBeTrue();
});

// --- My IT Requests Page ---

it('shows only the users own requests', function () {
    ItTicket::factory()->create(['reporter_id' => $this->student->id, 'title' => 'My Bug']);
    ItTicket::factory()->create(['reporter_id' => $this->admin->id, 'title' => 'Admin Bug']);

    $this->actingAs($this->student)
        ->get(route('it-request.index'))
        ->assertSee('My Bug')
        ->assertDontSee('Admin Bug');
});

// --- Admin Board Access ---

it('allows admin to access the IT board', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.it-board.index'))
        ->assertSuccessful();
});

it('denies non-admin users access to IT board', function () {
    $this->actingAs($this->student)
        ->get(route('admin.it-board.index'))
        ->assertForbidden();
});

// --- Admin Kanban Operations ---

it('allows admin to create tickets via quick create', function () {
    Volt::test('admin.it-board.index')
        ->actingAs($this->admin)
        ->call('openQuickCreate', 'todo')
        ->assertSet('showCreateModal', true)
        ->assertSet('createInStatus', 'todo')
        ->set('newTitle', 'Fix API endpoint')
        ->set('newType', 'bug')
        ->set('newPriority', 'high')
        ->call('quickCreate')
        ->assertSet('showCreateModal', false);

    $ticket = ItTicket::where('title', 'Fix API endpoint')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->status)->toBe('todo');
    expect($ticket->type)->toBe('bug');
});

it('allows admin to update ticket status via drag', function () {
    $ticket = ItTicket::factory()->create(['status' => 'backlog', 'position' => 0]);

    Volt::test('admin.it-board.index')
        ->actingAs($this->admin)
        ->call('updateTicketStatus', $ticket->id, 'in_progress', 0);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('sets completed_at when moving to done', function () {
    $ticket = ItTicket::factory()->create(['status' => 'testing']);

    Volt::test('admin.it-board.index')
        ->actingAs($this->admin)
        ->call('updateTicketStatus', $ticket->id, 'done', 0);

    expect($ticket->fresh()->completed_at)->not->toBeNull();
});

it('clears completed_at when moving out of done', function () {
    $ticket = ItTicket::factory()->done()->create();

    Volt::test('admin.it-board.index')
        ->actingAs($this->admin)
        ->call('updateTicketStatus', $ticket->id, 'review', 0);

    expect($ticket->fresh()->completed_at)->toBeNull();
});

// --- Ticket Detail Page ---

it('allows admin to view ticket details', function () {
    $ticket = ItTicket::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.it-board.show', $ticket))
        ->assertSuccessful()
        ->assertSee($ticket->title);
});

it('allows admin to add comments', function () {
    $ticket = ItTicket::factory()->create();

    Volt::test('admin.it-board.show', ['itTicket' => $ticket])
        ->actingAs($this->admin)
        ->set('commentBody', 'This is a test comment')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($ticket->comments()->count())->toBe(1);
    expect($ticket->comments->first()->body)->toBe('This is a test comment');
});

it('allows admin to update ticket fields from detail page', function () {
    $ticket = ItTicket::factory()->create(['priority' => 'low']);

    Volt::test('admin.it-board.show', ['itTicket' => $ticket])
        ->actingAs($this->admin)
        ->set('priority', 'urgent')
        ->call('updateField', 'priority');

    expect($ticket->fresh()->priority)->toBe('urgent');
});

// --- Admin Ticket Create Page ---

it('allows admin to create tickets with full details', function () {
    $assignee = User::factory()->create(['role' => 'admin']);

    Volt::test('admin.it-board.create')
        ->actingAs($this->admin)
        ->set('title', 'Implement dark mode')
        ->set('description', 'Add dark mode support to all pages')
        ->set('type', 'feature')
        ->set('priority', 'medium')
        ->set('status', 'todo')
        ->set('assigneeId', $assignee->id)
        ->set('dueDate', '2026-04-01')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = ItTicket::where('title', 'Implement dark mode')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->assignee_id)->toBe($assignee->id);
    expect($ticket->due_date->format('Y-m-d'))->toBe('2026-04-01');
});
```

**Step 3: Run tests**

Run: `php artisan test --compact tests/Feature/ItTicketTest.php`
Expected: All tests PASS

**Step 4: Commit**

```bash
git add tests/Feature/ItTicketTest.php
git commit -m "test: add comprehensive IT ticket board tests"
```

---

## Task 14: Run Full Test Suite + Format Code

**Step 1: Format code with Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests PASS

**Step 3: Build frontend assets**

Run: `npm run build`

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: format code and finalize IT ticket board module"
```

---

## Summary of Files Created/Modified

### New Files (10):
1. `database/migrations/XXXX_create_it_tickets_table.php`
2. `database/migrations/XXXX_create_it_ticket_comments_table.php`
3. `app/Models/ItTicket.php`
4. `app/Models/ItTicketComment.php`
5. `database/factories/ItTicketFactory.php`
6. `resources/views/livewire/it-request/create.blade.php`
7. `resources/views/livewire/it-request/index.blade.php`
8. `resources/views/livewire/admin/it-board/index.blade.php`
9. `resources/views/livewire/admin/it-board/create.blade.php`
10. `resources/views/livewire/admin/it-board/show.blade.php`
11. `tests/Feature/ItTicketTest.php`

### Modified Files (4):
1. `package.json` — add `sortablejs`
2. `routes/web.php` — add IT board + IT request routes
3. `resources/views/components/layouts/app/sidebar.blade.php` — add navigation
4. `resources/js/app.js` — import SortableJS globally
