<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\Notice;
use App\Models\ParentStudent;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Result;
use App\Models\School;
use App\Models\User;
use App\Notifications\ParentWeeklyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendParentWeeklyDigest extends Command
{
    protected $signature = 'digest:parents-weekly';

    protected $description = 'Send weekly digest emails to parents summarising their children\'s activity';

    public function handle(): int
    {
        $weekStart = Carbon::now()->subWeek()->startOfDay();
        $weekEnd = Carbon::now();

        $schools = School::where('is_active', true)->get();
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($schools as $school) {
            $portalUrl = $this->resolvePortalUrl($school);

            // Get all active parents with email in this school
            $parents = User::withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->where('role', 'parent')
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();

            if ($parents->isEmpty()) {
                continue;
            }

            // Pre-fetch school-wide notices count for the week
            $noticesCount = Notice::withoutGlobalScope('tenant')
                ->where('school_id', $school->id)
                ->where('is_published', true)
                ->where('published_at', '>=', $weekStart)
                ->where('published_at', '<=', $weekEnd)
                ->count();

            foreach ($parents as $parent) {
                $digest = $this->buildDigestForParent($parent, $school, $weekStart, $weekEnd, $noticesCount, $portalUrl);

                if ($digest === null) {
                    $skippedCount++;

                    continue;
                }

                $parent->notify($digest);
                $sentCount++;
            }
        }

        $this->info("Weekly digest sent to {$sentCount} parents ({$skippedCount} skipped — no activity).");

        return self::SUCCESS;
    }

    private function buildDigestForParent(
        User $parent,
        School $school,
        Carbon $weekStart,
        Carbon $weekEnd,
        int $noticesCount,
        string $portalUrl,
    ): ?ParentWeeklyDigest {
        // Get linked children
        $childLinks = ParentStudent::withoutGlobalScopes()
            ->where('parent_id', $parent->id)
            ->where('school_id', $school->id)
            ->get();

        if ($childLinks->isEmpty()) {
            return null;
        }

        $childrenSummaries = [];
        $hasAnyActivity = false;

        foreach ($childLinks as $link) {
            $student = User::withoutGlobalScopes()
                ->with('studentProfile.class')
                ->find($link->student_id);

            if (! $student || ! $student->studentProfile) {
                continue;
            }

            $classId = $student->studentProfile->class_id;
            $className = $student->studentProfile->class?->name ?? 'Unknown';

            // Quiz attempts this week
            $quizAttempts = QuizAttempt::withoutGlobalScopes()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->whereIn('status', ['submitted', 'timed_out'])
                ->where('submitted_at', '>=', $weekStart)
                ->where('submitted_at', '<=', $weekEnd)
                ->with('quiz:id,title')
                ->get();

            $quizzes = [];
            foreach ($quizAttempts as $attempt) {
                $quizzes[] = [
                    'title' => $attempt->quiz?->title ?? 'Quiz',
                    'score' => $attempt->score.'/'.$attempt->total_points,
                    'percentage' => number_format((float) $attempt->percentage, 0),
                    'passed' => (bool) $attempt->passed,
                ];
            }

            // Game plays this week
            $gamePlays = GamePlay::withoutGlobalScopes()
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('completed', true)
                ->where('completed_at', '>=', $weekStart)
                ->where('completed_at', '<=', $weekEnd)
                ->with('game:id,title,game_type')
                ->get();

            $games = [];
            foreach ($gamePlays as $play) {
                $games[] = [
                    'title' => $play->game?->title ?? 'Game',
                    'type' => $play->game?->game_type ?? '',
                    'score' => $play->score.'/'.$play->max_score,
                    'percentage' => number_format((float) $play->percentage, 0),
                ];
            }

            // New assignments this week for the student's class
            $assignmentsCount = Assignment::withoutGlobalScope('tenant')
                ->where('school_id', $school->id)
                ->where('class_id', $classId)
                ->where('status', 'approved')
                ->where('created_at', '>=', $weekStart)
                ->where('created_at', '<=', $weekEnd)
                ->count();

            // New results this week
            $resultsCount = Result::withoutGlobalScope('tenant')
                ->where('student_id', $student->id)
                ->where('school_id', $school->id)
                ->where('status', 'approved')
                ->where('created_at', '>=', $weekStart)
                ->where('created_at', '<=', $weekEnd)
                ->count();

            if (! empty($quizzes) || ! empty($games) || $assignmentsCount > 0 || $resultsCount > 0) {
                $hasAnyActivity = true;
            }

            $childrenSummaries[] = [
                'name' => $student->name,
                'class' => $className,
                'quizzes' => $quizzes,
                'games' => $games,
                'assignments_count' => $assignmentsCount,
                'results_count' => $resultsCount,
            ];
        }

        // Skip if no children linked or zero activity across everything
        if (empty($childrenSummaries) || (! $hasAnyActivity && $noticesCount === 0)) {
            return null;
        }

        return new ParentWeeklyDigest(
            parentName: $parent->name,
            schoolName: $school->name,
            childrenSummaries: $childrenSummaries,
            noticesCount: $noticesCount,
            portalUrl: $portalUrl,
        );
    }

    private function resolvePortalUrl(School $school): string
    {
        if ($school->custom_domain) {
            return 'https://'.$school->custom_domain.'/portal/login';
        }

        return url('/portal/login');
    }
}
