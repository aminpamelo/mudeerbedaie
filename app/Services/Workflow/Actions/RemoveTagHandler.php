<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;

class RemoveTagHandler implements ActionHandlerInterface
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
            // Find the tag
            if ($tagId) {
                $tag = Tag::find($tagId);
            } else {
                $tag = Tag::where('name', $tagName)->first();
            }

            if (! $tag) {
                return [
                    'success' => true,
                    'message' => 'Tag not found (nothing to remove)',
                ];
            }

            // Check if student has the tag
            if (! $student->tags()->where('tag_id', $tag->id)->exists()) {
                return [
                    'success' => true,
                    'message' => 'Student does not have this tag',
                ];
            }

            // Remove the tag
            $student->tags()->detach($tag->id);

            Log::info("Removed tag {$tag->name} from student {$student->id}");

            return [
                'success' => true,
                'message' => "Tag '{$tag->name}' removed successfully",
                'data' => ['tag_id' => $tag->id],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to remove tag from student {$student->id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to remove tag: '.$e->getMessage(),
            ];
        }
    }
}
