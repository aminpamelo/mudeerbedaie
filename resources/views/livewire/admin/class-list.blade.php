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
    public $statusFilter = '';
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
        $this->statusFilter = '';
        $this->classTypeFilter = '';
        $this->categoryFilter = '';
        $this->resetPage();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function getClassesProperty()
    {
        return ClassModel::query()
            ->with(['course', 'teacher.user', 'sessions', 'activeStudents', 'categories', 'pics'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('course', function ($courseQuery) {
                        $courseQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('teacher.user', function ($teacherQuery) {
                        $teacherQuery->where('name', 'like', '%' . $this->search . '%');
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
            ->orderBy('date_time', 'desc')
            ->paginate($this->perPage);
    }

    public function getGroupedClassesProperty()
    {
        $query = ClassModel::query()
            ->with(['course', 'teacher.user', 'sessions', 'activeStudents', 'categories', 'pics'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('course', function ($courseQuery) {
                        $courseQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('teacher.user', function ($teacherQuery) {
                        $teacherQuery->where('name', 'like', '%' . $this->search . '%');
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
            ->orderBy('date_time', 'desc')
            ->get();

        // Get all active categories with their classes
        $categories = ClassCategory::active()->ordered()->get();

        $grouped = [];

        // Group by categories
        foreach ($categories as $category) {
            $categoryClasses = $query->filter(function ($class) use ($category) {
                return $class->categories->contains($category->id);
            });

            if ($categoryClasses->count() > 0) {
                $grouped[] = [
                    'category' => $category,
                    'classes' => $categoryClasses,
                ];
            }
        }

        // Get uncategorized classes
        $uncategorizedClasses = $query->filter(function ($class) {
            return $class->categories->count() === 0;
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
        $query = ClassModel::query()
            ->with(['course', 'teacher.user', 'sessions', 'activeStudents', 'categories', 'pics'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('course', function ($courseQuery) {
                        $courseQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('teacher.user', function ($teacherQuery) {
                        $teacherQuery->where('name', 'like', '%' . $this->search . '%');
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
            ->orderBy('date_time', 'desc')
            ->get();

        // Get all users who are PICs
        $picUsers = User::whereHas('picClasses')->where('role', '!=', 'student')->orderBy('name')->get();

        $grouped = [];

        // Group by PICs
        foreach ($picUsers as $pic) {
            $picClasses = $query->filter(function ($class) use ($pic) {
                return $class->pics->contains($pic->id);
            });

            if ($picClasses->count() > 0) {
                $grouped[] = [
                    'pic' => $pic,
                    'classes' => $picClasses,
                ];
            }
        }

        // Get classes without PICs
        $noPicClasses = $query->filter(function ($class) {
            return $class->pics->count() === 0;
        });

        if ($noPicClasses->count() > 0) {
            $grouped[] = [
                'pic' => null,
                'classes' => $noPicClasses,
            ];
        }

        return $grouped;
    }

    public function getTotalClassesProperty()
    {
        return ClassModel::count();
    }

    public function getActiveClassesProperty()
    {
        return ClassModel::where('status', 'active')->count();
    }

    public function getUpcomingClassesProperty()
    {
        return ClassModel::upcoming()->count();
    }

    public function getCoursesProperty()
    {
        return Course::where('status', 'active')->orderBy('name')->get();
    }

    public function getCategoriesProperty()
    {
        return ClassCategory::active()->ordered()->get();
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
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Classes</flux:heading>
            <flux:text class="mt-2">Manage classes and schedules</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" wire:click="openCategoryManageModal" icon="folder">
                Manage Categories
            </flux:button>
            <flux:button variant="primary" href="{{ route('classes.create') }}" icon="plus">
                Schedule New Class
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 p-3">
                        <flux:icon.calendar-days class="h-6 w-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->totalClasses }}</p>
                        <p class="text-sm text-gray-500">Total Classes</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 p-3">
                        <flux:icon.clock class="h-6 w-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->activeClasses }}</p>
                        <p class="text-sm text-gray-500">Active</p>
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-amber-50 p-3">
                        <flux:icon.forward class="h-6 w-6 text-amber-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900">{{ $this->upcomingClasses }}</p>
                        <p class="text-sm text-gray-500">Upcoming</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Filters -->
        <flux:card>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div class="md:col-span-2">
                        <flux:input
                            wire:model.live="search"
                            placeholder="Search classes..."
                            icon="magnifying-glass"
                        />
                    </div>

                    <div>
                        <flux:select wire:model.live="courseFilter">
                            <flux:select.option value="">All Courses</flux:select.option>
                            @foreach($this->courses as $course)
                                <flux:select.option value="{{ $course->id }}">{{ $course->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="categoryFilter">
                            <flux:select.option value="">All Categories</flux:select.option>
                            @foreach($this->categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="statusFilter">
                            <flux:select.option value="">All Status</flux:select.option>
                            <flux:select.option value="draft">Draft</flux:select.option>
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="suspended">Suspended</flux:select.option>
                            <flux:select.option value="cancelled">Cancelled</flux:select.option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="classTypeFilter">
                            <flux:select.option value="">All Types</flux:select.option>
                            <flux:select.option value="individual">Individual</flux:select.option>
                            <flux:select.option value="group">Group</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:button
                            wire:click="setViewMode('list')"
                            variant="{{ $viewMode === 'list' ? 'primary' : 'ghost' }}"
                            size="sm"
                            icon="list-bullet"
                        >
                            List View
                        </flux:button>
                        <flux:button
                            wire:click="setViewMode('grouped')"
                            variant="{{ $viewMode === 'grouped' ? 'primary' : 'ghost' }}"
                            size="sm"
                            icon="squares-2x2"
                        >
                            Group by Category
                        </flux:button>
                        <flux:button
                            wire:click="setViewMode('pic')"
                            variant="{{ $viewMode === 'pic' ? 'primary' : 'ghost' }}"
                            size="sm"
                            icon="user-circle"
                        >
                            Group by PIC
                        </flux:button>
                    </div>
                    <flux:button
                        wire:click="clearFilters"
                        variant="ghost"
                        icon="x-mark"
                    >
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </flux:card>

        @if($viewMode === 'list')
        <!-- Classes List View (List) -->
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <flux:heading size="lg">Classes List</flux:heading>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PICs</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($this->classes as $class)
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $class->title }}</div>
                                            @if($class->description)
                                                <div class="text-sm text-gray-500 truncate max-w-xs">
                                                    {{ Str::limit($class->description, 50) }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            wire:click="openCategoryModal({{ $class->id }})"
                                            class="flex flex-wrap gap-1 cursor-pointer hover:bg-gray-100 rounded-lg p-1 -m-1 transition-colors group"
                                            title="Click to edit categories"
                                        >
                                            @forelse($class->categories as $category)
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                                    style="background-color: {{ $category->color }}20; color: {{ $category->color }}"
                                                >
                                                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $category->color }}"></span>
                                                    {{ $category->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-gray-400 group-hover:text-gray-600 flex items-center gap-1">
                                                    <flux:icon.plus class="w-3 h-3" />
                                                    Add category
                                                </span>
                                            @endforelse
                                        </button>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $class->course->name }}</div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <flux:avatar size="sm" :name="$class->teacher->fullName" />
                                            <div class="text-sm text-gray-900">{{ $class->teacher->fullName }}</div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button
                                            wire:click="openPicModal({{ $class->id }})"
                                            class="flex items-center gap-1 cursor-pointer hover:bg-gray-100 rounded-lg p-1 -m-1 transition-colors group"
                                            title="Click to assign PICs"
                                        >
                                            @if($class->pics->count() > 0)
                                                <div class="flex -space-x-2">
                                                    @foreach($class->pics->take(3) as $pic)
                                                        <flux:avatar size="xs" :name="$pic->name" class="ring-2 ring-white" />
                                                    @endforeach
                                                </div>
                                                @if($class->pics->count() > 3)
                                                    <span class="text-xs text-gray-500 ml-1">+{{ $class->pics->count() - 3 }}</span>
                                                @endif
                                            @else
                                                <span class="text-xs text-gray-400 group-hover:text-gray-600 flex items-center gap-1">
                                                    <flux:icon.plus class="w-3 h-3" />
                                                    Add PIC
                                                </span>
                                            @endif
                                        </button>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm text-gray-900">{{ $class->date_time->format('M d, Y') }}</div>
                                            <div class="text-sm text-gray-500">
                                                {{ $class->date_time->format('g:i A') }} ({{ $class->formatted_duration }})
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" :class="$class->status_badge_class">
                                            {{ ucfirst($class->status) }}
                                        </flux:badge>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $class->active_student_count }} student(s)
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $class->completed_sessions }}/{{ $class->total_sessions }} sessions
                                        </div>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="folder"
                                                wire:click="openCategoryModal({{ $class->id }})"
                                                title="Edit categories"
                                            />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="user-group"
                                                wire:click="openPicModal({{ $class->id }})"
                                                title="Assign PICs"
                                            />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="eye"
                                                href="{{ route('classes.show', $class) }}"
                                            />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                                href="{{ route('classes.edit', $class) }}"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <flux:icon.calendar-days class="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                            <p class="text-gray-600">No classes found</p>
                                            @if($search || $courseFilter || $statusFilter || $classTypeFilter || $categoryFilter)
                                                <flux:button
                                                    wire:click="clearFilters"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="mt-2"
                                                >
                                                    Clear filters
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($this->classes->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $this->classes->links() }}
                    </div>
                @endif
            </div>
        </flux:card>
        @elseif($viewMode === 'grouped')
        <!-- Grouped by Category View -->
        <div class="space-y-4">
            @forelse($this->groupedClasses as $index => $group)
                <flux:card x-data="{ expanded: true }">
                    <div class="overflow-hidden">
                        <button
                            @click="expanded = !expanded"
                            class="w-full px-6 py-4 border-b border-gray-200 hover:bg-gray-50 transition-colors cursor-pointer"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($group['category'])
                                        <div
                                            class="w-4 h-4 rounded-full flex-shrink-0"
                                            style="background-color: {{ $group['category']->color }}"
                                        ></div>
                                        <flux:heading size="lg">{{ $group['category']->name }}</flux:heading>
                                        <flux:badge size="sm" color="zinc">{{ $group['classes']->count() }} classes</flux:badge>
                                    @else
                                        <div class="w-4 h-4 rounded-full flex-shrink-0 bg-gray-300"></div>
                                        <flux:heading size="lg">Uncategorized</flux:heading>
                                        <flux:badge size="sm" color="zinc">{{ $group['classes']->count() }} classes</flux:badge>
                                    @endif
                                </div>
                                <flux:icon
                                    name="chevron-down"
                                    class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                    ::class="expanded ? 'rotate-180' : ''"
                                />
                            </div>
                            @if($group['category'] && $group['category']->description)
                                <flux:text class="mt-1 text-left">{{ $group['category']->description }}</flux:text>
                            @endif
                        </button>

                        <div x-show="expanded" x-collapse class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PICs</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($group['classes'] as $class)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $class->title }}</div>
                                                    @if($class->description)
                                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                                            {{ Str::limit($class->description, 50) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $class->course->name }}</div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar size="sm" :name="$class->teacher->fullName" />
                                                    <div class="text-sm text-gray-900">{{ $class->teacher->fullName }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <button
                                                    wire:click="openPicModal({{ $class->id }})"
                                                    class="flex items-center gap-1 cursor-pointer hover:bg-gray-100 rounded-lg p-1 -m-1 transition-colors group"
                                                    title="Click to assign PICs"
                                                >
                                                    @if($class->pics->count() > 0)
                                                        <div class="flex -space-x-2">
                                                            @foreach($class->pics->take(3) as $pic)
                                                                <flux:avatar size="xs" :name="$pic->name" class="ring-2 ring-white" />
                                                            @endforeach
                                                        </div>
                                                        @if($class->pics->count() > 3)
                                                            <span class="text-xs text-gray-500 ml-1">+{{ $class->pics->count() - 3 }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-gray-400 group-hover:text-gray-600 flex items-center gap-1">
                                                            <flux:icon.plus class="w-3 h-3" />
                                                            Add PIC
                                                        </span>
                                                    @endif
                                                </button>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm text-gray-900">{{ $class->date_time->format('M d, Y') }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $class->date_time->format('g:i A') }} ({{ $class->formatted_duration }})
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge size="sm" :class="$class->status_badge_class">
                                                    {{ ucfirst($class->status) }}
                                                </flux:badge>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $class->active_student_count }} student(s)
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $class->completed_sessions }}/{{ $class->total_sessions }} sessions
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="folder"
                                                        wire:click="openCategoryModal({{ $class->id }})"
                                                        title="Edit categories"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="user-group"
                                                        wire:click="openPicModal({{ $class->id }})"
                                                        title="Assign PICs"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="eye"
                                                        href="{{ route('classes.show', $class) }}"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="pencil"
                                                        href="{{ route('classes.edit', $class) }}"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <div class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <flux:icon.calendar-days class="h-12 w-12 mx-auto mb-4 text-gray-300" />
                            <p class="text-gray-600">No classes found</p>
                            @if($search || $courseFilter || $statusFilter || $classTypeFilter || $categoryFilter)
                                <flux:button
                                    wire:click="clearFilters"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-2"
                                >
                                    Clear filters
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforelse
        </div>
        @elseif($viewMode === 'pic')
        <!-- Grouped by PIC View -->
        <div class="space-y-4">
            @forelse($this->groupedByPicClasses as $index => $group)
                <flux:card x-data="{ expanded: true }">
                    <div class="overflow-hidden">
                        <button
                            @click="expanded = !expanded"
                            class="w-full px-6 py-4 border-b border-gray-200 hover:bg-gray-50 transition-colors cursor-pointer"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    @if($group['pic'])
                                        <flux:avatar size="sm" :name="$group['pic']->name" />
                                        <div class="text-left">
                                            <flux:heading size="lg">{{ $group['pic']->name }}</flux:heading>
                                            <flux:text class="text-sm text-gray-500">{{ $group['pic']->email }}</flux:text>
                                        </div>
                                        <flux:badge size="sm" color="zinc">{{ $group['pic']->role_name }}</flux:badge>
                                        <flux:badge size="sm" color="blue">{{ $group['classes']->count() }} classes</flux:badge>
                                    @else
                                        <div class="w-8 h-8 rounded-full flex-shrink-0 bg-gray-300 flex items-center justify-center">
                                            <flux:icon.user class="w-4 h-4 text-gray-500" />
                                        </div>
                                        <flux:heading size="lg">No PIC Assigned</flux:heading>
                                        <flux:badge size="sm" color="zinc">{{ $group['classes']->count() }} classes</flux:badge>
                                    @endif
                                </div>
                                <flux:icon
                                    name="chevron-down"
                                    class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                    ::class="expanded ? 'rotate-180' : ''"
                                />
                            </div>
                        </button>

                        <div x-show="expanded" x-collapse class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($group['classes'] as $class)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="font-medium text-gray-900">{{ $class->title }}</div>
                                                    @if($class->description)
                                                        <div class="text-sm text-gray-500 truncate max-w-xs">
                                                            {{ Str::limit($class->description, 50) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <button
                                                    wire:click="openCategoryModal({{ $class->id }})"
                                                    class="flex flex-wrap gap-1 cursor-pointer hover:bg-gray-100 rounded-lg p-1 -m-1 transition-colors group"
                                                    title="Click to edit categories"
                                                >
                                                    @forelse($class->categories as $category)
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                                            style="background-color: {{ $category->color }}20; color: {{ $category->color }}"
                                                        >
                                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $category->color }}"></span>
                                                            {{ $category->name }}
                                                        </span>
                                                    @empty
                                                        <span class="text-xs text-gray-400 group-hover:text-gray-600 flex items-center gap-1">
                                                            <flux:icon.plus class="w-3 h-3" />
                                                            Add
                                                        </span>
                                                    @endforelse
                                                </button>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $class->course->name }}</div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar size="sm" :name="$class->teacher->fullName" />
                                                    <div class="text-sm text-gray-900">{{ $class->teacher->fullName }}</div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm text-gray-900">{{ $class->date_time->format('M d, Y') }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $class->date_time->format('g:i A') }} ({{ $class->formatted_duration }})
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge size="sm" :class="$class->status_badge_class">
                                                    {{ ucfirst($class->status) }}
                                                </flux:badge>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ $class->active_student_count }} student(s)
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $class->completed_sessions }}/{{ $class->total_sessions }} sessions
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="folder"
                                                        wire:click="openCategoryModal({{ $class->id }})"
                                                        title="Edit categories"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="user-group"
                                                        wire:click="openPicModal({{ $class->id }})"
                                                        title="Assign PICs"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="eye"
                                                        href="{{ route('classes.show', $class) }}"
                                                    />
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="pencil"
                                                        href="{{ route('classes.edit', $class) }}"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <div class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                            <flux:icon.calendar-days class="h-12 w-12 mx-auto mb-4 text-gray-300" />
                            <p class="text-gray-600">No classes found</p>
                            @if($search || $courseFilter || $statusFilter || $classTypeFilter || $categoryFilter)
                                <flux:button
                                    wire:click="clearFilters"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-2"
                                >
                                    Clear filters
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>
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