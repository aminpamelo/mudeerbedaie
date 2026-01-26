<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;

class AddTagHandler implements ActionHandlerInterface
{
    public function execute(Student $student, array $config): array
    {
        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag_name'] ?? null;

        if (! $tagId && ! $tagName) {
            return [
                'success' => false,
                'message' => 'No tag specified',
            ];
        }

        try {
            // Find or create tag
            if ($tagId) {
                $tag = Tag::find($tagId);
            } else {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
            }

            if (! $tag) {
                return [
                    'success' => false,
                    'message' => 'Tag not found',
                ];
            }

            // Check if student already has the tag
            if ($student->tags()->where('tag_id', $tag->id)->exists()) {
                return [
                    'success' => true,
                    'message' => 'Tag already assigned',
                    'data' => ['tag_id' => $tag->id],
                ];
            }

            // Add the tag
            $student->tags()->attach($tag->id, [
                'created_at' => now(),
            ]);

            Log::info("Added tag {$tag->name} to student {$student->id}");

            return [
                'success' => true,
                'message' => "Tag '{$tag->name}' added successfully",
                'data' => ['tag_id' => $tag->id],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to add tag to student {$student->id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to add tag: '.$e->getMessage(),
            ];
        }
    }
}
