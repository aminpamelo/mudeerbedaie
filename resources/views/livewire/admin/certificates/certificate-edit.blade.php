<?php

use App\Models\Certificate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Certificate $certificate;

    // Form fields
    public string $name = '';

    public string $description = '';

    public string $size = 'a4';

    public string $orientation = 'landscape';

    public int $width = 1122;

    public int $height = 793;

    public string $backgroundColor = '#ffffff';

    public $backgroundImage = null;

    public string $backgroundImagePath = '';

    public array $elements = [];

    public string $status = 'draft';

    // UI state
    public ?int $selectedElementIndex = null;

    public string $activeTab = 'design';

    // Assignment properties
    public string $assignmentType = 'course';

    public ?int $selectedCourseId = null;

    public ?int $selectedClassId = null;

    public bool $isDefaultAssignment = false;

    public string $searchCourses = '';

    public string $searchClasses = '';

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate;

        // Load existing data
        $this->name = $certificate->name;
        $this->description = $certificate->description ?? '';
        $this->size = $certificate->size;
        $this->orientation = $certificate->orientation;
        $this->width = $certificate->width;
        $this->height = $certificate->height;
        $this->backgroundColor = $certificate->background_color;
        $this->backgroundImagePath = $certificate->background_image ?? '';
        $this->elements = $certificate->elements ?? [];
        $this->status = $certificate->status;
    }

    public function updatedSize(): void
    {
        $this->updateDimensions();
    }

    public function updatedOrientation(): void
    {
        $this->updateDimensions();
    }

    protected function updateDimensions(): void
    {
        if ($this->size === 'a4') {
            if ($this->orientation === 'portrait') {
                $this->width = 793;
                $this->height = 1122;
            } else {
                $this->width = 1122;
                $this->height = 793;
            }
        } else {
            if ($this->orientation === 'portrait') {
                $this->width = 816;
                $this->height = 1056;
            } else {
                $this->width = 1056;
                $this->height = 816;
            }
        }
    }

    public function uploadBackground(): void
    {
        $this->validate([
            'backgroundImage' => 'required|image|max:5120',
        ]);

        $path = $this->backgroundImage->store('certificates/backgrounds', 'public');
        $this->backgroundImagePath = $path;
        $this->backgroundImage = null;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Background image uploaded successfully.',
        ]);
    }

    public function removeBackground(): void
    {
        $this->backgroundImagePath = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Background image removed.',
        ]);
    }

    public function addTextElement(): void
    {
        $this->elements[] = [
            'id' => uniqid('text_'),
            'type' => 'text',
            'content' => 'Double click to edit',
            'x' => 100,
            'y' => 100,
            'width' => 400,
            'height' => 50,
            'fontSize' => 24,
            'fontFamily' => 'Arial, sans-serif',
            'fontWeight' => 'normal',
            'color' => '#000000',
            'textAlign' => 'center',
            'lineHeight' => 1.2,
            'letterSpacing' => 0,
            'rotation' => 0,
            'opacity' => 1,
        ];

        $this->selectedElementIndex = count($this->elements) - 1;
    }

    public function addDynamicElement(string $field): void
    {
        $this->elements[] = [
            'id' => uniqid('dynamic_'),
            'type' => 'dynamic',
            'field' => $field,
            'x' => 100,
            'y' => 200,
            'width' => 400,
            'height' => 40,
            'fontSize' => 20,
            'fontFamily' => 'Georgia, serif',
            'fontWeight' => 'normal',
            'color' => '#333333',
            'textAlign' => 'center',
            'prefix' => '',
            'suffix' => '',
            'lineHeight' => 1.2,
            'rotation' => 0,
            'opacity' => 1,
        ];

        $this->selectedElementIndex = count($this->elements) - 1;
    }

    public function addShapeElement(string $shape): void
    {
        $this->elements[] = [
            'id' => uniqid('shape_'),
            'type' => 'shape',
            'shape' => $shape,
            'x' => 100,
            'y' => 300,
            'width' => $shape === 'circle' ? 100 : 400,
            'height' => $shape === 'line' ? 2 : 100,
            'borderWidth' => $shape === 'line' ? 2 : 1,
            'borderColor' => '#000000',
            'borderStyle' => 'solid',
            'fillColor' => 'transparent',
            'rotation' => 0,
            'opacity' => 1,
        ];

        $this->selectedElementIndex = count($this->elements) - 1;
    }

    public function selectElement(int $index): void
    {
        $this->selectedElementIndex = $index;
    }

    public function deleteElement(int $index): void
    {
        unset($this->elements[$index]);
        $this->elements = array_values($this->elements);
        $this->selectedElementIndex = null;
    }

    public function moveElementUp(int $index): void
    {
        if ($index > 0) {
            $temp = $this->elements[$index];
            $this->elements[$index] = $this->elements[$index - 1];
            $this->elements[$index - 1] = $temp;
            $this->selectedElementIndex = $index - 1;
        }
    }

    public function moveElementDown(int $index): void
    {
        if ($index < count($this->elements) - 1) {
            $temp = $this->elements[$index];
            $this->elements[$index] = $this->elements[$index + 1];
            $this->elements[$index + 1] = $temp;
            $this->selectedElementIndex = $index + 1;
        }
    }

    public function save(?string $saveStatus = null): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'size' => 'required|in:a4,letter',
            'orientation' => 'required|in:portrait,landscape',
            'backgroundColor' => 'required|string',
        ]);

        $this->certificate->update([
            'name' => $this->name,
            'description' => $this->description,
            'size' => $this->size,
            'orientation' => $this->orientation,
            'width' => $this->width,
            'height' => $this->height,
            'background_color' => $this->backgroundColor,
            'background_image' => $this->backgroundImagePath,
            'elements' => $this->elements,
            'status' => $saveStatus ?? $this->status,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate template updated successfully.',
        ]);

        $this->redirect(route('certificates.index'));
    }

    public function assignToCourse(): void
    {
        $this->validate([
            'selectedCourseId' => 'required|exists:courses,id',
        ]);

        $course = \App\Models\Course::find($this->selectedCourseId);

        if ($this->certificate->isAssignedToCourse($course)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate is already assigned to this course.',
            ]);

            return;
        }

        $this->certificate->assignToCourse($course, $this->isDefaultAssignment);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate assigned to course successfully.',
        ]);

        $this->reset(['selectedCourseId', 'isDefaultAssignment', 'searchCourses']);
    }

    public function assignToClass(): void
    {
        $this->validate([
            'selectedClassId' => 'required|exists:classes,id',
        ]);

        $class = \App\Models\ClassModel::find($this->selectedClassId);

        if ($this->certificate->isAssignedToClass($class)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate is already assigned to this class.',
            ]);

            return;
        }

        $this->certificate->assignToClass($class, $this->isDefaultAssignment);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate assigned to class successfully.',
        ]);

        $this->reset(['selectedClassId', 'isDefaultAssignment', 'searchClasses']);
    }

    public function unassignCourse(int $courseId): void
    {
        $this->certificate->courses()->detach($courseId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate unassigned from course successfully.',
        ]);
    }

    public function unassignClass(int $classId): void
    {
        $this->certificate->classes()->detach($classId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate unassigned from class successfully.',
        ]);
    }

    public function toggleDefaultCourse(int $courseId): void
    {
        $assignment = $this->certificate->courses()->where('course_id', $courseId)->first();

        if ($assignment) {
            $this->certificate->courses()->updateExistingPivot($courseId, [
                'is_default' => ! $assignment->pivot->is_default,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Default status updated successfully.',
            ]);
        }
    }

    public function toggleDefaultClass(int $classId): void
    {
        $assignment = $this->certificate->classes()->where('class_id', $classId)->first();

        if ($assignment) {
            $this->certificate->classes()->updateExistingPivot($classId, [
                'is_default' => ! $assignment->pivot->is_default,
            ]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Default status updated successfully.',
            ]);
        }
    }

    public function with(): array
    {
        $assignedCourses = $this->certificate->courses()
            ->withCount('students')
            ->get();

        $assignedClasses = $this->certificate->classes()
            ->with('course')
            ->withCount('activeStudents')
            ->get();

        $availableCourses = \App\Models\Course::query()
            ->when($this->searchCourses, fn ($q) => $q->where('name', 'like', "%{$this->searchCourses}%"))
            ->whereNotIn('id', $this->certificate->courses()->pluck('courses.id'))
            ->get();

        $availableClasses = \App\Models\ClassModel::query()
            ->with('course')
            ->when($this->searchClasses, function ($q) {
                $q->where('title', 'like', "%{$this->searchClasses}%")
                    ->orWhereHas('course', fn ($q) => $q->where('name', 'like', "%{$this->searchClasses}%"));
            })
            ->whereNotIn('id', $this->certificate->classes()->pluck('classes.id'))
            ->get();

        return [
            'assignedCourses' => $assignedCourses,
            'assignedClasses' => $assignedClasses,
            'availableCourses' => $availableCourses,
            'availableClasses' => $availableClasses,
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('certificates.index') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                <flux:icon name="arrow-left" class="h-5 w-5" />
            </a>
            <div>
                <flux:heading size="xl">Edit Certificate Template</flux:heading>
                <flux:text class="mt-2">{{ $certificate->name }}</flux:text>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-6">
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
            <button
                type="button"
                wire:click="$set('activeTab', 'design')"
                @class([
                    'px-4 py-2 font-medium text-sm transition-colors',
                    'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' => $activeTab === 'design',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'design',
                ])
            >
                Design
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'settings')"
                @class([
                    'px-4 py-2 font-medium text-sm transition-colors',
                    'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' => $activeTab === 'settings',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'settings',
                ])
            >
                Settings
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'assignments')"
                @class([
                    'px-4 py-2 font-medium text-sm transition-colors',
                    'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' => $activeTab === 'assignments',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'assignments',
                ])
            >
                Assignments
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'preview')"
                @class([
                    'px-4 py-2 font-medium text-sm transition-colors',
                    'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' => $activeTab === 'preview',
                    'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'preview',
                ])
            >
                Preview
            </button>
        </div>
    </div>

    @if($activeTab === 'design')
        <div class="flex gap-6 overflow-x-auto">
            {{-- Canvas Area (70%) --}}
            <div class="flex-1 min-w-0">
                {{-- Add Elements Toolbar --}}
                <div class="mb-4 flex gap-2">
                    <flux:button wire:click="addTextElement" variant="outline" size="sm" icon="document-text">
                        Add Text
                    </flux:button>

                    <flux:dropdown>
                        <flux:button variant="outline" size="sm" icon="variable">
                            Add Dynamic Field
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item wire:click="addDynamicElement('student_name')" icon="user">Student Name</flux:menu.item>
                            <flux:menu.item wire:click="addDynamicElement('course_name')" icon="academic-cap">Course Name</flux:menu.item>
                            <flux:menu.item wire:click="addDynamicElement('certificate_number')" icon="hashtag">Certificate Number</flux:menu.item>
                            <flux:menu.item wire:click="addDynamicElement('issue_date')" icon="calendar">Issue Date</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    <flux:dropdown>
                        <flux:button variant="outline" size="sm" icon="square-2-stack">
                            Add Shape
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item wire:click="addShapeElement('rectangle')" icon="stop">Rectangle</flux:menu.item>
                            <flux:menu.item wire:click="addShapeElement('circle')" icon="circle-stack">Circle</flux:menu.item>
                            <flux:menu.item wire:click="addShapeElement('line')">Line</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">Canvas</flux:heading>
                        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <span>{{ $width }} × {{ $height }}px</span>
                        </div>
                    </div>

                    {{-- Canvas --}}
                    <div class="rounded border border-gray-300 bg-gray-50 p-4 dark:border-gray-600 dark:bg-gray-900" style="overflow: auto;">
                        <div class="flex justify-center w-full min-h-[600px]">
                            <div
                                x-data="{ scale: 1 }"
                                x-init="
                                    const updateScale = () => {
                                        const containerWidth = $el.parentElement.clientWidth - 32;
                                        const canvasWidth = {{ $width }};
                                        scale = Math.min(1, containerWidth / canvasWidth);
                                    };
                                    updateScale();
                                    window.addEventListener('resize', updateScale);
                                "
                                :style="`transform: scale(${scale}); transform-origin: top center;`"
                                style="margin: 0 auto;"
                            >
                                <div
                                    class="relative"
                                    style="width: {{ $width }}px; height: {{ $height }}px; background-color: {{ $backgroundColor }};"
                                >
                                    @if($backgroundImagePath)
                                        <img
                                            src="{{ Storage::url($backgroundImagePath) }}"
                                            alt="Certificate Background"
                                            class="absolute inset-0 pointer-events-none w-full h-full"
                                            style="object-fit: fill;"
                                        />
                                    @endif
                            @foreach($elements as $index => $element)
                                <div
                                    x-data="{
                                        isDragging: false,
                                        startX: 0,
                                        startY: 0,
                                        currentX: {{ $element['x'] }},
                                        currentY: {{ $element['y'] }},
                                        getScale() {
                                            return this.$el.closest('[x-data]')?.scale || 1;
                                        }
                                    }"
                                    x-on:mousedown="
                                        isDragging = true;
                                        const scale = getScale();
                                        const rect = $el.parentElement.getBoundingClientRect();
                                        startX = ($event.clientX - rect.left) / scale;
                                        startY = ($event.clientY - rect.top) / scale;
                                        $wire.selectElement({{ $index }});
                                    "
                                    x-on:mousemove.window="
                                        if (isDragging) {
                                            const scale = getScale();
                                            const rect = $el.parentElement.getBoundingClientRect();
                                            const mouseX = ($event.clientX - rect.left) / scale;
                                            const mouseY = ($event.clientY - rect.top) / scale;
                                            currentX = Math.max(0, Math.min({{ $width - $element['width'] }}, mouseX - (startX - currentX)));
                                            currentY = Math.max(0, Math.min({{ $height - $element['height'] }}, mouseY - (startY - currentY)));
                                        }
                                    "
                                    x-on:mouseup.window="
                                        if (isDragging) {
                                            isDragging = false;
                                            $wire.set('elements.{{ $index }}.x', Math.round(currentX));
                                            $wire.set('elements.{{ $index }}.y', Math.round(currentY));
                                        }
                                    "
                                    class="absolute cursor-move border-2 hover:border-blue-500 {{ $selectedElementIndex === $index ? 'border-blue-500 ring-2 ring-blue-300' : 'border-transparent' }}" :style="`
                                        left: ${currentX}px;
                                        top: ${currentY}px;
                                        width: {{ $element['width'] }}px;
                                        height: {{ $element['height'] }}px;
                                        transform: rotate({{ $element['rotation'] ?? 0 }}deg);
                                        opacity: {{ $element['opacity'] ?? 1 }};
                                    `"
                                >
                                    @if($element['type'] === 'text')
                                        <div style="
                                            font-size: {{ $element['fontSize'] }}px;
                                            font-family: {{ $element['fontFamily'] }};
                                            font-weight: {{ $element['fontWeight'] }};
                                            color: {{ $element['color'] }};
                                            text-align: {{ $element['textAlign'] }};
                                            line-height: {{ $element['lineHeight'] }};
                                        ">
                                            {{ $element['content'] }}
                                        </div>
                                    @elseif($element['type'] === 'dynamic')
                                        <div style="
                                            font-size: {{ $element['fontSize'] }}px;
                                            font-family: {{ $element['fontFamily'] }};
                                            font-weight: {{ $element['fontWeight'] }};
                                            color: {{ $element['color'] }};
                                            text-align: {{ $element['textAlign'] }};
                                        " class="italic text-gray-400">
                                            {{ $element['prefix'] }}{{ '{' . $element['field'] . '}' }}{{ $element['suffix'] }}
                                        </div>
                                    @elseif($element['type'] === 'shape')
                                        <div style="
                                            width: 100%;
                                            height: 100%;
                                            border-width: {{ $element['borderWidth'] }}px;
                                            border-color: {{ $element['borderColor'] }};
                                            border-style: {{ $element['borderStyle'] }};
                                            background-color: {{ $element['fillColor'] }};
                                            @if($element['shape'] === 'circle') border-radius: 50%; @endif
                                        "></div>
                                    @endif
                                </div>
                            @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Element Properties Sidebar (30%) --}}
            <div class="w-80 shrink-0 space-y-4">
                {{-- Layers Panel --}}
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <flux:heading size="base" class="mb-3">Layers</flux:heading>

                    <div class="space-y-1">
                        @forelse($elements as $index => $element)
                            <div
                                wire:click="selectElement({{ $index }})"
                                class="flex cursor-pointer items-center justify-between rounded p-2 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $selectedElementIndex === $index ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                            >
                                <div class="flex items-center gap-2">
                                    <flux:icon name="{{ match($element['type']) {
                                        'text' => 'document-text',
                                        'dynamic' => 'variable',
                                        'shape' => 'square-2-stack',
                                        default => 'cube'
                                    } }}" class="h-4 w-4" />
                                    <span class="text-sm">
                                        {{ match($element['type']) {
                                            'text' => Str::limit($element['content'], 15),
                                            'dynamic' => ucwords(str_replace('_', ' ', $element['field'])),
                                            'shape' => ucfirst($element['shape']),
                                            default => 'Element'
                                        } }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-xs text-gray-500 dark:text-gray-400">No elements yet</p>
                        @endforelse
                    </div>
                </div>

                {{-- Element Properties Panel --}}
                @if($selectedElementIndex !== null && isset($elements[$selectedElementIndex]))
                    @php $element = $elements[$selectedElementIndex]; @endphp

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <flux:heading size="base" class="mb-3">Properties</flux:heading>

                        <div class="space-y-4">
                            @if($element['type'] === 'text')
                                <flux:field>
                                    <flux:label>Content</flux:label>
                                    <flux:textarea wire:model.live="elements.{{ $selectedElementIndex }}.content" rows="2" />
                                </flux:field>
                            @endif

                            @if(in_array($element['type'], ['text', 'dynamic']))
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>Font Size</flux:label>
                                        <flux:input type="number" wire:model.live="elements.{{ $selectedElementIndex }}.fontSize" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Color</flux:label>
                                        <input type="color" wire:model.live="elements.{{ $selectedElementIndex }}.color" class="h-10 w-full rounded border" />
                                    </flux:field>
                                </div>

                                <flux:field>
                                    <flux:label>Font Weight</flux:label>
                                    <flux:select wire:model.live="elements.{{ $selectedElementIndex }}.fontWeight">
                                        <option value="normal">Normal</option>
                                        <option value="bold">Bold</option>
                                    </flux:select>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Text Align</flux:label>
                                    <flux:select wire:model.live="elements.{{ $selectedElementIndex }}.textAlign">
                                        <option value="left">Left</option>
                                        <option value="center">Center</option>
                                        <option value="right">Right</option>
                                    </flux:select>
                                </flux:field>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>X Position</flux:label>
                                    <flux:input type="number" wire:model.live="elements.{{ $selectedElementIndex }}.x" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Y Position</flux:label>
                                    <flux:input type="number" wire:model.live="elements.{{ $selectedElementIndex }}.y" />
                                </flux:field>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label>Width</flux:label>
                                    <flux:input type="number" wire:model.live="elements.{{ $selectedElementIndex }}.width" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Height</flux:label>
                                    <flux:input type="number" wire:model.live="elements.{{ $selectedElementIndex }}.height" />
                                </flux:field>
                            </div>

                            <div class="flex gap-2">
                                <flux:button wire:click="moveElementUp({{ $selectedElementIndex }})" variant="outline" size="sm" icon="arrow-up">
                                    Move Up
                                </flux:button>
                                <flux:button wire:click="moveElementDown({{ $selectedElementIndex }})" variant="outline" size="sm" icon="arrow-down">
                                    Move Down
                                </flux:button>
                                <flux:button wire:click="deleteElement({{ $selectedElementIndex }})" variant="danger" size="sm" icon="trash" class="ml-auto">
                                    Delete
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <flux:heading size="base" class="mb-3">Properties</flux:heading>
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400">Select an element to edit</p>
                    </div>
                @endif
            </div>
        </div>
    @elseif($activeTab === 'settings')
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <flux:heading size="lg" class="mb-6">Template Settings</flux:heading>

            <div class="space-y-6">
                <flux:field>
                    <flux:label>Template Name</flux:label>
                    <flux:input wire:model="name" placeholder="e.g., Course Completion Certificate" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" placeholder="Optional description for this template" rows="3" />
                </flux:field>

                <flux:field>
                    <flux:label>Paper Size</flux:label>
                    <flux:select wire:model.live="size">
                        <option value="a4">A4 (210mm × 297mm)</option>
                        <option value="letter">Letter (8.5" × 11")</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Orientation</flux:label>
                    <flux:select wire:model.live="orientation">
                        <option value="portrait">Portrait</option>
                        <option value="landscape">Landscape</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Background Color</flux:label>
                    <input type="color" wire:model.live="backgroundColor" class="h-10 w-full rounded border border-gray-300 dark:border-gray-600" />
                </flux:field>

                <flux:field>
                    <flux:label>Background Image</flux:label>
                    @if($backgroundImagePath)
                        <div class="mb-2">
                            <img src="{{ Storage::url($backgroundImagePath) }}" class="h-32 w-auto rounded border" />
                            <flux:button wire:click="removeBackground" variant="danger" size="sm" class="mt-2">
                                Remove Image
                            </flux:button>
                        </div>
                    @else
                        <flux:input type="file" wire:model="backgroundImage" accept="image/*" />
                        @if($backgroundImage)
                            <flux:button wire:click="uploadBackground" variant="primary" size="sm" class="mt-2">
                                Upload
                            </flux:button>
                        @endif
                    @endif
                    <flux:error name="backgroundImage" />
                </flux:field>
            </div>
        </div>
    @elseif($activeTab === 'assignments')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Assignment Form --}}
            <div>
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <flux:heading size="lg" class="mb-4">Add Assignment</flux:heading>

                    <div class="space-y-4">
                        {{-- Assignment Type Selector --}}
                        <div>
                            <flux:text class="text-sm font-medium mb-2">Assignment Type</flux:text>
                            <div class="space-y-2">
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" wire:model.live="assignmentType" value="course" class="mr-2">
                                    <span class="text-sm">Assign to Course</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input type="radio" wire:model.live="assignmentType" value="class" class="mr-2">
                                    <span class="text-sm">Assign to Class</span>
                                </label>
                            </div>
                        </div>

                        @if($assignmentType === 'course')
                            {{-- Course Assignment --}}
                            <div>
                                <flux:field>
                                    <flux:label>Search Course</flux:label>
                                    <flux:input
                                        wire:model.live.debounce.300ms="searchCourses"
                                        placeholder="Search by course name..."
                                        icon="magnifying-glass"
                                    />
                                </flux:field>
                            </div>

                            <div>
                                <flux:field>
                                    <flux:label>Select Course</flux:label>
                                    <flux:select wire:model="selectedCourseId">
                                        <option value="">Choose a course...</option>
                                        @foreach($availableCourses as $course)
                                            <option value="{{ $course->id }}">{{ $course->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            </div>

                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="isDefaultAssignment" class="mr-2">
                                    <span class="text-sm">Set as default certificate for this course</span>
                                </label>
                            </div>

                            <flux:button variant="primary" wire:click="assignToCourse" class="w-full">
                                Assign to Course
                            </flux:button>
                        @else
                            {{-- Class Assignment --}}
                            <div>
                                <flux:field>
                                    <flux:label>Search Class</flux:label>
                                    <flux:input
                                        wire:model.live.debounce.300ms="searchClasses"
                                        placeholder="Search by class or course name..."
                                        icon="magnifying-glass"
                                    />
                                </flux:field>
                            </div>

                            <div>
                                <flux:field>
                                    <flux:label>Select Class</flux:label>
                                    <flux:select wire:model="selectedClassId">
                                        <option value="">Choose a class...</option>
                                        @foreach($availableClasses as $class)
                                            <option value="{{ $class->id }}">
                                                {{ $class->title }} ({{ $class->course->name }})
                                            </option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>
                            </div>

                            <div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="isDefaultAssignment" class="mr-2">
                                    <span class="text-sm">Set as default certificate for this class</span>
                                </label>
                            </div>

                            <flux:button variant="primary" wire:click="assignToClass" class="w-full">
                                Assign to Class
                            </flux:button>
                        @endif
                    </div>
                </div>

                {{-- Assignment Summary --}}
                <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <flux:heading size="lg" class="mb-4">Assignment Summary</flux:heading>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Assigned Courses</flux:text>
                            <flux:badge variant="neutral">{{ $assignedCourses->count() }}</flux:badge>
                        </div>

                        <div class="flex justify-between items-center">
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Assigned Classes</flux:text>
                            <flux:badge variant="neutral">{{ $assignedClasses->count() }}</flux:badge>
                        </div>

                        <div class="flex justify-between items-center">
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Total Issued</flux:text>
                            <flux:badge variant="neutral">{{ $certificate->issues()->count() }}</flux:badge>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Assigned Courses & Classes --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Assigned Courses --}}
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Assigned Courses ({{ $assignedCourses->count() }})</flux:heading>
                    </div>

                    @if($assignedCourses->isEmpty())
                        <div class="text-center py-8">
                            <flux:icon name="document-text" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                            <flux:text class="text-gray-500 dark:text-gray-400">No courses assigned yet</flux:text>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($assignedCourses as $course)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <flux:heading size="sm">{{ $course->name }}</flux:heading>
                                            @if($course->pivot->is_default)
                                                <flux:badge variant="primary" size="sm">Default</flux:badge>
                                            @endif
                                        </div>
                                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $course->students_count }} student(s) enrolled
                                        </flux:text>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="toggleDefaultCourse({{ $course->id }})"
                                        >
                                            <flux:icon :name="$course->pivot->is_default ? 'star' : 'star'" class="w-4 h-4 {{ $course->pivot->is_default ? 'text-yellow-500' : '' }}" />
                                        </flux:button>
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="unassignCourse({{ $course->id }})"
                                            wire:confirm="Are you sure you want to unassign this certificate from the course?"
                                        >
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Assigned Classes --}}
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Assigned Classes ({{ $assignedClasses->count() }})</flux:heading>
                    </div>

                    @if($assignedClasses->isEmpty())
                        <div class="text-center py-8">
                            <flux:icon name="user-group" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                            <flux:text class="text-gray-500 dark:text-gray-400">No classes assigned yet</flux:text>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($assignedClasses as $class)
                                <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <flux:heading size="sm">{{ $class->title }}</flux:heading>
                                            @if($class->pivot->is_default)
                                                <flux:badge variant="primary" size="sm">Default</flux:badge>
                                            @endif
                                        </div>
                                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $class->course->name }} • {{ $class->active_students_count }} student(s)
                                        </flux:text>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="toggleDefaultClass({{ $class->id }})"
                                        >
                                            <flux:icon :name="$class->pivot->is_default ? 'star' : 'star'" class="w-4 h-4 {{ $class->pivot->is_default ? 'text-yellow-500' : '' }}" />
                                        </flux:button>
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="unassignClass({{ $class->id }})"
                                            wire:confirm="Are you sure you want to unassign this certificate from the class?"
                                        >
                                            <flux:icon name="x-mark" class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @elseif($activeTab === 'preview')
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <flux:heading size="lg" class="mb-6">Preview with Sample Data</flux:heading>

            <div class="overflow-auto rounded border border-gray-300 bg-gray-50 p-8 dark:border-gray-600 dark:bg-gray-900">
                <div class="flex justify-center w-full">
                    <div
                        x-data="{ scale: 1 }"
                        x-init="
                            const updateScale = () => {
                                const containerWidth = $el.parentElement.clientWidth - 64;
                                const canvasWidth = {{ $width }};
                                scale = Math.min(1, containerWidth / canvasWidth);
                            };
                            updateScale();
                            window.addEventListener('resize', updateScale);
                        "
                        :style="`transform: scale(${scale}); transform-origin: top center;`"
                        style="margin: 0 auto;"
                    >
                        <div
                            class="relative"
                            style="width: {{ $width }}px; height: {{ $height }}px; background-color: {{ $backgroundColor }};"
                        >
                            @if($backgroundImagePath)
                                <img
                                    src="{{ Storage::url($backgroundImagePath) }}"
                                    alt="Certificate Background"
                                    class="absolute inset-0 w-full h-full"
                                    style="object-fit: fill;"
                                />
                            @endif
                    @foreach($elements as $element)
                        @php
                            $sampleData = [
                                'student_name' => 'John Doe',
                                'course_name' => 'Web Development Bootcamp',
                                'certificate_number' => 'CERT-2025-0001',
                                'issue_date' => now()->format('F j, Y'),
                            ];
                        @endphp

                        <div
                            class="absolute"
                            style="
                                left: {{ $element['x'] }}px;
                                top: {{ $element['y'] }}px;
                                width: {{ $element['width'] }}px;
                                height: {{ $element['height'] }}px;
                                transform: rotate({{ $element['rotation'] ?? 0 }}deg);
                                opacity: {{ $element['opacity'] ?? 1 }};
                            "
                        >
                            @if($element['type'] === 'text')
                                <div style="
                                    font-size: {{ $element['fontSize'] }}px;
                                    font-family: {{ $element['fontFamily'] }};
                                    font-weight: {{ $element['fontWeight'] }};
                                    color: {{ $element['color'] }};
                                    text-align: {{ $element['textAlign'] }};
                                ">
                                    {{ $element['content'] }}
                                </div>
                            @elseif($element['type'] === 'dynamic')
                                <div style="
                                    font-size: {{ $element['fontSize'] }}px;
                                    font-family: {{ $element['fontFamily'] }};
                                    font-weight: {{ $element['fontWeight'] }};
                                    color: {{ $element['color'] }};
                                    text-align: {{ $element['textAlign'] }};
                                ">
                                    {{ $element['prefix'] }}{{ $sampleData[$element['field']] ?? 'Sample Data' }}{{ $element['suffix'] }}
                                </div>
                            @elseif($element['type'] === 'shape')
                                <div style="
                                    width: 100%;
                                    height: 100%;
                                    border-width: {{ $element['borderWidth'] }}px;
                                    border-color: {{ $element['borderColor'] }};
                                    border-style: {{ $element['borderStyle'] }};
                                    background-color: {{ $element['fillColor'] }};
                                    @if($element['shape'] === 'circle') border-radius: 50%; @endif
                                "></div>
                            @endif
                        </div>
                    @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="mt-6 flex justify-end gap-2">
        <flux:button href="{{ route('certificates.index') }}" variant="ghost">
            Cancel
        </flux:button>
        <flux:button wire:click="save" variant="outline">
            Save Changes
        </flux:button>
        @if($certificate->isDraft())
            <flux:button wire:click="save('active')" variant="primary">
                Save & Activate
            </flux:button>
        @endif
    </div>
</div>
