<?php

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Url;
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

    #[Url(as: 'tab')]
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

    public function downloadPreviewPdf()
    {
        $previewData = $this->certificate->generatePreview();

        $html = view('certificates.pdf-template', [
            'certificate' => $this->certificate,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundColor' => $this->backgroundColor,
            'backgroundImage' => $this->backgroundImagePath,
            'elements' => $this->elements,
            'data' => $previewData,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper(
                $this->size === 'letter' ? 'letter' : 'a4',
                $this->orientation
            )
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'certificate-preview.pdf');
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
            ->limit(20)
            ->get();

        $availableClasses = \App\Models\ClassModel::query()
            ->with('course')
            ->when($this->searchClasses, function ($q) {
                $q->where('title', 'like', "%{$this->searchClasses}%")
                    ->orWhereHas('course', fn ($q) => $q->where('name', 'like', "%{$this->searchClasses}%"));
            })
            ->whereNotIn('id', $this->certificate->classes()->pluck('classes.id'))
            ->limit(20)
            ->get();

        return [
            'assignedCourses' => $assignedCourses,
            'assignedClasses' => $assignedClasses,
            'availableCourses' => $availableCourses,
            'availableClasses' => $availableClasses,
        ];
    }
}; ?>

@push('styles')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700|playfair-display:400,700|lora:400,700|poppins:400,500,600,700|raleway:400,500,600,700|roboto:400,500,700|open-sans:400,600,700|nunito:400,600,700|merriweather:400,700|oswald:400,500,600,700|dancing-script:400,700|amiri:400,700|scheherazade-new:400,700|noto-sans-arabic:400,700" rel="stylesheet" />
@endpush

<div>
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('certificates.index') }}" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                <flux:icon name="arrow-left" class="h-5 w-5" />
            </a>
            <div>
                <flux:heading size="xl">Edit Certificate Template</flux:heading>
                <flux:text class="mt-2">{{ $certificate->name }}</flux:text>
            </div>
        </div>
        <flux:button variant="outline" wire:click="downloadPreviewPdf" icon="arrow-down-tray">
            Download Preview
        </flux:button>
    </div>

    {{-- Tabs --}}
    <div class="mb-6">
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
            <button
                type="button"
                wire:click="$set('activeTab', 'design')"
                class="px-4 py-2 font-medium text-sm transition-colors {{ $activeTab === 'design' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                Design
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'settings')"
                class="px-4 py-2 font-medium text-sm transition-colors {{ $activeTab === 'settings' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                Settings
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'assignments')"
                class="px-4 py-2 font-medium text-sm transition-colors {{ $activeTab === 'assignments' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
            >
                Assignments
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'preview')"
                class="px-4 py-2 font-medium text-sm transition-colors {{ $activeTab === 'preview' ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}"
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
                                    wire:key="element-{{ $index }}"
                                    x-data="{
                                        isDragging: false,
                                        isHovered: false,
                                        offsetX: 0,
                                        offsetY: 0,
                                        currentX: {{ $element['x'] }},
                                        currentY: {{ $element['y'] }},
                                    }"
                                    x-on:mouseenter="isHovered = true"
                                    x-on:mouseleave="isHovered = false"
                                    x-init="
                                        $wire.$watch('elements.{{ $index }}.x', v => { if (!isDragging) currentX = v });
                                        $wire.$watch('elements.{{ $index }}.y', v => { if (!isDragging) currentY = v });
                                    "
                                    x-on:mousedown.stop.prevent="
                                        isDragging = true;
                                        const rect = $el.parentElement.getBoundingClientRect();
                                        const scale = rect.width / {{ $width }};
                                        const mouseX = ($event.clientX - rect.left) / scale;
                                        const mouseY = ($event.clientY - rect.top) / scale;
                                        offsetX = mouseX - currentX;
                                        offsetY = mouseY - currentY;
                                        $wire.selectElement({{ $index }});
                                    "
                                    x-on:mousemove.window="
                                        if (isDragging) {
                                            const rect = $el.parentElement.getBoundingClientRect();
                                            const scale = rect.width / {{ $width }};
                                            const mouseX = ($event.clientX - rect.left) / scale;
                                            const mouseY = ($event.clientY - rect.top) / scale;
                                            currentX = Math.max(0, Math.min({{ $width - (int)$element['width'] }}, mouseX - offsetX));
                                            currentY = Math.max(0, Math.min({{ $height - (int)$element['height'] }}, mouseY - offsetY));
                                        }
                                    "
                                    x-on:mouseup.window="
                                        if (isDragging) {
                                            isDragging = false;
                                            $wire.set('elements.{{ $index }}.x', Math.round(currentX));
                                            $wire.set('elements.{{ $index }}.y', Math.round(currentY));
                                        }
                                    "
                                    class="absolute cursor-move select-none" :style="`
                                        outline: 2px solid ${ {{ $selectedElementIndex === $index ? 'true' : 'false' }} ? '#3b82f6' : (isHovered ? '#3b82f6' : 'transparent') };
                                        ${ {{ $selectedElementIndex === $index ? 'true' : 'false' }} ? 'box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);' : '' }
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
                                            width: 100%;
                                            height: 100%;
                                            font-size: {{ $element['fontSize'] }}px;
                                            font-family: {{ $element['fontFamily'] }};
                                            font-weight: {{ $element['fontWeight'] }};
                                            color: {{ $element['color'] }};
                                            text-align: {{ $element['textAlign'] }};
                                            line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                            letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                            white-space: pre-wrap;
                                            word-wrap: break-word;
                                        ">{{ $element['content'] }}</div>
                                    @elseif($element['type'] === 'dynamic')
                                        <div style="
                                            width: 100%;
                                            height: 100%;
                                            font-size: {{ $element['fontSize'] }}px;
                                            font-family: {{ $element['fontFamily'] }};
                                            font-weight: {{ $element['fontWeight'] }};
                                            color: {{ $element['color'] }};
                                            text-align: {{ $element['textAlign'] }};
                                            line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                            letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                            white-space: pre-wrap;
                                            word-wrap: break-word;
                                            font-style: italic;
                                            opacity: 0.5;
                                        ">{{ $element['prefix'] }}{{ '{' . $element['field'] . '}' }}{{ $element['suffix'] }}</div>
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
                                    <flux:label>Font Family</flux:label>
                                    <flux:select wire:model.live="elements.{{ $selectedElementIndex }}.fontFamily">
                                        <optgroup label="Sans-Serif">
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="'Montserrat', sans-serif">Montserrat</option>
                                            <option value="'Poppins', sans-serif">Poppins</option>
                                            <option value="'Raleway', sans-serif">Raleway</option>
                                            <option value="'Roboto', sans-serif">Roboto</option>
                                            <option value="'Open Sans', sans-serif">Open Sans</option>
                                            <option value="'Nunito', sans-serif">Nunito</option>
                                            <option value="'Oswald', sans-serif">Oswald</option>
                                        </optgroup>
                                        <optgroup label="Serif">
                                            <option value="Georgia, serif">Georgia</option>
                                            <option value="'Playfair Display', serif">Playfair Display</option>
                                            <option value="'Lora', serif">Lora</option>
                                            <option value="'Merriweather', serif">Merriweather</option>
                                            <option value="'Times New Roman', serif">Times New Roman</option>
                                        </optgroup>
                                        <optgroup label="Script">
                                            <option value="'Dancing Script', cursive">Dancing Script</option>
                                        </optgroup>
                                        <optgroup label="Arabic">
                                            <option value="'Amiri', serif">Amiri</option>
                                            <option value="'Scheherazade New', serif">Scheherazade New</option>
                                            <option value="'Noto Sans Arabic', sans-serif">Noto Sans Arabic</option>
                                        </optgroup>
                                    </flux:select>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Font Weight</flux:label>
                                    <flux:select wire:model.live="elements.{{ $selectedElementIndex }}.fontWeight">
                                        <option value="normal">Normal</option>
                                        <option value="500">Medium</option>
                                        <option value="600">Semi Bold</option>
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
                            <flux:radio.group wire:model.live="assignmentType">
                                <flux:radio value="course" label="Assign to Course" />
                                <flux:radio value="class" label="Assign to Class" />
                            </flux:radio.group>
                        </div>

                        @if($assignmentType === 'course')
                            {{-- Course Assignment - Searchable Combobox --}}
                            <div
                                wire:key="combobox-course"
                                x-data="{
                                    open: false,
                                    selectedName: '',
                                    init() {
                                        this.$watch('$wire.selectedCourseId', (value) => {
                                            if (!value) this.selectedName = '';
                                        });
                                    },
                                    selectCourse(id, name) {
                                        $wire.set('selectedCourseId', id);
                                        this.selectedName = name;
                                        this.open = false;
                                        $wire.set('searchCourses', '');
                                    }
                                }"
                                @click.outside="open = false"
                            >
                                <flux:field>
                                    <flux:label>Select Course</flux:label>
                                    <div class="relative">
                                        {{-- Selected display / Search input --}}
                                        <div
                                            x-show="!open && selectedName"
                                            @click="open = true; $nextTick(() => $refs.courseSearch.focus())"
                                            class="flex items-center justify-between w-full cursor-pointer rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-700"
                                        >
                                            <span x-text="selectedName" class="truncate text-zinc-900 dark:text-zinc-100"></span>
                                            <flux:icon name="chevron-down" class="w-4 h-4 text-zinc-400 shrink-0 ml-2" />
                                        </div>

                                        <div x-show="open || !selectedName">
                                            <div class="relative">
                                                <flux:icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400 pointer-events-none" />
                                                <input
                                                    x-ref="courseSearch"
                                                    type="text"
                                                    wire:model.live.debounce.300ms="searchCourses"
                                                    @focus="open = true"
                                                    placeholder="Type to search courses..."
                                                    class="w-full rounded-lg border border-zinc-200 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                                                />
                                            </div>
                                        </div>

                                        {{-- Dropdown results --}}
                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1"
                                            class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
                                        >
                                            <ul class="max-h-48 overflow-y-auto py-1">
                                                @forelse($availableCourses as $course)
                                                    <li
                                                        wire:key="course-option-{{ $course->id }}"
                                                        @click="selectCourse({{ $course->id }}, '{{ addslashes($course->name) }}')"
                                                        class="flex items-center gap-2 cursor-pointer px-3 py-2 text-sm text-zinc-700 hover:bg-blue-50 hover:text-blue-700 dark:text-zinc-200 dark:hover:bg-zinc-600 dark:hover:text-white"
                                                    >
                                                        <flux:icon name="academic-cap" class="w-4 h-4 text-zinc-400 shrink-0" />
                                                        <span class="truncate">{{ $course->name }}</span>
                                                    </li>
                                                @empty
                                                    <li class="px-3 py-4 text-center text-sm text-zinc-400">
                                                        @if($searchCourses)
                                                            No courses found for "{{ $searchCourses }}"
                                                        @else
                                                            No available courses to assign
                                                        @endif
                                                    </li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </flux:field>
                            </div>

                            <flux:checkbox wire:model="isDefaultAssignment" label="Set as default certificate for this course" />

                            <flux:button variant="primary" wire:click="assignToCourse" class="w-full">
                                Assign to Course
                            </flux:button>
                        @else
                            {{-- Class Assignment - Searchable Combobox --}}
                            <div
                                wire:key="combobox-class"
                                x-data="{
                                    open: false,
                                    selectedName: '',
                                    init() {
                                        this.$watch('$wire.selectedClassId', (value) => {
                                            if (!value) this.selectedName = '';
                                        });
                                    },
                                    selectClass(id, name) {
                                        $wire.set('selectedClassId', id);
                                        this.selectedName = name;
                                        this.open = false;
                                        $wire.set('searchClasses', '');
                                    }
                                }"
                                @click.outside="open = false"
                            >
                                <flux:field>
                                    <flux:label>Select Class</flux:label>
                                    <div class="relative">
                                        {{-- Selected display / Search input --}}
                                        <div
                                            x-show="!open && selectedName"
                                            @click="open = true; $nextTick(() => $refs.classSearch.focus())"
                                            class="flex items-center justify-between w-full cursor-pointer rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-700"
                                        >
                                            <span x-text="selectedName" class="truncate text-zinc-900 dark:text-zinc-100"></span>
                                            <flux:icon name="chevron-down" class="w-4 h-4 text-zinc-400 shrink-0 ml-2" />
                                        </div>

                                        <div x-show="open || !selectedName">
                                            <div class="relative">
                                                <flux:icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400 pointer-events-none" />
                                                <input
                                                    x-ref="classSearch"
                                                    type="text"
                                                    wire:model.live.debounce.300ms="searchClasses"
                                                    @focus="open = true"
                                                    placeholder="Type to search classes..."
                                                    class="w-full rounded-lg border border-zinc-200 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-100 dark:placeholder:text-zinc-500"
                                                />
                                            </div>
                                        </div>

                                        {{-- Dropdown results --}}
                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1"
                                            class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
                                        >
                                            <ul class="max-h-48 overflow-y-auto py-1">
                                                @forelse($availableClasses as $class)
                                                    <li
                                                        wire:key="class-option-{{ $class->id }}"
                                                        @click="selectClass({{ $class->id }}, '{{ addslashes($class->title) }} ({{ addslashes($class->course->name) }})')"
                                                        class="flex items-center gap-2 cursor-pointer px-3 py-2 text-sm text-zinc-700 hover:bg-blue-50 hover:text-blue-700 dark:text-zinc-200 dark:hover:bg-zinc-600 dark:hover:text-white"
                                                    >
                                                        <flux:icon name="user-group" class="w-4 h-4 text-zinc-400 shrink-0" />
                                                        <div class="min-w-0">
                                                            <span class="block truncate">{{ $class->title }}</span>
                                                            <span class="block truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $class->course->name }}</span>
                                                        </div>
                                                    </li>
                                                @empty
                                                    <li class="px-3 py-4 text-center text-sm text-zinc-400">
                                                        @if($searchClasses)
                                                            No classes found for "{{ $searchClasses }}"
                                                        @else
                                                            No available classes to assign
                                                        @endif
                                                    </li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                </flux:field>
                            </div>

                            <flux:checkbox wire:model="isDefaultAssignment" label="Set as default certificate for this class" />

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
                    @php
                        $sampleData = [
                            'student_name' => 'John Doe',
                            'course_name' => 'Web Development Bootcamp',
                            'certificate_number' => 'CERT-2025-0001',
                            'issue_date' => now()->format('F j, Y'),
                        ];
                    @endphp
                    @foreach($elements as $element)
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
                                    width: 100%;
                                    height: 100%;
                                    font-size: {{ $element['fontSize'] }}px;
                                    font-family: {{ $element['fontFamily'] }};
                                    font-weight: {{ $element['fontWeight'] }};
                                    color: {{ $element['color'] }};
                                    text-align: {{ $element['textAlign'] }};
                                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                    white-space: pre-wrap;
                                    word-wrap: break-word;
                                ">{{ $element['content'] }}</div>
                            @elseif($element['type'] === 'dynamic')
                                <div style="
                                    width: 100%;
                                    height: 100%;
                                    font-size: {{ $element['fontSize'] }}px;
                                    font-family: {{ $element['fontFamily'] }};
                                    font-weight: {{ $element['fontWeight'] }};
                                    color: {{ $element['color'] }};
                                    text-align: {{ $element['textAlign'] }};
                                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                    white-space: pre-wrap;
                                    word-wrap: break-word;
                                ">{{ $element['prefix'] ?? '' }}{{ $sampleData[$element['field']] ?? 'Sample Data' }}{{ $element['suffix'] ?? '' }}</div>
                            @elseif($element['type'] === 'image')
                                @if(!empty($element['src']))
                                    <img
                                        src="{{ Storage::url($element['src']) }}"
                                        alt="{{ $element['alt'] ?? 'Image' }}"
                                        style="
                                            width: 100%;
                                            height: 100%;
                                            object-fit: {{ $element['objectFit'] ?? 'contain' }};
                                        "
                                    />
                                @endif
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

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = true;
            message = $event.detail.message || 'Operation successful';
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 4000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            x-show="type === 'success'"
            class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg"
        >
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'error'"
            class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
    </div>
</div>
