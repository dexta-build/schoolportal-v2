<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * The end-of-term checklist: where every class is in
 *   enter scores → lock → generate report cards → approve → publish → (last term) promote,
 * and exactly what is still missing at each step, so the admin knows who to follow up with.
 */
class EndOfTermService
{
    public const STATUS_DONE = 'done';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_NOT_STARTED = 'not_started';

    /**
     * @return array{steps: list<array<string, mixed>>, classes: Collection, is_last_term: bool}
     */
    public function overview(int $schoolId, Term $term): array
    {
        $term->loadMissing(['session' => fn ($q) => $q->withoutGlobalScopes()]);
        $classes = $this->classProgress($schoolId, $term);

        $steps = [
            $this->scoresStep($classes),
            $this->lockStep($classes),
            $this->generateStep($classes, $term),
            $this->approveStep($classes, $term),
            $this->publishStep($classes, $term),
        ];

        $isLastTerm = ! Term::withoutGlobalScopes()
            ->where('session_id', $term->session_id)
            ->where('term_number', '>', $term->term_number)
            ->exists();

        if ($isLastTerm) {
            $steps[] = [
                'key' => 'promote',
                'title' => __('Promote students to their next class'),
                'description' => __('This is the last term of the session. Once report cards are published, move students up for the new session.'),
                'status' => self::STATUS_NOT_STARTED,
                'summary' => __('Do this when the new session begins.'),
                'items' => [],
                'action' => ['label' => __('Promote students'), 'url' => route('admin.promotions.index')],
            ];
        }

        return ['steps' => $steps, 'classes' => $classes, 'is_last_term' => $isLastTerm];
    }

    /**
     * Per-class numbers for the term, from a handful of grouped queries.
     */
    private function classProgress(int $schoolId, Term $term): Collection
    {
        $classes = SchoolClass::withoutGlobalScope('tenant')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->with(['teacher' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->orderBy('sort_order')
            ->get();

        $componentCount = ScoreComponent::withoutGlobalScopes()->where('school_id', $schoolId)->where('is_active', true)->count();

        $studentsByClass = StudentProfile::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereIn('class_id', $classes->pluck('id'))
            ->enrolledByTerm($term)
            ->get(['user_id', 'class_id'])
            ->groupBy('class_id')
            ->map(fn (Collection $rows): array => $rows->pluck('user_id')->map(fn ($id): int => (int) $id)->all());

        $subjectsByClass = ClassSubject::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereIn('class_id', $classes->pluck('id'))
            ->with(['subject' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'), 'teacher' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->get()
            ->groupBy('class_id');

        // Filled and unlocked scores per class/subject
        $scores = StudentSubjectScore::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('term_id', $term->id)
            ->whereNotNull('score')
            ->selectRaw('class_id, subject_id, COUNT(*) as filled, SUM(CASE WHEN is_locked = 0 THEN 1 ELSE 0 END) as unlocked')
            ->groupBy('class_id', 'subject_id')
            ->get()
            ->groupBy('class_id');

        // End-of-term report cards per class, by status
        $reports = StudentTermReport::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('term_id', $term->id)
            ->where('report_type', 'full_term')
            ->selectRaw('class_id, status, COUNT(*) as total, SUM(CASE WHEN needs_regeneration = 1 THEN 1 ELSE 0 END) as stale')
            ->groupBy('class_id', 'status')
            ->get()
            ->groupBy('class_id');

        return $classes->map(function (SchoolClass $class) use ($studentsByClass, $subjectsByClass, $scores, $reports, $componentCount): array {
            $studentCount = count($studentsByClass->get($class->id, []));
            $classScores = $scores->get($class->id, collect())->keyBy('subject_id');

            $subjects = $subjectsByClass->get($class->id, collect())->map(fn (ClassSubject $cs): array => [
                'name' => $cs->subject?->name ?? __('Subject'),
                'teacher' => $cs->teacher?->name ?? $class->teacher?->name,
                'expected' => $studentCount * $componentCount,
                'filled' => min((int) ($classScores->get($cs->subject_id)?->filled ?? 0), $studentCount * $componentCount),
            ])->values();

            $classReports = $reports->get($class->id, collect())->keyBy('status');
            $reportCount = fn (string $status): int => (int) ($classReports->get($status)?->total ?? 0);

            return [
                'id' => $class->id,
                'name' => $class->name,
                'teacher' => $class->teacher?->name,
                'students' => $studentCount,
                'subjects' => $subjects,
                'expected' => $subjects->sum('expected'),
                'filled' => $subjects->sum('filled'),
                'unlocked' => (int) $classScores->sum('unlocked'),
                'reports' => [
                    'total' => (int) $classReports->sum('total'),
                    'stale' => (int) $classReports->sum('stale'),
                    'draft' => $reportCount('draft'),
                    'pending_approval' => $reportCount('pending_approval'),
                    'approved' => $reportCount('approved'),
                    'published' => $reportCount('published'),
                ],
            ];
        })
            // Classes with no students this term have nothing to wrap up
            ->filter(fn (array $class): bool => $class['students'] > 0)
            ->values();
    }

    private function scoresStep(Collection $classes): array
    {
        $items = [];
        foreach ($classes as $class) {
            foreach ($class['subjects'] as $subject) {
                $missing = $subject['expected'] - $subject['filled'];
                if ($missing > 0) {
                    $items[] = [
                        'text' => __(':class · :subject — :count missing', [
                            'class' => $class['name'],
                            'subject' => $subject['name'],
                            'count' => trans_choice(':count score|:count scores', $missing, ['count' => $missing]),
                        ]),
                        'who' => $subject['teacher'],
                        'url' => route('admin.scores.index', ['class_id' => $class['id']]),
                    ];
                }
            }
        }

        $expected = $classes->sum('expected');
        $filled = $classes->sum('filled');

        return [
            'key' => 'scores',
            'title' => __('Enter all scores'),
            'description' => __('Every student needs a score for every subject and score component.'),
            'status' => $this->status($expected > 0 && $filled >= $expected, $filled > 0),
            'summary' => $expected > 0
                ? __(':filled of :expected scores entered', ['filled' => number_format($filled), 'expected' => number_format($expected)])
                : __('No subjects or score components are set up yet.'),
            'items' => $items,
            'action' => ['label' => __('Open Score Entry'), 'url' => route('admin.scores.index')],
        ];
    }

    private function lockStep(Collection $classes): array
    {
        $unlockedClasses = $classes->filter(fn (array $c): bool => $c['unlocked'] > 0);
        $hasScores = $classes->sum('filled') > 0;

        return [
            'key' => 'lock',
            'title' => __('Lock scores'),
            'description' => __('Locking makes scores final. After that, changes are made through recorded corrections.'),
            'status' => $this->status($hasScores && $unlockedClasses->isEmpty(), $hasScores && $unlockedClasses->count() < $classes->count()),
            'summary' => $hasScores
                ? trans_choice(':count class still has unlocked scores|:count classes still have unlocked scores', $unlockedClasses->count(), ['count' => $unlockedClasses->count()])
                : __('Enter scores first.'),
            'items' => $unlockedClasses->map(fn (array $c): array => [
                'text' => __(':class — :count not locked', ['class' => $c['name'], 'count' => trans_choice(':count score|:count scores', $c['unlocked'], ['count' => $c['unlocked']])]),
                'who' => null,
                'url' => route('admin.scores.index', ['class_id' => $c['id']]),
            ])->values()->all(),
            'action' => ['label' => __('Open Score Entry'), 'url' => route('admin.scores.index')],
        ];
    }

    private function generateStep(Collection $classes, Term $term): array
    {
        $items = [];
        foreach ($classes as $class) {
            $missing = max(0, $class['students'] - $class['reports']['total']);
            if ($missing > 0) {
                $items[] = $this->reportItem($class, $term, __(':class — :count not generated', ['class' => $class['name'], 'count' => trans_choice(':count report card|:count report cards', $missing, ['count' => $missing])]));
            } elseif ($class['reports']['stale'] > 0) {
                $items[] = $this->reportItem($class, $term, __(':class — :count out of date after score changes', ['class' => $class['name'], 'count' => trans_choice(':count report card|:count report cards', $class['reports']['stale'], ['count' => $class['reports']['stale']])]));
            }
        }

        $generated = $classes->sum(fn (array $c): int => $c['reports']['total']);

        return [
            'key' => 'generate',
            'title' => __('Generate report cards'),
            'description' => __('Report cards are made from the locked scores, with positions for the whole class.'),
            'status' => $this->status($classes->isNotEmpty() && $items === [], $generated > 0),
            'summary' => __(':count of :total report cards generated', ['count' => number_format($generated), 'total' => number_format($classes->sum('students'))]),
            'items' => $items,
            'action' => ['label' => __('Open Report Cards'), 'url' => route('admin.scores.reports', ['term_id' => $term->id, 'report_type' => 'full_term'])],
        ];
    }

    private function approveStep(Collection $classes, Term $term): array
    {
        $items = [];
        foreach ($classes as $class) {
            if ($class['reports']['draft'] > 0) {
                $items[] = $this->reportItem($class, $term, __(':class — :count waiting for the class teacher\'s comment', ['class' => $class['name'], 'count' => trans_choice(':count report card|:count report cards', $class['reports']['draft'], ['count' => $class['reports']['draft']])]), $class['teacher']);
            }
            if ($class['reports']['pending_approval'] > 0) {
                $items[] = $this->reportItem($class, $term, __(':class — :count waiting for your approval', ['class' => $class['name'], 'count' => trans_choice(':count report card|:count report cards', $class['reports']['pending_approval'], ['count' => $class['reports']['pending_approval']])]));
            }
        }

        $approvedOrPublished = $classes->sum(fn (array $c): int => $c['reports']['approved'] + $c['reports']['published']);
        $generated = $classes->sum(fn (array $c): int => $c['reports']['total']);

        return [
            'key' => 'approve',
            'title' => __('Approve report cards'),
            'description' => __('Class teachers add their comment and submit; you then approve.'),
            'status' => $this->status($generated > 0 && $items === [], $approvedOrPublished > 0 || $items !== []),
            'summary' => __(':count of :total approved', ['count' => number_format($approvedOrPublished), 'total' => number_format($generated)]),
            'items' => $items,
            'action' => ['label' => __('Open Report Cards'), 'url' => route('admin.scores.reports', ['term_id' => $term->id, 'report_type' => 'full_term'])],
        ];
    }

    private function publishStep(Collection $classes, Term $term): array
    {
        $items = [];
        foreach ($classes as $class) {
            if ($class['reports']['approved'] > 0) {
                $items[] = $this->reportItem($class, $term, __(':class — :count approved but not yet published', ['class' => $class['name'], 'count' => trans_choice(':count report card|:count report cards', $class['reports']['approved'], ['count' => $class['reports']['approved']])]));
            }
        }

        $published = $classes->sum(fn (array $c): int => $c['reports']['published']);
        $totalStudents = $classes->sum('students');

        return [
            'key' => 'publish',
            'title' => __('Publish report cards'),
            'description' => __('Publishing makes report cards visible to students and parents, who are notified.'),
            'status' => $this->status($totalStudents > 0 && $published >= $totalStudents, $published > 0),
            'summary' => __(':count of :total published', ['count' => number_format($published), 'total' => number_format($totalStudents)]),
            'items' => $items,
            'action' => ['label' => __('Open Report Cards'), 'url' => route('admin.scores.reports', ['term_id' => $term->id, 'report_type' => 'full_term'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $class
     * @return array{text: string, who: ?string, url: string}
     */
    private function reportItem(array $class, Term $term, string $text, ?string $who = null): array
    {
        return [
            'text' => $text,
            'who' => $who,
            'url' => route('admin.scores.reports', ['class_id' => $class['id'], 'term_id' => $term->id, 'report_type' => 'full_term']),
        ];
    }

    private function status(bool $done, bool $started): string
    {
        return $done ? self::STATUS_DONE : ($started ? self::STATUS_IN_PROGRESS : self::STATUS_NOT_STARTED);
    }
}
