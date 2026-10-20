<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAccessReset;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Services\ExamGradingService;
use App\Services\ScoreAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamGradingService $gradingService,
        private readonly ScoreAggregationService $scoreService,
    ) {}

    /**
     * List available exams for the student, filtered by category.
     */
    public function index(Request $request): View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;
        $category = $this->resolveCategory();

        $this->finalizeExpiredAttempts($student->id);

        $exams = Exam::with([
            'subject:id,name,short_name',
            'class:id,name',
            'attempts' => fn ($q) => $q->where('student_id', $student->id)->with('accessReset'),
            'accessResets' => fn ($q) => $q->where('student_id', $student->id),
        ])->withCount('questions')
            ->availableForStudent($student->id)
            ->forClass($classId);

        // When category is null (unified view), show all; otherwise filter
        if ($category !== null) {
            $exams->forCategory($category);
        }

        $exams = $exams->orderByDesc('published_at')
            ->paginate(12);

        // Upcoming exams (published but not yet open)
        $upcomingQuery = Exam::with(['subject:id,name,short_name', 'class:id,name'])
            ->upcoming()
            ->forClass($classId)
            ->whereDoesntHave('accessResets', fn ($q) => $q->where('student_id', $student->id)->active())
            ->whereDoesntHave('attempts', fn ($q) => $q->where('student_id', $student->id)->where('status', 'in_progress'));

        if ($category !== null) {
            $upcomingQuery->forCategory($category);
        }

        $upcoming = $upcomingQuery->orderBy('available_from')
            ->get();

        // Closed exams (deadline has passed) — show results or "Missed" badge
        $closedQuery = Exam::with([
            'subject:id,name,short_name',
            'attempts' => fn ($q) => $q->where('student_id', $student->id)->with('accessReset'),
        ])
            ->closed()
            ->forClass($classId)
            ->whereDoesntHave('accessResets', fn ($q) => $q->where('student_id', $student->id)->active())
            ->whereDoesntHave('attempts', fn ($q) => $q->where('student_id', $student->id)->where('status', 'in_progress'));

        if ($category !== null) {
            $closedQuery->forCategory($category);
        }

        $closed = $closedQuery->orderByDesc('available_until')
            ->take(20)
            ->get();

        return view('student.exams.index', [
            'exams' => $exams,
            'upcoming' => $upcoming,
            'closed' => $closed,
            'category' => $category,
            'label' => $this->categoryLabel($category),
            'routePrefix' => $this->routePrefix(),
            'studentId' => $student->id,
        ]);
    }

    /**
     * Pre-exam screen: show instructions, rules, info.
     */
    public function show(Exam $exam): View|RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $exam->class_id !== (int) $classId || ! $exam->is_published) {
            abort(403);
        }

        $this->finalizeExpiredAttempts($student->id, $exam->id);
        $exam->load(['subject:id,name', 'class:id,name', 'questions']);

        $completedAttempts = $exam->completedAttemptsFor($student->id);
        $latestAttempt = $exam->latestAttemptFor($student->id);
        $canAttempt = $exam->canStudentAttempt($student->id);

        // If there's an in-progress attempt, offer to resume
        $inProgressAttempt = $exam->resumableAttemptForStudent($student->id);

        $questionTypeCounts = $exam->questions->groupBy('type')->map->count();
        $totalPoints = $exam->questions->sum('points');
        $hasTheoryQuestions = $exam->questions->contains(fn ($q) => in_array($q->type, ['theory', 'short_answer']));

        $previousAttempts = $exam->attemptsFor($student->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded', 'grading_failed'])
            ->orderBy('attempt_number')
            ->get();

        return view('student.exams.show', [
            'exam' => $exam,
            'category' => $this->resolveCategory(),
            'label' => $this->categoryLabel($this->resolveCategory()),
            'routePrefix' => $this->routePrefix(),
            'completedAttempts' => $completedAttempts,
            'latestAttempt' => $latestAttempt,
            'canAttempt' => $canAttempt,
            'inProgressAttempt' => $inProgressAttempt,
            'questionTypeCounts' => $questionTypeCounts,
            'totalPoints' => $totalPoints,
            'hasTheoryQuestions' => $hasTheoryQuestions,
            'previousAttempts' => $previousAttempts,
            'blockedMessage' => $canAttempt ? null : $this->startBlockedMessage($exam, $student->id),
            'allowedAttempts' => $exam->allowedAttemptsForStudent($student->id),
        ]);
    }

    /**
     * Start a new attempt or resume an existing one.
     */
    public function start(Exam $exam): RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $exam->class_id !== (int) $classId || ! $exam->is_published) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', __('This :label is not available for your class.', [
                    'label' => mb_strtolower($this->categoryLabel($exam->category)),
                ]));
        }

        $existingAttempts = $exam->attemptsFor($student->id)
            ->where('status', 'in_progress')
            ->with('accessReset')
            ->get();

        foreach ($existingAttempts as $existingAttempt) {
            if ($existingAttempt->hasExpired()) {
                $this->autoSubmit($existingAttempt);
            }
        }

        $existingAttempt = $exam->resumableAttemptForStudent($student->id);
        if ($existingAttempt) {
            return redirect()->route($this->routePrefix().'.take', $existingAttempt);
        }

        if (! $exam->canStudentAttempt($student->id)) {
            return redirect()->route($this->routePrefix().'.show', $exam)
                ->with('error', $this->startBlockedMessage($exam, $student->id));
        }

        $attempt = $this->createAttempt($exam, $student);

        return redirect()->route($this->routePrefix().'.take', $attempt);
    }

    /**
     * The exam-taking interface.
     */
    public function take(ExamAttempt $attempt): View|RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $attempt->student_id !== (int) $student->id) {
            $exam = $attempt->exam;

            if ($exam && (int) $exam->class_id === (int) $classId && $exam->is_published) {
                $this->finalizeExpiredAttempts($student->id, $exam->id);

                $ownAttempt = $exam->attemptsFor($student->id)
                    ->where('status', 'in_progress')
                    ->with('accessReset')
                    ->get()
                    ->first(fn (ExamAttempt $candidate): bool => $candidate->isResumable());

                if ($ownAttempt) {
                    return redirect()->route($this->routePrefix().'.take', $ownAttempt);
                }

                if ($exam->canStudentAttempt($student->id)) {
                    return redirect()->route($this->routePrefix().'.take', $this->createAttempt($exam, $student));
                }

                return redirect()->route($this->routePrefix().'.show', $exam)
                    ->with('error', $this->startBlockedMessage($exam, $student->id));
            }

            abort(403);
        }

        if ((int) $attempt->exam->class_id !== (int) $classId) {
            abort(403, __('This CBT is not assigned to your current class.'));
        }

        if (! $attempt->isInProgress()) {
            return redirect()->route($this->routePrefix().'.results', $attempt);
        }

        // IP session lock — prevent resuming from a different IP (e.g. shared device hand-off)
        if ($attempt->ip_address && $attempt->ip_address !== request()->ip()) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', __('Your :label is locked to the network you started it on, and your connection has changed. Ask your school admin to allow you to resume — your saved answers will be kept.', [
                    'label' => mb_strtolower($this->categoryLabel($attempt->exam->category)),
                ]));
        }

        // Unlocked by an admin ("Allow resume") — lock again to the network the student resumes from
        if (! $attempt->ip_address) {
            $attempt->update(['ip_address' => request()->ip()]);
        }

        if ($attempt->hasExpired()) {
            $this->autoSubmit($attempt);

            return redirect()->route($this->routePrefix().'.results', $attempt);
        }

        $exam = $attempt->exam;
        $exam->load('questions');

        // Shuffle questions with a stable seed per attempt (consistent on refresh)
        if ($exam->shuffle_questions) {
            $seed = crc32((string) $attempt->id);
            $questions = $exam->questions->shuffle($seed);
        } else {
            $questions = $exam->questions;
        }

        // Get saved answers keyed by question_id
        $answers = ExamAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        $remainingSeconds = $attempt->remainingSeconds();

        // Transform saved answers to {question_id: selected_answer}
        // Theory/short answers live in theory_answer, so they show again after a reload
        $answersForJs = $answers->mapWithKeys(fn ($a) => [$a->question_id => $a->selected_answer ?? $a->theory_answer])->all();

        return view('student.exams.take', [
            'exam' => $exam,
            'attempt' => $attempt,
            'questions' => $questions->values(),
            'answers' => $answersForJs,
            'remainingSeconds' => $remainingSeconds,
            'category' => $this->resolveCategory(),
            'label' => $this->categoryLabel($this->resolveCategory()),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    /**
     * Save a single answer (AJAX-friendly).
     */
    public function saveAnswer(Request $request, ExamAttempt $attempt): RedirectResponse|JsonResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        $attempt->loadMissing('exam');

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->exam->class_id !== (int) $classId
            || ! $attempt->isInProgress()) {
            abort(403);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_answer' => ['nullable', 'string', 'max:10000'],
        ]);

        $questionId = (int) $validated['question_id'];
        $expired = $attempt->hasExpired();

        // Within the grace window a late (e.g. retried) answer still counts before the auto-submit
        if (! $expired || $attempt->acceptsLateAnswers()) {
            $questionTypes = $this->questionTypes($attempt);

            if (! isset($questionTypes[$questionId])) {
                abort(422, 'Invalid question.');
            }

            $this->storeAnswer($attempt, $questionId, $questionTypes[$questionId], $validated['selected_answer']);
        }

        if ($expired) {
            $this->autoSubmit($attempt);

            if ($request->wantsJson()) {
                return response()->json(['status' => 'timed_out', 'redirect' => route($this->routePrefix().'.results', $attempt)]);
            }

            return redirect()->route($this->routePrefix().'.results', $attempt);
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'saved']);
        }

        return redirect()->back();
    }

    /**
     * Record a tab switch event.
     */
    public function tabSwitch(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->exam->class_id !== (int) $classId
            || ! $attempt->isInProgress()) {
            return response()->json(['status' => 'invalid'], 403);
        }

        if ($attempt->hasExpired()) {
            $this->autoSubmit($attempt);

            return response()->json([
                'status' => 'auto_submitted',
                'redirect' => route($this->routePrefix().'.results', $attempt),
                'message' => __('Your time has elapsed and your work was submitted automatically.'),
            ]);
        }

        $attempt->increment('tab_switches');

        $maxSwitches = $attempt->exam->max_tab_switches;
        $exceeded = $maxSwitches > 0 && $attempt->tab_switches >= $maxSwitches;

        if ($exceeded) {
            $this->autoSubmit($attempt, 'tab_switch_limit');

            return response()->json([
                'status' => 'auto_submitted',
                'redirect' => route($this->routePrefix().'.results', $attempt),
                'message' => 'Your exam was auto-submitted due to exceeding the maximum number of tab switches.',
            ]);
        }

        return response()->json([
            'status' => 'recorded',
            'tab_switches' => $attempt->tab_switches,
            'max_tab_switches' => $maxSwitches,
            'remaining' => max(0, $maxSwitches - $attempt->tab_switches),
        ]);
    }

    /**
     * Submit the exam.
     */
    public function submit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->exam->class_id !== (int) $classId
            || ! $attempt->isInProgress()) {
            abort(403);
        }

        $expired = $attempt->hasExpired();

        // Save any final answers bundled with submit. The browser auto-submits when its timer
        // hits zero, so this usually arrives just after expiry — keep it within the grace window.
        $answers = $request->input('answers', []);
        if (is_array($answers) && (! $expired || $attempt->acceptsLateAnswers())) {
            $questionTypes = $this->questionTypes($attempt);
            foreach ($answers as $questionId => $answer) {
                if (! is_numeric($questionId) || ! isset($questionTypes[(int) $questionId])) {
                    continue;
                }

                $this->storeAnswer($attempt, (int) $questionId, $questionTypes[(int) $questionId], is_string($answer) ? $answer : null);
            }
        }

        if ($expired) {
            $this->autoSubmit($attempt);

            return redirect()->route($this->routePrefix().'.results', $attempt)
                ->with('error', __('Your time elapsed before submission, so your saved work was submitted automatically.'));
        }

        DB::transaction(function () use ($attempt) {
            $elapsed = (int) $attempt->started_at->diffInSeconds(now());
            $attempt->update([
                'submitted_at' => now(),
                'time_spent_seconds' => $elapsed,
                'status' => 'submitted',
                'completion_reason' => 'submitted',
            ]);
        });

        $this->gradeSubmittedAttempt($attempt);

        return redirect()->route($this->routePrefix().'.results', $attempt);
    }

    /**
     * Show results after submission.
     */
    public function results(ExamAttempt $attempt): View|RedirectResponse
    {
        $student = auth()->user();

        if ((int) $attempt->student_id !== (int) $student->id) {
            abort(403);
        }

        if ($attempt->isInProgress()) {
            if ($attempt->hasExpired()) {
                $this->autoSubmit($attempt);
                $attempt->refresh();
            } else {
                return redirect()->route($this->routePrefix().'.take', $attempt);
            }
        }

        $exam = $attempt->exam;
        $exam->load(['questions', 'subject:id,name', 'class:id,name,level_id']);

        $answers = ExamAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        $grade = $attempt->percentage !== null
            ? $this->scoreService->getGrade($student->school_id, (float) $attempt->percentage, $exam->class?->level_id)
            : null;

        return view('student.exams.results', [
            'exam' => $exam,
            'attempt' => $attempt,
            'answers' => $answers,
            'grade' => $grade,
            'category' => $this->resolveCategory(),
            'label' => $this->categoryLabel($this->resolveCategory()),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    // ── Private helpers ──

    /**
     * Question types map [id => type] for the attempt's exam, cached because it is read on every answer click.
     *
     * @return array<int, string>
     */
    private function questionTypes(ExamAttempt $attempt): array
    {
        $load = fn (): array => $attempt->exam->questions()->pluck('type', 'id')->all();

        // A cache outage (e.g. full disk) must never stop a student saving an answer
        try {
            return Cache::remember("exam_{$attempt->exam_id}_question_types", 3600, $load);
        } catch (Throwable $e) {
            report($e);

            return $load();
        }
    }

    private function storeAnswer(ExamAttempt $attempt, int $questionId, string $type, ?string $answer): void
    {
        $isTheory = in_array($type, ['theory', 'short_answer'], true);

        ExamAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->update([
                'selected_answer' => $isTheory ? null : $answer,
                'theory_answer' => $isTheory ? $answer : null,
                'answered_at' => now(),
            ]);
    }

    private function autoSubmit(ExamAttempt $attempt, string $reason = 'time_elapsed'): void
    {
        DB::transaction(function () use ($attempt, $reason) {
            $elapsed = (int) $attempt->started_at->diffInSeconds(now());
            if ($attempt->exam->time_limit_minutes) {
                $elapsed = min($elapsed, $attempt->exam->time_limit_minutes * 60);
            }

            $attempt->update([
                'submitted_at' => now(),
                'time_spent_seconds' => $elapsed,
                'status' => 'timed_out',
                'completion_reason' => $reason,
            ]);
        });

        $this->gradeSubmittedAttempt($attempt);
    }

    private function createAttempt(Exam $exam, mixed $student): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $student): ExamAttempt {
            $student->newQuery()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $resumableAttempt = ExamAttempt::with('accessReset')
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->get()
                ->first(fn (ExamAttempt $attempt): bool => $attempt->isResumable());

            if ($resumableAttempt) {
                return $resumableAttempt;
            }

            $attemptNumber = ((int) ExamAttempt::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->max('attempt_number')) + 1;

            $activeReset = ExamAccessReset::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->active()
                ->oldest('available_until')
                ->lockForUpdate()
                ->first();

            $usedResetAttempts = ExamAccessReset::where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->whereNotNull('used_at')
                ->count();
            $baseCapacity = max(1, (int) ($exam->max_attempts ?: 1)) + $usedResetAttempts;
            $existingAttempts = $attemptNumber - 1;
            $mustUseReset = ! $exam->isAvailable() || $existingAttempts >= $baseCapacity;

            if ($mustUseReset && ! $activeReset) {
                abort(403, __('No active reset access is available for this CBT.'));
            }

            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'school_id' => $student->school_id,
                'attempt_number' => $attemptNumber,
                'started_at' => now(),
                'status' => 'in_progress',
                'ip_address' => request()->ip(),
            ]);

            $questionIds = $exam->questions()->pluck('id');
            $answerRows = [];
            foreach ($questionIds as $qId) {
                $answerRows[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $qId,
                    'school_id' => $student->school_id,
                ];
            }
            if (! empty($answerRows)) {
                ExamAnswer::insert($answerRows);
            }

            if ($mustUseReset && $activeReset) {
                $activeReset->update([
                    'attempt_id' => $attempt->id,
                    'used_at' => now(),
                ]);
            }

            return $attempt;
        });
    }

    private function gradeSubmittedAttempt(ExamAttempt $attempt): void
    {
        $this->gradingService->gradeAttempt($attempt->fresh());
    }

    private function finalizeExpiredAttempts(int $studentId, ?int $examId = null): void
    {
        $query = ExamAttempt::with(['exam', 'accessReset'])
            ->where('student_id', $studentId)
            ->where('status', 'in_progress');

        if ($examId !== null) {
            $query->where('exam_id', $examId);
        }

        foreach ($query->get() as $attempt) {
            if ($attempt->hasExpired()) {
                $this->autoSubmit($attempt);
            }
        }
    }

    private function resolveCategory(): ?string
    {
        // 1. Check query param first (unified index page uses ?category=)
        $queryCategory = request()->query('category');
        if (in_array($queryCategory, ['exam', 'assessment', 'assignment'], true)) {
            return $queryCategory;
        }

        // 2. Check route-bound exam model (for show/results/etc.)
        $exam = request()->route('exam');
        if ($exam instanceof Exam) {
            return $exam->category;
        }

        // 3. Attempt routes do not carry {exam}; infer the label from the attempt's exam.
        $attempt = request()->route('attempt');
        if ($attempt instanceof ExamAttempt) {
            return $attempt->exam?->category ?? 'exam';
        }

        // 4. For the unified index (student.exams.index without ?category), return null = all
        $routeName = request()->route()?->getName() ?? '';
        if ($routeName === 'student.exams.index' && ! $queryCategory) {
            return null;
        }

        return 'exam';
    }

    private function categoryLabel(?string $category): string
    {
        return match ($category) {
            'assessment' => __('Assessment'),
            'assignment' => __('Assignment'),
            null => __('CBT'),
            default => __('Exam'),
        };
    }

    private function startBlockedMessage(Exam $exam, int $studentId): string
    {
        $label = mb_strtolower($this->categoryLabel($exam->category));

        if ($exam->available_from && $exam->available_from->isFuture()) {
            return __('This :label is not open yet. It opens on :date.', [
                'label' => $label,
                'date' => $exam->available_from->format('M j, Y g:i A'),
            ]);
        }

        $activeReset = $exam->activeResetForStudent($studentId);
        if ($activeReset) {
            return __('Your reset access is available until :date.', [
                'date' => $activeReset->available_until->format('M j, Y g:i A'),
            ]);
        }

        if ($exam->available_until && $exam->available_until->isPast()) {
            return __('This :label has closed. It closed on :date.', [
                'label' => $label,
                'date' => $exam->available_until->format('M j, Y g:i A'),
            ]);
        }

        if ($exam->completedAttemptsFor($studentId) >= $exam->allowedAttemptsForStudent($studentId)) {
            return __('You have used all :max attempts for this :label.', [
                'max' => $exam->allowedAttemptsForStudent($studentId),
                'label' => $label,
            ]);
        }

        return __('You cannot start this :label right now. Please contact your teacher or school administrator.', [
            'label' => $label,
        ]);
    }

    private function routePrefix(): string
    {
        return 'student.exams';
    }
}
