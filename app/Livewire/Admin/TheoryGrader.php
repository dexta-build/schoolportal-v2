<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\SchoolClass;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Beautiful split-panel theory grader for admin and teachers.
 *
 * Modes:
 *  - 'by_student': grade all theory questions for one student, navigate between students
 *  - 'by_question': grade one question across all students, navigate between questions
 */
class TheoryGrader extends Component
{
    // ── Configuration ─────────────────────────────────────────────
    // Locked: set by the page and the server only. Grading trusts these values (which exam,
    // whose attempt, the question's maximum points), so the browser must not change them.
    #[Locked]
    public int $examId;

    #[Locked]
    public string $role = 'admin';   // 'admin' | 'teacher'

    public string $mode = 'by_student';  // 'by_student' | 'by_question'

    // ── Navigation state ──────────────────────────────────────────
    public int $currentStudentIndex = 0;

    public int $currentQuestionIndex = 0;

    // ── Current grading inputs ────────────────────────────────────
    public int|float $score = 0;

    public string $comment = '';

    // ── Loaded data (server-derived, so locked) ───────────────────
    /** @var array<string, mixed> */
    #[Locked]
    public array $exam = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $theoryQuestions = [];

    /** @var list<array<string, mixed>> */
    #[Locked]
    public array $attempts = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $currentQuestion = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $currentAttempt = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $currentAnswer = null;

    // ── UI feedback ───────────────────────────────────────────────
    public string $savedMessage = '';

    public bool $isSaving = false;

    // ── Lifecycle ─────────────────────────────────────────────────

    public function mount(
        int $examId,
        string $role = 'admin',
        string $mode = 'by_student',
        ?int $attemptId = null,
        ?int $questionId = null,
    ): void {
        // The page says who is grading; the logged-in user must actually be that role
        abort_unless(
            ($role === 'admin' && auth()->user()?->role === 'school_admin')
            || ($role === 'teacher' && auth()->user()?->role === 'teacher'),
            403
        );

        $this->examId = $examId;
        $this->role = $role;
        $this->mode = $mode;

        $this->loadData();

        // Set initial position
        if ($mode === 'by_student' && $attemptId) {
            $idx = collect($this->attempts)->search(fn ($a) => $a['id'] === $attemptId);
            $this->currentStudentIndex = $idx !== false ? (int) $idx : 0;
        } elseif ($mode === 'by_question' && $questionId) {
            $idx = collect($this->theoryQuestions)->search(fn ($q) => $q['id'] === $questionId);
            $this->currentQuestionIndex = $idx !== false ? (int) $idx : 0;
        }

        $this->syncCurrent();
    }

    // ── Data loading ──────────────────────────────────────────────

    private function loadData(): void
    {
        $school = app('current.school');

        $exam = Exam::with(['class:id,name', 'subject:id,name'])
            ->where('school_id', $school->id)
            ->findOrFail($this->examId);

        // Enforce teacher class ownership
        if ($this->role === 'teacher') {
            $class = SchoolClass::where('id', $exam->class_id)
                ->where('teacher_id', auth()->id())
                ->first();
            if (! $class) {
                abort(403, __('You do not own this class.'));
            }
        }

        $this->exam = [
            'id' => $exam->id,
            'title' => $exam->title,
            'class_name' => $exam->class?->name ?? '—',
            'subject_name' => $exam->subject?->name ?? '—',
            'category' => $exam->category ?? 'exam',
        ];

        // Load theory/short-answer questions only
        $this->theoryQuestions = ExamQuestion::where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->whereIn('type', ['theory', 'short_answer'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'type_label' => $q->type === 'theory' ? 'Theory/Essay' : 'Short Answer',
                'question_text' => $q->question_text,
                'marking_guide' => $q->marking_guide,
                'sample_answer' => $q->sample_answer,
                'points' => $q->points,
                'min_words' => $q->min_words ?? null,
                'max_words' => $q->max_words ?? null,
                'section_label' => $q->section_label ?? null,
            ])
            ->toArray();

        // Load submitted/grading attempts
        $this->attempts = ExamAttempt::with('student:id,name,username')
            ->where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($a) {
                // Count graded theory answers for this attempt
                $gradedCount = ExamAnswer::where('attempt_id', $a->id)
                    ->whereNotNull('graded_at')
                    ->whereIn('question_id', collect($this->theoryQuestions)->pluck('id'))
                    ->count();
                $totalTheory = count($this->theoryQuestions);

                return [
                    'id' => $a->id,
                    'student_name' => $a->student?->name ?? '—',
                    'student_username' => $a->student?->username ?? '—',
                    'initial' => mb_strtoupper(mb_substr($a->student?->name ?? 'S', 0, 1)),
                    'status' => $a->status,
                    'graded_count' => $gradedCount,
                    'total_theory' => $totalTheory,
                    'all_graded' => $totalTheory > 0 && $gradedCount >= $totalTheory,
                ];
            })
            ->toArray();
    }

    // ── Navigation sync ───────────────────────────────────────────

    private function syncCurrent(): void
    {
        $this->savedMessage = '';

        // Current question
        $this->currentQuestion = $this->theoryQuestions[$this->currentQuestionIndex] ?? null;

        // Current attempt / student
        $this->currentAttempt = $this->attempts[$this->currentStudentIndex] ?? null;

        // Load the answer for this (attempt, question) pair
        $this->loadCurrentAnswer();
    }

    private function loadCurrentAnswer(): void
    {
        if (! $this->currentAttempt || ! $this->currentQuestion) {
            $this->currentAnswer = null;
            $this->score = 0;
            $this->comment = '';

            return;
        }

        $school = app('current.school');

        $answer = ExamAnswer::where('attempt_id', $this->currentAttempt['id'])
            ->where('question_id', $this->currentQuestion['id'])
            ->where('school_id', $school->id)
            ->first();

        if ($answer) {
            $wordCount = $answer->theory_answer ? str_word_count($answer->theory_answer) : 0;

            $this->currentAnswer = [
                'id' => $answer->id,
                'theory_answer' => $answer->theory_answer ?? '',
                'points_earned' => $answer->points_earned ?? 0,
                'teacher_comment' => $answer->teacher_comment ?? '',
                'is_graded' => $answer->graded_at !== null,
                'word_count' => $wordCount,
            ];

            $this->score = $answer->points_earned ?? 0;
            $this->comment = $answer->teacher_comment ?? '';
        } else {
            $this->currentAnswer = null;
            $this->score = 0;
            $this->comment = '';
        }
    }

    // ── Grading actions ───────────────────────────────────────────

    public function setScore(int $score): void
    {
        $this->score = max(0, min($score, $this->currentQuestion['points'] ?? 0));
    }

    public function save(): void
    {
        if (! $this->currentAttempt || ! $this->currentQuestion) {
            return;
        }

        $maxPts = (int) ($this->currentQuestion['points'] ?? 1);
        $earned = max(0, min((int) $this->score, $maxPts));
        $school = app('current.school');

        ExamAnswer::where('attempt_id', $this->currentAttempt['id'])
            ->where('question_id', $this->currentQuestion['id'])
            ->where('school_id', $school->id)
            ->update([
                'points_earned' => $earned,
                'is_correct' => $earned >= $maxPts,
                'teacher_comment' => $this->comment ?: null,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]);

        $this->recalculateAttempt($this->currentAttempt['id']);

        $this->savedMessage = __('Saved — :earned/:max pts', ['earned' => $earned, 'max' => $maxPts]);

        // Refresh data so indicators update
        $this->loadData();
        $this->syncCurrent();
    }

    public function saveAndNext(): void
    {
        $this->save();
        $this->next();
    }

    public function next(): void
    {
        if ($this->mode === 'by_student') {
            // Advance through questions for this student first
            $nextQ = $this->currentQuestionIndex + 1;
            if (isset($this->theoryQuestions[$nextQ])) {
                $this->currentQuestionIndex = $nextQ;
            } else {
                // Wrap to next student
                $nextS = $this->currentStudentIndex + 1;
                if (isset($this->attempts[$nextS])) {
                    $this->currentStudentIndex = $nextS;
                    $this->currentQuestionIndex = 0;
                }
            }
        } else {
            // by_question: advance students, wrap to next question when done
            $nextS = $this->currentStudentIndex + 1;
            if (isset($this->attempts[$nextS])) {
                $this->currentStudentIndex = $nextS;
            } else {
                $nextQ = $this->currentQuestionIndex + 1;
                if (isset($this->theoryQuestions[$nextQ])) {
                    $this->currentQuestionIndex = $nextQ;
                    $this->currentStudentIndex = 0;
                }
            }
        }

        $this->syncCurrent();
    }

    public function prev(): void
    {
        if ($this->mode === 'by_student') {
            $prevQ = $this->currentQuestionIndex - 1;
            if ($prevQ >= 0) {
                $this->currentQuestionIndex = $prevQ;
            } else {
                $prevS = $this->currentStudentIndex - 1;
                if ($prevS >= 0) {
                    $this->currentStudentIndex = $prevS;
                    $this->currentQuestionIndex = count($this->theoryQuestions) - 1;
                }
            }
        } else {
            $prevS = $this->currentStudentIndex - 1;
            if ($prevS >= 0) {
                $this->currentStudentIndex = $prevS;
            } else {
                $prevQ = $this->currentQuestionIndex - 1;
                if ($prevQ >= 0) {
                    $this->currentQuestionIndex = $prevQ;
                    $this->currentStudentIndex = count($this->attempts) - 1;
                }
            }
        }

        $this->syncCurrent();
    }

    public function jumpToStudent(int $index): void
    {
        if (isset($this->attempts[$index])) {
            $this->currentStudentIndex = $index;
            $this->syncCurrent();
        }
    }

    public function jumpToQuestion(int $index): void
    {
        if (isset($this->theoryQuestions[$index])) {
            $this->currentQuestionIndex = $index;
            if ($this->mode === 'by_student') {
                // Keep same student, change question
            } else {
                // Reset student index when switching questions
                $this->currentStudentIndex = 0;
            }
            $this->syncCurrent();
        }
    }

    public function switchMode(string $mode): void
    {
        $this->mode = $mode;
        $this->currentStudentIndex = 0;
        $this->currentQuestionIndex = 0;
        $this->syncCurrent();
    }

    // ── Score recalculation ───────────────────────────────────────

    private function recalculateAttempt(int $attemptId): void
    {
        $school = app('current.school');
        $attempt = ExamAttempt::where('school_id', $school->id)->find($attemptId);

        if (! $attempt) {
            return;
        }

        $totalEarned = ExamAnswer::where('attempt_id', $attemptId)
            ->where('school_id', $school->id)
            ->sum('points_earned');

        $totalPoints = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->where('school_id', $school->id)
            ->sum('points');

        $percentage = $totalPoints > 0
            ? round($totalEarned / $totalPoints * 100, 2)
            : 0;

        // Determine if ALL theory questions are graded
        $theoryCount = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->where('school_id', $school->id)
            ->whereIn('type', ['theory', 'short_answer'])
            ->count();

        $gradedCount = ExamAnswer::where('attempt_id', $attemptId)
            ->where('school_id', $school->id)
            ->whereNotNull('graded_at')
            ->count();

        $allGraded = $theoryCount > 0 && $gradedCount >= $theoryCount;

        $exam = $attempt->exam;

        $attempt->update([
            'score' => $totalEarned,
            'total_points' => $totalPoints,
            'percentage' => $percentage,
            'passed' => $percentage >= (float) ($exam?->passing_score ?? 40),
            'status' => $allGraded ? 'graded' : 'grading',
        ]);
    }

    // ── Computed helpers ──────────────────────────────────────────

    /** Total graded count across all attempts for current question (by_question mode). */
    public function getGradedForCurrentQuestion(): int
    {
        if (! $this->currentQuestion) {
            return 0;
        }

        $school = app('current.school');

        return ExamAnswer::where('question_id', $this->currentQuestion['id'])
            ->where('school_id', $school->id)
            ->whereNotNull('graded_at')
            ->count();
    }

    /** Total fully-graded attempts. */
    public function getFullyGradedCount(): int
    {
        return collect($this->attempts)->filter(fn ($a) => $a['all_graded'])->count();
    }

    // ── Render ────────────────────────────────────────────────────

    public function render(): View
    {
        $routePrefix = $this->role === 'teacher' ? 'teacher.exams' : 'admin.exams';

        return view('livewire.admin.theory-grader', [
            'routePrefix' => $routePrefix,
            'totalQuestions' => count($this->theoryQuestions),
            'totalStudents' => count($this->attempts),
            'hasData' => count($this->theoryQuestions) > 0 && count($this->attempts) > 0,
            'fullyGradedCount' => $this->getFullyGradedCount(),
            'isAtEnd' => $this->mode === 'by_student'
                ? ($this->currentStudentIndex >= count($this->attempts) - 1 && $this->currentQuestionIndex >= count($this->theoryQuestions) - 1)
                : ($this->currentStudentIndex >= count($this->attempts) - 1 && $this->currentQuestionIndex >= count($this->theoryQuestions) - 1),
        ]);
    }
}
