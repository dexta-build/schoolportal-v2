<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\ScoreAggregationService;
use Illuminate\View\View;

class ChildCbtResultController extends Controller
{
    public function __construct(
        private readonly ScoreAggregationService $scoreService,
    ) {}

    public function index(User $child): View
    {
        // The school decides whether parents see CBT results (Settings → CBT Results for Parents)
        abort_unless(app('current.school')->featureEnabled('enable_cbt_results_for_parents'), 404);

        $parent = auth()->user();
        $child->loadMissing('studentProfile.class');

        // Verify parent-child link
        if (! $parent->children()->where('student_id', $child->id)->exists()) {
            abort(403);
        }

        $attempts = ExamAttempt::with([
            'exam:id,title,category,class_id,subject_id,passing_score,max_score',
            'exam.class:id,name,level_id',
            'exam.subject:id,name',
        ])
            ->where('student_id', $child->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded'])
            ->orderByDesc('submitted_at')
            ->paginate(15);

        // Build grade map for each attempt
        $grades = $attempts->getCollection()->mapWithKeys(fn (ExamAttempt $a) => [
            $a->id => $a->percentage !== null
                ? $this->scoreService->getGrade($parent->school_id, (float) $a->percentage, $a->exam?->class?->level_id)
                : null,
        ]);

        return view('parent.cbt.results', compact('child', 'attempts', 'grades'));
    }
}
