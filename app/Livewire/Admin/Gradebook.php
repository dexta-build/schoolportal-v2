<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\ScoreAmendment;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\Term;
use App\Services\ScoreAggregationService;
use App\Services\ScoreCorrectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Gradebook extends Component
{
    // Set by the page, never by the browser — every permission check below depends on it
    #[Locked]
    public string $role = 'admin'; // 'admin' | 'teacher'

    public ?int $classId = null;

    public ?int $termId = null;

    // Show one subject at a time (picked automatically on phones, where the full grid is too wide)
    public ?int $subjectId = null;

    // Set by the browser on small screens: keep a subject picked when the class changes
    public bool $oneSubjectMode = false;

    // Rendered grid data (plain arrays for Livewire serialisation)
    public array $grid = [];

    public array $subjects = [];

    public array $components = [];

    public int $studentCount = 0;

    public int $filledCells = 0;

    public int $totalCells = 0;

    public int $cbtCells = 0;

    public int $lockedCells = 0;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /** @var array{count: int, by: ?string, at: ?string}|null Who locked this class's scores, and when */
    public ?array $lockInfo = null;

    public int $unlockedFilledCells = 0;

    /** @var array<int, array<string, mixed>> Corrections waiting for an admin decision */
    public array $pendingAmendments = [];

    public int $staleReportCount = 0;

    // Correction modal (amend for admins, request for teachers)
    public ?int $correctionScoreId = null;

    /** @var array<string, mixed>|null */
    public ?array $correctionCell = null;

    public string $correctionNewScore = '';

    public string $correctionReason = '';

    // Unlock modal
    public bool $showUnlock = false;

    public string $unlockReason = '';

    // Reject-correction modal
    public ?int $rejectingAmendmentId = null;

    public string $rejectReason = '';

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(string $role = 'admin'): void
    {
        $this->role = $role;

        $school = app('current.school');

        $currentTerm = Term::where('school_id', $school->id)
            ->where('is_current', true)
            ->first();

        if ($currentTerm) {
            $this->termId = $currentTerm->id;
        }

        // Opened from a link (e.g. the End of Term checklist): start on that class and term.
        // canAccessClass() inside loadGrid() still decides whether this user may see it.
        if (request()->integer('term_id')) {
            $this->termId = request()->integer('term_id');
        }
        if (request()->integer('class_id')) {
            $this->classId = request()->integer('class_id');
            $this->loadGrid();
        }
    }

    // ── Watchers ─────────────────────────────────────────────────────────────

    public function updatedClassId(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->loadGrid();
    }

    public function updatedTermId(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->loadGrid();
    }

    /**
     * Called from the browser on phones, where all subjects side by side are too wide to use.
     */
    public function useOneSubjectMode(): void
    {
        $this->oneSubjectMode = true;
        $this->pickVisibleSubject();
    }

    /**
     * Keep the chosen subject valid for the loaded class. In one-subject mode, fall back to the first subject.
     */
    private function pickVisibleSubject(): void
    {
        $ids = array_column($this->subjects, 'id');

        if ($this->subjectId && ! in_array($this->subjectId, $ids, true)) {
            $this->subjectId = null;
        }

        if ($this->oneSubjectMode && ! $this->subjectId && $ids !== []) {
            $this->subjectId = $ids[0];
        }
    }

    // ── Data Loading ─────────────────────────────────────────────────────────

    private function loadGrid(): void
    {
        $this->grid = [];
        $this->subjects = [];
        $this->components = [];
        $this->studentCount = 0;
        $this->filledCells = 0;
        $this->totalCells = 0;
        $this->cbtCells = 0;
        $this->lockedCells = 0;
        $this->lockInfo = null;
        $this->unlockedFilledCells = 0;
        $this->pendingAmendments = [];
        $this->staleReportCount = 0;

        if (! $this->classId || ! $this->termId) {
            return;
        }

        $school = app('current.school');

        // Admins: any class in their school. Teachers: only their own class.
        if (! $this->canAccessClass()) {
            $this->errorMessage = 'You do not have access to this class.';

            return;
        }

        /** @var ScoreAggregationService $service */
        $service = app(ScoreAggregationService::class);
        $data = $service->getClassScoreGrid($this->classId, $this->termId, $school->id);

        $this->subjects = $data['subjects']->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'short_name' => $s->short_name ?? mb_substr($s->name, 0, 4),
        ])->values()->all();

        $this->components = $data['components']->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'short_name' => $c->short_name,
            'max_score' => (float) $c->max_score,
            'weight' => (float) $c->weight,
        ])->values()->all();

        $this->grid = $data['students'];
        $this->studentCount = count($this->grid);

        $this->pickVisibleSubject();

        // Completion stats
        $numComps = count($this->components);
        $numSubjects = count($this->subjects);
        $this->totalCells = $this->studentCount * $numSubjects * $numComps;

        foreach ($this->grid as $row) {
            foreach ($this->subjects as $subj) {
                foreach ($this->components as $comp) {
                    $cell = $row['subjects'][$subj['id']]['components'][$comp['id']] ?? null;
                    if ($cell && $cell['score'] !== null) {
                        $this->filledCells++;
                        if (($cell['source_type'] ?? '') === 'cbt') {
                            $this->cbtCells++;
                        }
                        if ($cell['is_locked'] ?? false) {
                            $this->lockedCells++;
                        } else {
                            $this->unlockedFilledCells++;
                        }
                    }
                }
            }
        }

        $this->loadCorrectionState($school->id);
    }

    /**
     * Lock attribution, pending corrections, and report cards made out of date by score changes.
     */
    private function loadCorrectionState(int $schoolId): void
    {
        $latestLock = StudentSubjectScore::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('class_id', $this->classId)
            ->where('term_id', $this->termId)
            ->where('is_locked', true)
            ->whereNotNull('locked_at')
            ->with('lockedByUser:id,name')
            ->latest('locked_at')
            ->first();

        if ($this->lockedCells > 0) {
            $this->lockInfo = [
                'count' => $this->lockedCells,
                'by' => $latestLock?->lockedByUser?->name,
                'at' => $latestLock?->locked_at?->format('j M Y, g:i A'),
            ];
        }

        $this->staleReportCount = StudentTermReport::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('class_id', $this->classId)
            ->where('needs_regeneration', true)
            ->where(fn ($q) => $q->where('term_id', $this->termId)
                ->orWhere(fn ($session) => $session->where('report_type', 'session')
                    ->where('session_id', Term::withoutGlobalScopes()->whereKey($this->termId)->value('session_id'))))
            ->count();

        if ($this->isSchoolAdmin()) {
            $this->pendingAmendments = ScoreAmendment::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('class_id', $this->classId)
                ->where('term_id', $this->termId)
                ->pending()
                ->with(['student:id,name', 'subject:id,name', 'scoreComponent:id,short_name,max_score', 'requestedByUser:id,name'])
                ->oldest()
                ->get()
                ->map(fn (ScoreAmendment $a): array => [
                    'id' => $a->id,
                    'student' => $a->student?->name,
                    'subject' => $a->subject?->name,
                    'component' => $a->scoreComponent?->short_name,
                    'max' => $a->scoreComponent?->max_score,
                    'old' => $a->old_score === null ? null : $a->old_score + 0,
                    'new' => $a->new_score + 0,
                    'reason' => $a->reason,
                    'source' => $a->sourceLabel(),
                    'requested_by' => $a->requestedByUser?->name,
                    'when' => $a->created_at?->diffForHumans(),
                ])
                ->all();
        }
    }

    private function isSchoolAdmin(): bool
    {
        return $this->role === 'admin' && auth()->user()?->role === 'school_admin';
    }

    /**
     * classId and termId come from the browser, so both must belong to this school —
     * and for teachers, the class must be theirs.
     */
    private function canAccessClass(): bool
    {
        if (! $this->classId || ! $this->termId) {
            return false;
        }

        $schoolId = app('current.school')->id;

        if (! Term::withoutGlobalScopes()->where('school_id', $schoolId)->whereKey($this->termId)->exists()) {
            return false;
        }

        $class = SchoolClass::withoutGlobalScope('tenant')->where('school_id', $schoolId)->whereKey($this->classId);

        if ($this->isSchoolAdmin()) {
            return $class->exists();
        }

        return $this->role === 'teacher'
            && auth()->user()?->role === 'teacher'
            && $class->where('teacher_id', auth()->id())->exists();
    }

    private function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    // ── Locking ──────────────────────────────────────────────────────────────

    public function lockAll(): void
    {
        $this->clearMessages();

        if (! $this->isSchoolAdmin() || ! $this->canAccessClass()) {
            $this->errorMessage = __('Only school admins can lock scores.');

            return;
        }

        $count = app(ScoreCorrectionService::class)->lockClass($this->classId, $this->termId, auth()->user());
        $this->successMessage = trans_choice(':count score locked.|:count scores locked.', $count, ['count' => $count]);
        $this->loadGrid();
    }

    public function unlockAll(): void
    {
        $this->clearMessages();

        if (! $this->isSchoolAdmin() || ! $this->canAccessClass()) {
            $this->errorMessage = __('Only school admins can unlock scores.');

            return;
        }

        $this->validate(['unlockReason' => ['required', 'string', 'min:5', 'max:500']], [], ['unlockReason' => __('reason')]);

        $count = app(ScoreCorrectionService::class)->unlockClass($this->classId, $this->termId, auth()->user(), trim($this->unlockReason));

        $this->showUnlock = false;
        $this->unlockReason = '';
        $this->successMessage = trans_choice(':count score unlocked. Lock them again when your changes are done.|:count scores unlocked. Lock them again when your changes are done.', $count, ['count' => $count]);
        $this->loadGrid();
    }

    // ── Correcting a locked score ────────────────────────────────────────────

    public function openCorrection(int $scoreId): void
    {
        $this->clearMessages();
        $this->resetValidation();

        $score = $this->lockedScoreInClass($scoreId);
        if (! $score) {
            $this->errorMessage = __('That score is not available for correction.');

            return;
        }

        $score->loadMissing(['student:id,name', 'subject:id,name', 'scoreComponent:id,name']);

        $this->correctionScoreId = $score->id;
        $this->correctionCell = [
            'student' => $score->student?->name,
            'subject' => $score->subject?->name,
            'component' => $score->scoreComponent?->name,
            'current' => $score->score + 0,
            'max' => (float) $score->max_score,
        ];
        $this->correctionNewScore = '';
        $this->correctionReason = '';
    }

    public function cancelCorrection(): void
    {
        $this->correctionScoreId = null;
        $this->correctionCell = null;
        $this->resetValidation();
    }

    public function submitCorrection(): void
    {
        $this->clearMessages();

        $score = $this->correctionScoreId ? $this->lockedScoreInClass($this->correctionScoreId) : null;
        if (! $score) {
            $this->cancelCorrection();
            $this->errorMessage = __('That score is not available for correction.');

            return;
        }

        $this->validate([
            'correctionNewScore' => ['required', 'numeric', 'min:0', 'max:'.$score->max_score],
            'correctionReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'correctionNewScore' => __('new score'),
            'correctionReason' => __('reason'),
        ]);

        $service = app(ScoreCorrectionService::class);

        try {
            if ($this->isSchoolAdmin()) {
                $service->amend($score, (float) $this->correctionNewScore, trim($this->correctionReason), auth()->user());
                $this->successMessage = __('Score corrected. Report cards for this class are marked out of date — regenerate them when you are done.');
            } else {
                $service->requestCorrection($score, (float) $this->correctionNewScore, trim($this->correctionReason), auth()->user());
                $this->successMessage = __('Correction request sent to the school admin for approval.');
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->cancelCorrection();
        $this->loadGrid();
    }

    private function lockedScoreInClass(int $scoreId): ?StudentSubjectScore
    {
        if (! $this->canAccessClass()) {
            return null;
        }

        return StudentSubjectScore::withoutGlobalScopes()
            ->where('school_id', app('current.school')->id)
            ->where('class_id', $this->classId)
            ->where('term_id', $this->termId)
            ->where('is_locked', true)
            ->find($scoreId);
    }

    // ── Reviewing pending corrections (admin) ────────────────────────────────

    public function approveAmendment(int $amendmentId): void
    {
        $this->clearMessages();

        $amendment = $this->pendingAmendmentInClass($amendmentId);
        if (! $amendment) {
            $this->errorMessage = __('That correction is no longer pending.');
            $this->loadGrid();

            return;
        }

        app(ScoreCorrectionService::class)->approve($amendment, auth()->user());
        $this->successMessage = __('Correction applied. Report cards for this class are marked out of date.');
        $this->loadGrid();
    }

    public function startReject(int $amendmentId): void
    {
        $this->resetValidation();
        $this->rejectingAmendmentId = $amendmentId;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingAmendmentId = null;
        $this->resetValidation();
    }

    public function confirmReject(): void
    {
        $this->clearMessages();

        $amendment = $this->rejectingAmendmentId ? $this->pendingAmendmentInClass($this->rejectingAmendmentId) : null;
        if (! $amendment) {
            $this->cancelReject();
            $this->errorMessage = __('That correction is no longer pending.');
            $this->loadGrid();

            return;
        }

        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:500']], [], ['rejectReason' => __('reason')]);

        app(ScoreCorrectionService::class)->reject($amendment, auth()->user(), trim($this->rejectReason));

        $this->cancelReject();
        $this->successMessage = __('Correction rejected. The score was not changed.');
        $this->loadGrid();
    }

    private function pendingAmendmentInClass(int $amendmentId): ?ScoreAmendment
    {
        if (! $this->isSchoolAdmin() || ! $this->canAccessClass()) {
            return null;
        }

        return ScoreAmendment::withoutGlobalScopes()
            ->where('school_id', app('current.school')->id)
            ->where('class_id', $this->classId)
            ->where('term_id', $this->termId)
            ->pending()
            ->find($amendmentId);
    }

    // ── Report cards ─────────────────────────────────────────────────────────

    /**
     * Regenerate every out-of-date report card for this class, class-wide so positions stay correct.
     */
    public function regenerateReports(): void
    {
        $this->clearMessages();

        if (! $this->canAccessClass()) {
            $this->errorMessage = __('You do not have access to this class.');

            return;
        }

        $school = app('current.school');
        $term = Term::withoutGlobalScopes()->findOrFail($this->termId);

        $stale = StudentTermReport::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('class_id', $this->classId)
            ->where('needs_regeneration', true)
            ->where(fn ($q) => $q->where('term_id', $term->id)
                ->orWhere(fn ($session) => $session->where('report_type', 'session')->where('session_id', $term->session_id)))
            ->get(['report_type'])
            ->pluck('report_type')
            ->unique();

        $service = app(ScoreAggregationService::class);
        $count = 0;

        // Term reports first — session reports are built from the full-term reports
        foreach (['midterm', 'full_term'] as $type) {
            if ($stale->contains($type)) {
                $count += $service->generateClassReports($this->classId, $term->session_id, $term->id, $school->id, $type);
            }
        }

        if ($stale->contains('session')) {
            $count += $service->generateClassSessionReports($this->classId, $term->session_id, $school->id);
        }

        $this->successMessage = trans_choice(':count report card updated. Published ones keep their status, and students and parents are told when theirs changed.|:count report cards updated. Published ones keep their status, and students and parents are told when theirs changed.', $count, ['count' => $count]);
        $this->loadGrid();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Batch-save scores sent from Alpine.js.
     *
     * $changes = [['student_id'=>…,'subject_id'=>…,'component_id'=>…,'score'=>…], …]
     */
    public function saveScores(array $changes): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        if (empty($changes)) {
            return;
        }

        $school = app('current.school');
        $user = auth()->user();

        // Admins: any class. Teachers: only their own class.
        if (! $this->canAccessClass()) {
            $this->errorMessage = 'Access denied.';

            return;
        }

        $term = Term::where('school_id', $school->id)->find($this->termId);
        if (! $term) {
            $this->errorMessage = 'Term not found.';

            return;
        }

        // Pre-load components to avoid N+1
        $componentMap = ScoreComponent::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->get()
            ->keyBy('id');

        // The changes come from the browser — only accept students enrolled in this class for
        // this term, and subjects taught in this class
        $classStudentIds = StudentProfile::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('class_id', $this->classId)
            ->enrolledByTerm($term->loadMissing(['session' => fn ($q) => $q->withoutGlobalScopes()]))
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $classSubjectIds = SchoolClass::withoutGlobalScopes()->findOrFail($this->classId)
            ->subjects()->withoutGlobalScopes()->pluck('subjects.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($changes, $school, $term, $user, $componentMap, $classStudentIds, $classSubjectIds, &$updated, &$skipped) {
            foreach ($changes as $change) {
                $studentId = (int) ($change['student_id'] ?? 0);
                $subjectId = (int) ($change['subject_id'] ?? 0);
                $componentId = (int) ($change['component_id'] ?? 0);
                $score = $change['score'] ?? '';

                if ($studentId === 0 || $subjectId === 0 || $componentId === 0) {
                    continue;
                }

                if (! in_array($studentId, $classStudentIds, true) || ! in_array($subjectId, $classSubjectIds, true)) {
                    continue;
                }

                if ($score === '' || $score === null) {
                    continue;
                }

                $component = $componentMap->get($componentId);
                if (! $component) {
                    continue;
                }

                // Skip locked
                $existing = StudentSubjectScore::withoutGlobalScopes()
                    ->where('student_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('term_id', $term->id)
                    ->where('score_component_id', $componentId)
                    ->first();

                if ($existing && $existing->is_locked) {
                    $skipped++;

                    continue;
                }

                $scoreValue = min((float) $score, (float) $component->max_score);
                $scoreValue = max(0.0, $scoreValue);

                StudentSubjectScore::withoutGlobalScopes()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'subject_id' => $subjectId,
                        'term_id' => $term->id,
                        'score_component_id' => $componentId,
                    ],
                    [
                        'school_id' => $school->id,
                        'class_id' => $this->classId,
                        'session_id' => $term->session_id,
                        'score' => $scoreValue,
                        'max_score' => $component->max_score,
                        'source_type' => 'manual',
                        'entered_by' => $user->id,
                    ]
                );

                $updated++;
            }
        });

        if ($updated > 0) {
            app(ScoreCorrectionService::class)->markReportsStale($this->classId, $term->id);

            $noun = $updated === 1 ? 'score' : 'scores';
            $this->successMessage = "{$updated} {$noun} saved successfully.";
            if ($skipped > 0) {
                $this->successMessage .= " ({$skipped} locked scores were skipped.)";
            }
            $this->dispatch('scoresSaved');
            $this->loadGrid();
        } elseif ($skipped > 0) {
            $this->errorMessage = 'All selected scores are locked and cannot be changed.';
        } else {
            $this->errorMessage = 'No scores were updated.';
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $school = app('current.school');
        $user = auth()->user();

        if ($this->role === 'teacher') {
            $levels = collect();
            $classes = SchoolClass::where('teacher_id', $user->id)
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } else {
            $levels = SchoolLevel::with([
                'classes' => fn ($q) => $q
                    ->where('school_id', $school->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
                ->where('school_id', $school->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            $classes = collect();
        }

        $sessions = AcademicSession::where('school_id', $school->id)
            ->with(['terms' => fn ($q) => $q->orderBy('term_number')])
            ->orderByDesc('start_date')
            ->get();

        $fillPercent = $this->totalCells > 0
            ? (int) round(($this->filledCells / $this->totalCells) * 100)
            : 0;

        $selectedClass = $this->classId
            ? SchoolClass::withoutGlobalScopes()->find($this->classId)
            : null;

        $selectedTerm = $this->termId
            ? Term::withoutGlobalScopes()->with('session')->find($this->termId)
            : null;

        return view('livewire.admin.gradebook', compact(
            'levels',
            'classes',
            'sessions',
            'fillPercent',
            'selectedClass',
            'selectedTerm',
        ));
    }
}
