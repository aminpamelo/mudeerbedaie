<?php

namespace App\Services\Workflow\Actions;

use App\Models\LeadScoreHistory;
use App\Models\Student;
use App\Models\StudentLeadScore;
use Illuminate\Support\Facades\Log;

class AddScoreHandler implements ActionHandlerInterface
{
    public function execute(Student $student, array $config): array
    {
        $points = $config['points'] ?? 0;
        $reason = $config['reason'] ?? 'Workflow action';

        if ($points == 0) {
            return [
                'success' => true,
                'message' => 'No points to add',
            ];
        }

        try {
            // Get or create student lead score
            $leadScore = StudentLeadScore::firstOrCreate(
                ['student_id' => $student->id],
                ['total_score' => 0, 'engagement_score' => 0, 'fit_score' => 0]
            );

            $oldScore = $leadScore->total_score;
            $newScore = $oldScore + $points;

            // Update the score
            $leadScore->update([
                'total_score' => $newScore,
                'engagement_score' => $leadScore->engagement_score + $points,
            ]);

            // Log the score change
            LeadScoreHistory::create([
                'student_id' => $student->id,
                'points_change' => $points,
                'reason' => $reason,
                'source' => 'workflow',
                'previous_score' => $oldScore,
                'new_score' => $newScore,
            ]);

            Log::info("Added {$points} points to student {$student->id}", [
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => "Added {$points} points",
                'data' => [
                    'points_added' => $points,
                    'old_score' => $oldScore,
                    'new_score' => $newScore,
                ],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to add score for student {$student->id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to add score: '.$e->getMessage(),
            ];
        }
    }
}
