<?php

namespace App\Services\CRM;

use App\Models\Student;
use App\Models\StudentTag;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function __construct(
        private ContactActivityService $activityService
    ) {}

    public function createTag(string $name, string $color = '#6366f1', ?string $description = null, string $type = 'manual'): Tag
    {
        return Tag::create([
            'name' => $name,
            'color' => $color,
            'description' => $description,
            'type' => $type,
        ]);
    }

    public function updateTag(Tag $tag, array $data): Tag
    {
        $tag->update($data);

        return $tag->fresh();
    }

    public function deleteTag(Tag $tag): bool
    {
        return $tag->delete();
    }

    public function addTagToStudent(
        Student $student,
        Tag $tag,
        ?string $source = null,
        ?int $workflowId = null,
        ?int $appliedBy = null
    ): bool {
        // Check if tag already exists
        if ($student->tags()->where('tag_id', $tag->id)->exists()) {
            return false;
        }

        StudentTag::create([
            'student_id' => $student->id,
            'tag_id' => $tag->id,
            'applied_by' => $appliedBy ?? auth()->id(),
            'source' => $source,
            'workflow_id' => $workflowId,
        ]);

        // Log the activity
        $this->activityService->logTagAdded($student, $tag->name, $source);

        return true;
    }

    public function removeTagFromStudent(Student $student, Tag $tag): bool
    {
        $deleted = StudentTag::where('student_id', $student->id)
            ->where('tag_id', $tag->id)
            ->delete();

        if ($deleted) {
            $this->activityService->logTagRemoved($student, $tag->name);
        }

        return $deleted > 0;
    }

    public function syncStudentTags(Student $student, array $tagIds, ?string $source = null): void
    {
        $currentTagIds = $student->tags()->pluck('tags.id')->toArray();

        // Tags to add
        $tagsToAdd = array_diff($tagIds, $currentTagIds);
        foreach ($tagsToAdd as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $this->addTagToStudent($student, $tag, $source);
            }
        }

        // Tags to remove
        $tagsToRemove = array_diff($currentTagIds, $tagIds);
        foreach ($tagsToRemove as $tagId) {
            $tag = Tag::find($tagId);
            if ($tag) {
                $this->removeTagFromStudent($student, $tag);
            }
        }
    }

    public function getStudentTags(Student $student): Collection
    {
        return $student->tags()->get();
    }

    public function getStudentsWithTag(Tag $tag): Collection
    {
        return $tag->students()->get();
    }

    public function getStudentsWithAnyTags(array $tagIds): Collection
    {
        return Student::whereHas('tags', function ($query) use ($tagIds) {
            $query->whereIn('tags.id', $tagIds);
        })->get();
    }

    public function getStudentsWithAllTags(array $tagIds): Collection
    {
        $query = Student::query();

        foreach ($tagIds as $tagId) {
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('tags.id', $tagId);
            });
        }

        return $query->get();
    }

    public function getStudentsWithoutTags(array $tagIds): Collection
    {
        return Student::whereDoesntHave('tags', function ($query) use ($tagIds) {
            $query->whereIn('tags.id', $tagIds);
        })->get();
    }

    public function getAllTags(): Collection
    {
        return Tag::orderBy('name')->get();
    }

    public function getTagsByType(string $type): Collection
    {
        return Tag::where('type', $type)->orderBy('name')->get();
    }

    public function findOrCreateTag(string $name, string $type = 'manual'): Tag
    {
        return Tag::firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($name)],
            [
                'name' => $name,
                'type' => $type,
            ]
        );
    }

    public function getTagUsageStats(): array
    {
        return Tag::withCount('students')
            ->orderBy('students_count', 'desc')
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'type' => $tag->type,
                'student_count' => $tag->students_count,
            ])
            ->toArray();
    }
}
