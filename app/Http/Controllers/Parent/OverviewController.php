<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ExamAttempt;
use App\Models\GamePlay;
use App\Models\QuizAttempt;
use App\Models\Result;
use App\Models\StudentTermReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class OverviewController extends Controller
{
    /**
     * Results overview — show all children with their result counts and quick links.
     */
    public function results(): View
    {
        $children = $this->getChildrenWithStats('results');

        return view('parent.overview.results', compact('children'));
    }

    /**
     * Assignments overview — show all children with assignment info.
     */
    public function assignments(): View
    {
        $children = $this->getChildrenWithStats('assignments');

        return view('parent.overview.assignments', compact('children'));
    }

    /**
     * Quizzes overview — show all children with quiz stats.
     */
    public function quizzes(): View
    {
        $children = $this->getChildrenWithStats('quizzes');

        return view('parent.overview.quizzes', compact('children'));
    }

    /**
     * Games overview — show all children with game stats.
     */
    public function games(): View
    {
        $children = $this->getChildrenWithStats('games');

        return view('parent.overview.games', compact('children'));
    }

    /**
     * Report Cards overview — show all children with report card counts.
     */
    public function reportCards(): View
    {
        $children = $this->getChildrenWithStats('report_cards');

        return view('parent.overview.report-cards', compact('children'));
    }

    /**
     * CBT overview — show all children with CBT exam/assessment/assignment stats.
     */
    public function cbt(): View
    {
        // The school decides whether parents see CBT results (Settings → CBT Results for Parents)
        abort_unless(app('current.school')->featureEnabled('enable_cbt_results_for_parents'), 404);

        $children = $this->getChildrenWithStats('cbt');

        return view('parent.overview.cbt', compact('children'));
    }

    /**
     * Load children with relevant stats based on context.
     *
     * @return Collection<int, User>
     */
    private function getChildrenWithStats(string $context): Collection
    {
        $parent = auth()->user();
        $school = app('current.school');
        $childIds = $parent->children()->pluck('student_id');

        if ($childIds->isEmpty()) {
            return collect();
        }

        $children = User::whereIn('id', $childIds)
            ->where('school_id', $parent->school_id)
            ->with([
                'studentProfile.class:id,name,level_id',
                'studentProfile.class.level:id,name',
            ])
            ->get();

        // Attach stats based on context
        match ($context) {
            'results' => $this->attachResultStats($children),
            'assignments' => $this->attachAssignmentStats($children, $school),
            'quizzes' => $this->attachQuizStats($children),
            'games' => $this->attachGameStats($children),
            'cbt' => $this->attachCbtStats($children),
            'report_cards' => $this->attachReportCardStats($children),
        };

        return $children;
    }

    private function attachResultStats(Collection $children): void
    {
        $childIds = $children->pluck('id');

        $counts = Result::where('status', 'approved')
            ->whereIn('student_id', $childIds)
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $latestResults = Result::where('status', 'approved')
            ->whereIn('student_id', $childIds)
            ->with(['session:id,name', 'term:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->first());

        foreach ($children as $child) {
            $child->stat_results_count = (int) ($counts[$child->id] ?? 0);
            $child->stat_latest_result = $latestResults[$child->id] ?? null;
        }
    }

    private function attachAssignmentStats(Collection $children, $school): void
    {
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        foreach ($children as $child) {
            $classId = $child->studentProfile?->class_id;
            $count = 0;

            if ($classId) {
                $query = Assignment::where('status', 'approved')
                    ->where('class_id', $classId);

                if ($currentSession) {
                    $query->where('session_id', $currentSession->id);
                }
                if ($currentTerm) {
                    $query->where('term_id', $currentTerm->id);
                }

                $count = $query->count();
            }

            $child->stat_assignments_count = $count;
        }
    }

    private function attachQuizStats(Collection $children): void
    {
        $childIds = $children->pluck('id');

        $stats = QuizAttempt::whereIn('student_id', $childIds)
            ->whereIn('status', ['submitted', 'timed_out'])
            ->selectRaw('student_id, count(*) as taken, avg(percentage) as avg_pct, sum(case when passed = 1 then 1 else 0 end) as passed_count')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        foreach ($children as $child) {
            $qs = $stats[$child->id] ?? null;
            $child->stat_quizzes_taken = $qs ? (int) $qs->taken : 0;
            $child->stat_quiz_avg = $qs ? round((float) $qs->avg_pct, 1) : null;
            $child->stat_quiz_passed = $qs ? (int) $qs->passed_count : 0;
        }
    }

    private function attachGameStats(Collection $children): void
    {
        $childIds = $children->pluck('id');

        $stats = GamePlay::whereIn('student_id', $childIds)
            ->where('completed', true)
            ->selectRaw('student_id, count(*) as played, avg(percentage) as avg_pct, max(percentage) as best_pct')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        foreach ($children as $child) {
            $gs = $stats[$child->id] ?? null;
            $child->stat_games_played = $gs ? (int) $gs->played : 0;
            $child->stat_game_avg = $gs ? round((float) $gs->avg_pct, 1) : null;
            $child->stat_game_best = $gs ? round((float) $gs->best_pct, 1) : null;
        }
    }

    private function attachCbtStats(Collection $children): void
    {
        $childIds = $children->pluck('id');

        $stats = ExamAttempt::whereIn('student_id', $childIds)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded'])
            ->selectRaw('student_id, count(*) as taken, avg(percentage) as avg_pct, sum(case when passed = 1 then 1 else 0 end) as passed_count')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        foreach ($children as $child) {
            $cs = $stats[$child->id] ?? null;
            $child->stat_cbt_taken = $cs ? (int) $cs->taken : 0;
            $child->stat_cbt_avg = $cs ? round((float) $cs->avg_pct, 1) : null;
            $child->stat_cbt_passed = $cs ? (int) $cs->passed_count : 0;
        }
    }

    private function attachReportCardStats(Collection $children): void
    {
        $childIds = $children->pluck('id');

        $counts = StudentTermReport::whereIn('student_id', $childIds)
            ->where('status', 'published')
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $latestReports = StudentTermReport::whereIn('student_id', $childIds)
            ->where('status', 'published')
            ->with(['session:id,name', 'term:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->first());

        foreach ($children as $child) {
            $child->stat_report_cards_count = (int) ($counts[$child->id] ?? 0);
            $child->stat_latest_report = $latestReports[$child->id] ?? null;
        }
    }
}
