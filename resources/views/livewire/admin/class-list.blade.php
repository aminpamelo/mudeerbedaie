<?php

use App\Models\ClassModel;
use App\Models\ClassCategory;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $courseFilter = '';
    public $statusFilter = 'active';
    public $classTypeFilter = '';
    public $categoryFilter = '';
    public $viewMode = 'grouped'; // 'list', 'grouped', or 'pic'
    public $perPage = 10;

    // Category assignment modal
    public $showCategoryModal = false;
    public $editingClassId = null;
    public $selectedCategoryIds = [];

    // Category management modal
    public $showCategoryManageModal = false;
    public $editingCategoryId = null;
    public $categoryName = '';
    public $categoryColor = '#6366f1';
    public $categoryDescription = '';

    // PIC assignment modal
    public $showPicModal = false;
    public $editingPicClassId = null;
    public $selectedPicIds = [];
    public $picSearch = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingClassTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->courseFilter = '';
        $this->statusFilter = 'active';
        $this->classTypeFilter = '';
        $this->categoryFilter = '';
        $this->resetPage();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    private function baseClassQuery()
    {
        return ClassModel::query()
            ->with(['course', 'teacher.user', 'categories', 'pics'])
            ->withCount([
                'activeStudents',
                'sessions as total_sessions_count',
                'sessions as completed_sessions_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                        ->orWhereHas('course', function ($courseQuery) {
                            $courseQuery->where('name', 'like', '%' . $this->search . '%');
                        })
                        ->orWhereHas('teacher.user', function ($teacherQuery) {
                            $teacherQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->courseFilter, function ($query) {
                $query->where('course_id', $this->courseFilter);
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->classTypeFilter, function ($query) {
                $query->where('class_type', $this->classTypeFilter);
            })
            ->when($this->categoryFilter, function ($query) {
                $query->whereHas('categories', function ($catQuery) {
                    $catQuery->where('class_categories.id', $this->categoryFilter);
                });
            })
            ->orderBy('date_time', 'desc');
    }

    public function getClassesProperty()
    {
        return $this->baseClassQuery()->paginate($this->perPage);
    }

    public function getGroupedClassesProperty()
    {
        $classes = $this->baseClassQuery()->get();
        $categories = ClassCategory::active()->ordered()->get();

        $grouped = [];

        foreach ($categories as $category) {
            $categoryClasses = $classes->filter(function ($class) use ($category) {
                return $class->categories->contains($category->id);
            });

            if ($categoryClasses->count() > 0) {
                $grouped[] = [
                    'category' => $category,
                    'classes' => $categoryClasses,
                ];
            }
        }

        $uncategorizedClasses = $classes->filter(function ($class) {
            return $class->categories->isEmpty();
        });

        if ($uncategorizedClasses->count() > 0) {
            $grouped[] = [
                'category' => null,
                'classes' => $uncategorizedClasses,
            ];
        }

        return $grouped;
    }

    public function getGroupedByPicClassesProperty()
    {
        $classes = $this->baseClassQuery()->get();
        $picUsers = User::whereHas('picClasses')->where('role', '!=', 'student')->orderBy('name')->get();

        $grouped = [];

        foreach ($picUsers as $pic) {
            $picClasses = $classes->filter(function ($class) use ($pic) {
                return $class->pics->contains($pic->id);
            });

            if ($picClasses->count() > 0) {
                $grouped[] = [
                    'pic' => $pic,
                    'classes' => $picClasses,
                ];
            }
        }

        $noPicClasses = $classes->filter(function ($class) {
            return $class->pics->isEmpty();
        });

        if ($noPicClasses->count() > 0) {
            $grouped[] = [
                'pic' => null,
                'classes' => $noPicClasses,
            ];
        }

        return $grouped;
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => ClassModel::count(),
            'active' => ClassModel::where('status', 'active')->count(),
            'upcoming' => ClassModel::where('status', 'active')
                ->whereHas('sessions', function ($q) {
                    $q->where('session_date', '>', now()->toDateString())
                        ->where('status', 'scheduled');
                })->count(),
        ];
    }

    public function getCoursesProperty()
    {
        return Course::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    public function getCategoriesProperty()
    {
        return ClassCategory::active()->ordered()->get(['id', 'name']);
    }

    public function getAllCategoriesProperty()
    {
        return ClassCategory::ordered()->get();
    }

    // Category assignment methods
    public function openCategoryModal($classId): void
    {
        $this->editingClassId = $classId;
        $class = ClassModel::find($classId);
        $this->selectedCategoryIds = $class->categories->pluck('id')->toArray();
        $this->showCategoryModal = true;
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->editingClassId = null;
        $this->selectedCategoryIds = [];
    }

    public function saveCategoryAssignments(): void
    {
        $class = ClassModel::find($this->editingClassId);
        if ($class) {
            $class->categories()->sync($this->selectedCategoryIds);
        }
        $this->closeCategoryModal();
    }

    // Category management methods
    public function openCategoryManageModal($categoryId = null): void
    {
        $this->editingCategoryId = $categoryId;

        if ($categoryId) {
            $category = ClassCategory::find($categoryId);
            $this->categoryName = $category->name;
            $this->categoryColor = $category->color;
            $this->categoryDescription = $category->description ?? '';
        } else {
            $this->categoryName = '';
            $this->categoryColor = '#6366f1';
            $this->categoryDescription = '';
        }

        $this->showCategoryManageModal = true;
    }

    public function closeCategoryManageModal(): void
    {
        $this->showCategoryManageModal = false;
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categoryColor = '#6366f1';
        $this->categoryDescription = '';
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
            'categoryColor' => 'required|string|max:7',
            'categoryDescription' => 'nullable|string|max:500',
        ]);

        if ($this->editingCategoryId) {
            $category = ClassCategory::find($this->editingCategoryId);
            $category->update([
                'name' => $this->categoryName,
                'slug' => \Str::slug($this->categoryName),
                'color' => $this->categoryColor,
                'description' => $this->categoryDescription,
            ]);
        } else {
            ClassCategory::create([
                'name' => $this->categoryName,
                'slug' => \Str::slug($this->categoryName),
                'color' => $this->categoryColor,
                'description' => $this->categoryDescription,
                'is_active' => true,
                'sort_order' => (ClassCategory::max('sort_order') ?? 0) + 1,
            ]);
        }

        $this->closeCategoryManageModal();
        $this->dispatch('$refresh');
    }

    public function deleteCategory($categoryId): void
    {
        $category = ClassCategory::find($categoryId);
        if ($category) {
            $category->delete();
        }
        $this->dispatch('$refresh');
    }

    public function toggleCategoryStatus($categoryId): void
    {
        $category = ClassCategory::find($categoryId);
        if ($category) {
            $category->update(['is_active' => !$category->is_active]);
        }
        $this->dispatch('$refresh');
    }

    public function switchToManageModal(): void
    {
        $this->closeCategoryModal();
        $this->openCategoryManageModal();
    }

    // PIC assignment methods
    public function getUsersProperty()
    {
        return User::query()
            ->where('status', 'active')
            ->where('role', '!=', 'student')
            ->when($this->picSearch, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->picSearch . '%')
                      ->orWhere('email', 'like', '%' . $this->picSearch . '%');
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    public function openPicModal($classId): void
    {
        $this->editingPicClassId = $classId;
        $class = ClassModel::find($classId);
        $this->selectedPicIds = $class->pics->pluck('id')->toArray();
        $this->picSearch = '';
        $this->showPicModal = true;
    }

    public function closePicModal(): void
    {
        $this->showPicModal = false;
        $this->editingPicClassId = null;
        $this->selectedPicIds = [];
        $this->picSearch = '';
    }

    public function savePicAssignments(): void
    {
        $class = ClassModel::find($this->editingPicClassId);
        if ($class) {
            // Sync with assigned_by tracking
            $syncData = [];
            foreach ($this->selectedPicIds as $userId) {
                $syncData[$userId] = ['assigned_by' => auth()->id()];
            }
            $class->pics()->sync($syncData);
        }
        $this->closePicModal();
    }

    public function togglePicSelection($userId): void
    {
        if (in_array($userId, $this->selectedPicIds)) {
            $this->selectedPicIds = array_diff($this->selectedPicIds, [$userId]);
        } else {
            $this->selectedPicIds[] = $userId;
        }
    }
};

?>

<div>
    <div class="mb-5 flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">Classes</flux:heading>
                <div class="hidden sm:flex items-center gap-1.5">
                    <span class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:text-zinc-300">{{ $this->stats['total'] }} total</span>
                    <span class="inline-flex items-center rounded-md bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 text-xs font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{{ $this->stats['active'] }} active</span>
                    <span class="inline-flex items-center rounded-md bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 text-xs font-medium tabular-nums text-amber-700 dark:text-amber-400">{{ $this->stats['upcoming'] }} upcoming</span>
                </div>
            </div>
            <flux:text class="mt-0.5">Manage classes, schedules & assignments</flux:text>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <flux:button variant="ghost" wire:click="openCategoryManageModal" icon="folder" size="sm">
                Categories
            </flux:button>
            <flux:button variant="primary" href="{{ route('classes.create') }}" icon="plus" size="sm">
                New Class
            </flux:button>
        </div>
    </div>

    <div class="space-y-4">
        <!-- Toolbar -->
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
            <!-- Filters Row -->
            <div class="flex items-center gap-2 p-2.5">
                <div class="flex-1 min-w-0">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search classes, courses, teachers..."
                        icon="magnifying-glass"
                        size="sm"
                    />
                </div>

                <div class="hidden lg:flex items-center gap-2">
                    <div class="w-40 shrink-0">
                        <flux:select wire:model.live="courseFilter" size="sm">
                            <flux:select.option value="">All Courses</flux:select.option>
                            @foreach($this->courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="w-40 shrink-0">
                        <flux:select wire:model.live="categoryFilter" size="sm">
                            <flux:select.option value="">All Categories</flux:select.option>
                            @foreach($this->categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="w-32 shrink-0">
                        <flux:select wire:model.live="statusFilter" size="sm">
                            <flux:select.option value="">All Status</flux:select.option>
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                            <flux:select.option value="cancelled">Cancelled</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-28 shrink-0">
                        <flux:select wire:model.live="classTypeFilter" size="sm">
                            <flux:select.option value="">All Types</flux:select.option>
                            <flux:select.option value="individual">Individual</flux:select.option>
                            <flux:select.option value="group">Group</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                @if($search || $courseFilter || $statusFilter !== 'active' || $classTypeFilter || $categoryFilter)
                    <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark" title="Reset filters" />
                @endif
            </div>

            <!-- Mobile Filters (shown below on smaller screens) -->
            <div class="lg:hidden border-t border-zinc-100 dark:border-zinc-700 p-2.5">
                <div class="grid grid-cols-2 gap-2">
                    <flux:select wire:model.live="courseFilter" size="sm">
                        <flux:select.option value="">All Courses</flux:select.option>
                        @foreach($this->courses as $course)
                            <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="categoryFilter" size="sm">
                        <flux:select.option value="">All Categories</flux:select.option>
                        @foreach($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="statusFilter" size="sm">
                        <flux:select.option value="">All Status</flux:select.option>
                        <flux:select.option value="draft">Draft</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="completed">Completed</flux:select.option>
                        <flux:select.option value="suspended">Suspended</flux:select.option>
                        <flux:select.option value="cancelled">Cancelled</flux:select.option>
                    </flux:select>
                    <flux:select wire:model.live="classTypeFilter" size="sm">
                        <flux:select.option value="">All Types</flux:select.option>
                        <flux:select.option value="individual">Individual</flux:select.option>
                        <flux:select.option value="group">Group</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- View Toggle + Count -->
            <div class="flex items-center justify-between border-t border-zinc-100 dark:border-zinc-700 px-2.5 py-1.5">
                <div class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-700/50 p-0.5">
                    <button
                        wire:click="setViewMode('list')"
                        class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all {{ $viewMode === 'list' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                    >
                        <flux:icon name="list-bullet" class="w-3.5 h-3.5" />
                        List
                    </button>
                    <button
                        wire:click="setViewMode('grouped')"
                        class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all {{ $viewMode === 'grouped' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                    >
                        <flux:icon name="squares-2x2" class="w-3.5 h-3.5" />
                        Category
                    </button>
                    <button
                        wire:click="setViewMode('pic')"
                        class="inline-flex items-center gap-1.5 rounded px-2.5 py-1 text-xs font-medium transition-all {{ $viewMode === 'pic' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                    >
                        <flux:icon name="user-circle" class="w-3.5 h-3.5" />
                        PIC
                    </button>
                </div>
                <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    @if($viewMode === 'list')
                        {{ $this->classes->total() }} classes
                    @elseif($viewMode === 'grouped')
                        {{ collect($this->groupedClasses)->sum(fn($g) => $g['classes']->count()) }} classes
                    @else
                        {{ collect($this->groupedByPicClasses)->sum(fn($g) => $g['classes']->count()) }} classes
                    @endif
                </span>
            </div>
        </div>

        @if($viewMode === 'list')
        <!-- List View -->
        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-800/80">
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Class</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Category</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Teacher</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">PICs</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Schedule</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                            <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Progress</th>
                            <th class="px-3 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @forelse ($this->classes as $class)
                            <tr wire:key="list-{{ $class->id }}" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <a href="{{ route('classes.show', $class) }}" class="block">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $class->title }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $class->course->name }}</div>
                                    </a>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <button
                                        wire:click="openCategoryModal({{ $class->id }})"
                                        class="flex flex-wrap gap-1 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded p-0.5 -m-0.5 transition-colors group/cat"
                                        title="Edit categories"
                                    >
                                        @forelse($class->categories as $category)
                                            <span
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[11px] font-medium"
                                                style="background-color: {{ $category->color }}15; color: {{ $category->color }}"
                                            >
                                                <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                                                {{ $category->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-zinc-400 group-hover/cat:text-zinc-600 dark:group-hover/cat:text-zinc-300 flex items-center gap-0.5">
                                                <flux:icon.plus class="w-3 h-3" />
                                                Add
                                            </span>
                                        @endforelse
                                    </button>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <flux:avatar size="xs" :name="$class->teacher->fullName" />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->teacher->fullName }}</span>
                                    </div>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <button
                                        wire:click="openPicModal({{ $class->id }})"
                                        class="flex items-center gap-1 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded p-0.5 -m-0.5 transition-colors group/pic"
                                        title="Assign PICs"
                                    >
                                        @if($class->pics->count() > 0)
                                            <div class="flex -space-x-1.5">
                                                @foreach($class->pics->take(3) as $pic)
                                                    <flux:avatar size="xs" :name="$pic->name" class="ring-1 ring-white dark:ring-zinc-800" />
                                                @endforeach
                                            </div>
                                            @if($class->pics->count() > 3)
                                                <span class="text-[11px] text-zinc-500 ml-0.5">+{{ $class->pics->count() - 3 }}</span>
                                            @endif
                                        @else
                                            <span class="text-xs text-zinc-400 group-hover/pic:text-zinc-600 dark:group-hover/pic:text-zinc-300 flex items-center gap-0.5">
                                                <flux:icon.plus class="w-3 h-3" />
                                                Add
                                            </span>
                                        @endif
                                    </button>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->date_time->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $class->date_time->format('g:i A') }} · {{ $class->formatted_duration }}</div>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <flux:badge size="sm" :class="$class->status_badge_class">
                                        {{ ucfirst($class->status) }}
                                    </flux:badge>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="text-sm tabular-nums text-zinc-700 dark:text-zinc-300">
                                        {{ $class->active_students_count }} <span class="text-zinc-400">stu</span>
                                    </div>
                                    <div class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $class->completed_sessions_count }}/{{ $class->total_sessions_count }} sess
                                    </div>
                                </td>

                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <flux:button size="xs" variant="ghost" icon="eye" href="{{ route('classes.show', $class) }}" title="View" />
                                        <flux:button size="xs" variant="ghost" icon="pencil" href="{{ route('classes.edit', $class) }}" title="Edit" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <flux:icon.calendar-days class="h-8 w-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No classes found</p>
                                    @if($search || $courseFilter || $statusFilter !== 'active' || $classTypeFilter || $categoryFilter)
                                        <flux:button wire:click="clearFilters" variant="ghost" size="sm" class="mt-2">
                                            Reset filters
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->classes->hasPages())
                <div class="px-3 py-2.5 border-t border-zinc-200 dark:border-zinc-700">
                    {{ $this->classes->links() }}
                </div>
            @endif
        </div>
        @elseif($viewMode === 'grouped')
        <!-- Category View -->
        <div class="space-y-2.5">
            @forelse($this->groupedClasses as $index => $group)
                <div x-data="{ expanded: true }" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                    <button
                        @click="expanded = !expanded"
                        class="w-full px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors cursor-pointer"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if($group['category'])
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $group['category']->color }}"></span>
                                    <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $group['category']->name }}</span>
                                @else
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-zinc-300 dark:bg-zinc-600"></span>
                                    <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Uncategorized</span>
                                @endif
                                <span class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-700 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $group['classes']->count() }}</span>
                            </div>
                            <flux:icon
                                name="chevron-down"
                                class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200"
                                ::class="expanded ? 'rotate-180' : ''"
                            />
                        </div>
                    </button>

                    <div x-show="expanded" x-collapse class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-zinc-100 dark:border-zinc-700/50 bg-zinc-50/50 dark:bg-zinc-800/50">
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Class</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Teacher</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">PICs</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Schedule</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Progress</th>
                                    <th class="px-3 py-1.5 w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @foreach($group['classes'] as $class)
                                    <tr wire:key="grouped-{{ $class->id }}" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <a href="{{ route('classes.show', $class) }}" class="block">
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $class->title }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $class->course->name }}</div>
                                            </a>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <flux:avatar size="xs" :name="$class->teacher->fullName" />
                                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->teacher->fullName }}</span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <button
                                                wire:click="openPicModal({{ $class->id }})"
                                                class="flex items-center gap-1 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded p-0.5 -m-0.5 transition-colors group/pic"
                                                title="Assign PICs"
                                            >
                                                @if($class->pics->count() > 0)
                                                    <div class="flex -space-x-1.5">
                                                        @foreach($class->pics->take(3) as $pic)
                                                            <flux:avatar size="xs" :name="$pic->name" class="ring-1 ring-white dark:ring-zinc-800" />
                                                        @endforeach
                                                    </div>
                                                    @if($class->pics->count() > 3)
                                                        <span class="text-[11px] text-zinc-500 ml-0.5">+{{ $class->pics->count() - 3 }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-zinc-400 group-hover/pic:text-zinc-600 dark:group-hover/pic:text-zinc-300 flex items-center gap-0.5">
                                                        <flux:icon.plus class="w-3 h-3" />
                                                        Add
                                                    </span>
                                                @endif
                                            </button>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->date_time->format('M d, Y') }}</div>
                                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $class->date_time->format('g:i A') }} · {{ $class->formatted_duration }}</div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <flux:badge size="sm" :class="$class->status_badge_class">
                                                {{ ucfirst($class->status) }}
                                            </flux:badge>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="text-sm tabular-nums text-zinc-700 dark:text-zinc-300">
                                                {{ $class->active_students_count }} <span class="text-zinc-400">stu</span>
                                            </div>
                                            <div class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ $class->completed_sessions_count }}/{{ $class->total_sessions_count }} sess
                                            </div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <flux:button size="xs" variant="ghost" icon="eye" href="{{ route('classes.show', $class) }}" title="View" />
                                                <flux:button size="xs" variant="ghost" icon="pencil" href="{{ route('classes.edit', $class) }}" title="Edit" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-12 text-center">
                    <flux:icon.calendar-days class="h-8 w-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No classes found</p>
                    @if($search || $courseFilter || $statusFilter !== 'active' || $classTypeFilter || $categoryFilter)
                        <flux:button wire:click="clearFilters" variant="ghost" size="sm" class="mt-2">
                            Reset filters
                        </flux:button>
                    @endif
                </div>
            @endforelse
        </div>
        @elseif($viewMode === 'pic')
        <!-- PIC View -->
        <div class="space-y-2.5">
            @forelse($this->groupedByPicClasses as $index => $group)
                <div x-data="{ expanded: true }" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                    <button
                        @click="expanded = !expanded"
                        class="w-full px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors cursor-pointer"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                @if($group['pic'])
                                    <flux:avatar size="xs" :name="$group['pic']->name" />
                                    <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $group['pic']->name }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $group['pic']->role_name }}</span>
                                @else
                                    <div class="w-6 h-6 rounded-full flex-shrink-0 bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
                                        <flux:icon.user class="w-3 h-3 text-zinc-400" />
                                    </div>
                                    <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">No PIC Assigned</span>
                                @endif
                                <span class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-700 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $group['classes']->count() }}</span>
                            </div>
                            <flux:icon
                                name="chevron-down"
                                class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200"
                                ::class="expanded ? 'rotate-180' : ''"
                            />
                        </div>
                    </button>

                    <div x-show="expanded" x-collapse class="overflow-x-auto border-t border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-zinc-100 dark:border-zinc-700/50 bg-zinc-50/50 dark:bg-zinc-800/50">
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Class</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Category</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Teacher</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Schedule</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-1.5 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Progress</th>
                                    <th class="px-3 py-1.5 w-20"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @foreach($group['classes'] as $class)
                                    <tr wire:key="pic-{{ $class->id }}" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <a href="{{ route('classes.show', $class) }}" class="block">
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $class->title }}</div>
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $class->course->name }}</div>
                                            </a>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <button
                                                wire:click="openCategoryModal({{ $class->id }})"
                                                class="flex flex-wrap gap-1 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded p-0.5 -m-0.5 transition-colors group/cat"
                                                title="Edit categories"
                                            >
                                                @forelse($class->categories as $category)
                                                    <span
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[11px] font-medium"
                                                        style="background-color: {{ $category->color }}15; color: {{ $category->color }}"
                                                    >
                                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $category->color }}"></span>
                                                        {{ $category->name }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-zinc-400 group-hover/cat:text-zinc-600 dark:group-hover/cat:text-zinc-300 flex items-center gap-0.5">
                                                        <flux:icon.plus class="w-3 h-3" />
                                                        Add
                                                    </span>
                                                @endforelse
                                            </button>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="flex items-center gap-1.5">
                                                <flux:avatar size="xs" :name="$class->teacher->fullName" />
                                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->teacher->fullName }}</span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $class->date_time->format('M d, Y') }}</div>
                                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $class->date_time->format('g:i A') }} · {{ $class->formatted_duration }}</div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <flux:badge size="sm" :class="$class->status_badge_class">
                                                {{ ucfirst($class->status) }}
                                            </flux:badge>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <div class="text-sm tabular-nums text-zinc-700 dark:text-zinc-300">
                                                {{ $class->active_students_count }} <span class="text-zinc-400">stu</span>
                                            </div>
                                            <div class="text-[11px] tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ $class->completed_sessions_count }}/{{ $class->total_sessions_count }} sess
                                            </div>
                                        </td>

                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <flux:button size="xs" variant="ghost" icon="eye" href="{{ route('classes.show', $class) }}" title="View" />
                                                <flux:button size="xs" variant="ghost" icon="pencil" href="{{ route('classes.edit', $class) }}" title="Edit" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-4 py-12 text-center">
                    <flux:icon.calendar-days class="h-8 w-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No classes found</p>
                    @if($search || $courseFilter || $statusFilter !== 'active' || $classTypeFilter || $categoryFilter)
                        <flux:button wire:click="clearFilters" variant="ghost" size="sm" class="mt-2">
                            Reset filters
                        </flux:button>
                    @endif
                </div>
            @endforelse
        </div>
        @endif
    </div>

    <!-- Category Assignment Modal -->
    <flux:modal wire:model="showCategoryModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Assign Categories</flux:heading>
                <flux:text class="mt-2">Select categories for this class</flux:text>
            </div>

            <div class="space-y-3 max-h-64 overflow-y-auto">
                @forelse($this->allCategories as $category)
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors {{ in_array($category->id, $selectedCategoryIds) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                        <input
                            type="checkbox"
                            wire:model="selectedCategoryIds"
                            value="{{ $category->id }}"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <div class="flex items-center gap-2 flex-1">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $category->color }}"></span>
                            <span class="text-sm font-medium text-gray-700">{{ $category->name }}</span>
                        </div>
                        @if(!$category->is_active)
                            <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                        @endif
                    </label>
                @empty
                    <div class="p-4 border border-dashed border-gray-300 rounded-lg text-center">
                        <flux:icon.folder class="h-8 w-8 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500">No categories yet</p>
                        <flux:button
                            wire:click="switchToManageModal"
                            variant="ghost"
                            size="sm"
                            class="mt-2"
                        >
                            Create Category
                        </flux:button>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-between items-center pt-2 border-t">
                <flux:button
                    wire:click="switchToManageModal"
                    variant="ghost"
                    size="sm"
                    icon="cog-6-tooth"
                >
                    Manage Categories
                </flux:button>
                <div class="flex gap-2">
                    <flux:button wire:click="closeCategoryModal" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="saveCategoryAssignments" variant="primary">Save</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <!-- Category Management Modal -->
    <flux:modal wire:model="showCategoryManageModal" class="md:w-xl">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ $editingCategoryId ? 'Edit Category' : 'Manage Categories' }}</flux:heading>
                    <flux:text class="mt-1">{{ $editingCategoryId ? 'Update category details' : 'Create and manage class categories' }}</flux:text>
                </div>
            </div>

            @if($editingCategoryId || !$this->allCategories->count())
            <!-- Create/Edit Form -->
            <div class="space-y-4 p-4 bg-gray-50 rounded-lg">
                <flux:field>
                    <flux:label>Category Name</flux:label>
                    <flux:input wire:model="categoryName" placeholder="e.g., Quran, Islamic Studies" />
                    <flux:error name="categoryName" />
                </flux:field>

                <flux:field>
                    <flux:label>Color</flux:label>
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            wire:model="categoryColor"
                            class="h-10 w-14 rounded border border-gray-300 cursor-pointer"
                        />
                        <flux:input wire:model="categoryColor" class="flex-1" placeholder="#6366f1" />
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Description (Optional)</flux:label>
                    <flux:textarea wire:model="categoryDescription" rows="2" placeholder="Brief description of this category" />
                </flux:field>

                <!-- Preview -->
                <div class="flex items-center gap-2 p-3 bg-white rounded-lg border">
                    <span class="w-4 h-4 rounded-full" style="background-color: {{ $categoryColor }}"></span>
                    <span class="text-sm font-medium">{{ $categoryName ?: 'Category Preview' }}</span>
                </div>

                <div class="flex justify-end gap-2">
                    @if($editingCategoryId)
                        <flux:button wire:click="$set('editingCategoryId', null)" variant="ghost">Back to List</flux:button>
                    @endif
                    <flux:button wire:click="saveCategory" variant="primary">
                        {{ $editingCategoryId ? 'Update Category' : 'Create Category' }}
                    </flux:button>
                </div>
            </div>
            @else
            <!-- Categories List -->
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @foreach($this->allCategories as $category)
                    <div class="flex items-center justify-between p-3 border rounded-lg {{ $category->is_active ? 'bg-white' : 'bg-gray-50' }}">
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $category->color }}"></span>
                            <div>
                                <span class="text-sm font-medium {{ $category->is_active ? 'text-gray-900' : 'text-gray-500' }}">{{ $category->name }}</span>
                                @if($category->description)
                                    <p class="text-xs text-gray-500">{{ Str::limit($category->description, 40) }}</p>
                                @endif
                            </div>
                            @if(!$category->is_active)
                                <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <flux:button
                                wire:click="toggleCategoryStatus({{ $category->id }})"
                                variant="ghost"
                                size="sm"
                                :icon="$category->is_active ? 'eye-slash' : 'eye'"
                                title="{{ $category->is_active ? 'Deactivate' : 'Activate' }}"
                            />
                            <flux:button
                                wire:click="openCategoryManageModal({{ $category->id }})"
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                            />
                            <flux:button
                                wire:click="deleteCategory({{ $category->id }})"
                                wire:confirm="Are you sure you want to delete this category? Classes with this category will be updated."
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                class="text-red-600 hover:text-red-700"
                            />
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Add New Category Button -->
            <div class="pt-4 border-t">
                <flux:button
                    wire:click="$set('editingCategoryId', 0)"
                    variant="ghost"
                    icon="plus"
                    class="w-full justify-center"
                >
                    Add New Category
                </flux:button>
            </div>
            @endif

            <div class="flex justify-end pt-2 border-t">
                <flux:button wire:click="closeCategoryManageModal" variant="ghost">Close</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- PIC Assignment Modal -->
    <flux:modal wire:model="showPicModal" class="md:w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Assign Person In Charge (PIC)</flux:heading>
                <flux:text class="mt-2">Select users to be responsible for this class</flux:text>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="picSearch"
                placeholder="Search users by name or email..."
                icon="magnifying-glass"
            />

            <div class="space-y-2 max-h-72 overflow-y-auto">
                @forelse($this->users as $user)
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors {{ in_array($user->id, $selectedPicIds) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                        <input
                            type="checkbox"
                            wire:model="selectedPicIds"
                            value="{{ $user->id }}"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <flux:avatar size="sm" :name="$user->name" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        </div>
                        <flux:badge size="sm" color="zinc">{{ $user->role_name }}</flux:badge>
                    </label>
                @empty
                    <div class="p-4 border border-dashed border-gray-300 rounded-lg text-center">
                        <flux:icon.users class="h-8 w-8 mx-auto text-gray-400 mb-2" />
                        <p class="text-sm text-gray-500">
                            @if($picSearch)
                                No users found matching "{{ $picSearch }}"
                            @else
                                No active users available
                            @endif
                        </p>
                    </div>
                @endforelse
            </div>

            @if(count($selectedPicIds) > 0)
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <span class="font-medium">{{ count($selectedPicIds) }}</span> PIC(s) selected
                    </p>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-2 border-t">
                <flux:button wire:click="closePicModal" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="savePicAssignments" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</div>