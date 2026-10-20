<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class QuizController extends Controller
{
    public function index(): View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        $quizzes = Quiz::with(['class:id,name', 'attempts' => fn ($q) => $q->where('student_id', $student->id)])
            ->published()
            ->forClass($classId)
            ->orderByDesc('published_at')
            ->paginate(10);

        $available = $quizzes->getCollection()->filter(fn (Quiz $q) => $q->canStudentAttempt($student->id));
        $completed = $quizzes->getCollection()->filter(fn (Quiz $q) => ! $q->canStudentAttempt($student->id) && $q->attemptsForStudent($student->id) > 0);

        return view('student.quizzes.index', compact('quizzes', 'available', 'completed'));
    }

    public function start(Quiz $quiz): RedirectResponse|View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ($quiz->class_id !== $classId || ! $quiz->canStudentAttempt($student->id)) {
            abort(403, 'You cannot take this quiz.');
        }

        // Check for an in-progress attempt
        $existingAttempt = $quiz->attempts()
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            return redirect()->route('student.quizzes.take', $existingAttempt);
        }

        $attemptNumber = $quiz->attemptsForStudent($student->id) + 1;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        return redirect()->route('student.quizzes.take', $attempt);
    }

    public function take(QuizAttempt $attempt): View|RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->quiz->class_id !== (int) $classId) {
            abort(403);
        }

        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.quizzes.results', $attempt);
        }

        $quiz = $attempt->quiz;
        $quiz->load('questions');

        $questions = $quiz->shuffle_questions
            ? $quiz->questions->shuffle()
            : $quiz->questions;

        // Get already answered questions
        $answers = QuizAnswer::where('attempt_id', $attempt->id)
            ->pluck('selected_answer', 'question_id')
            ->toArray();

        // Calculate remaining time
        $remainingSeconds = null;
        if ($quiz->time_limit_minutes) {
            $elapsed = (int) $attempt->started_at->diffInSeconds(now());
            $totalSeconds = $quiz->time_limit_minutes * 60;
            $remainingSeconds = max(0, $totalSeconds - $elapsed);

            if ($remainingSeconds <= 0) {
                $this->submitAttempt($attempt, 'timed_out');

                return redirect()->route('student.quizzes.results', $attempt);
            }
        }

        return view('student.quizzes.take', compact('quiz', 'attempt', 'questions', 'answers', 'remainingSeconds'));
    }

    public function saveAnswer(Request $request, QuizAttempt $attempt): RedirectResponse|JsonResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        $attempt->loadMissing('quiz');

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->quiz->class_id !== (int) $classId
            || $attempt->status !== 'in_progress') {
            abort(403);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_answer' => ['nullable', 'string', 'max:500'],
        ]);

        $questionId = (int) $validated['question_id'];
        $expired = $attempt->hasExpired();

        // Within the grace window a late (e.g. retried) answer still counts before the auto-submit
        if (! $expired || $attempt->acceptsLateAnswers()) {
            if (! in_array($questionId, $this->questionIds($attempt), true)) {
                abort(422, 'Invalid question.');
            }

            $this->storeAnswer($attempt, $questionId, $validated['selected_answer']);
        }

        if ($expired) {
            $this->submitAttempt($attempt, 'timed_out');

            if ($request->wantsJson()) {
                return response()->json(['status' => 'timed_out', 'redirect' => route('student.quizzes.results', $attempt)]);
            }

            return redirect()->route('student.quizzes.results', $attempt);
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'saved']);
        }

        return redirect()->back();
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ((int) $attempt->student_id !== (int) $student->id
            || (int) $attempt->quiz->class_id !== (int) $classId
            || $attempt->status !== 'in_progress') {
            abort(403);
        }

        $expired = $attempt->hasExpired();

        // Save any answers bundled with the submit to prevent race conditions
        // (background fetch saves may not have completed before form submission).
        // The browser auto-submits at 00:00, so keep them within the grace window.
        $answers = $request->input('answers', []);
        if (is_array($answers) && ! empty($answers) && (! $expired || $attempt->acceptsLateAnswers())) {
            $validQuestionIds = $this->questionIds($attempt);
            foreach ($answers as $questionId => $answer) {
                if (! is_numeric($questionId) || ! in_array((int) $questionId, $validQuestionIds, true)) {
                    continue;
                }
                if (! is_string($answer) || $answer === '') {
                    continue;
                }
                $this->storeAnswer($attempt, (int) $questionId, $answer);
            }
        }

        $this->submitAttempt($attempt, $expired ? 'timed_out' : 'submitted');

        return redirect()->route('student.quizzes.results', $attempt);
    }

    public function results(QuizAttempt $attempt): View|RedirectResponse
    {
        $student = auth()->user();

        if ($attempt->student_id !== $student->id) {
            abort(403);
        }

        if ($attempt->status === 'in_progress') {
            return redirect()->route('student.quizzes.take', $attempt);
        }

        $quiz = $attempt->quiz;
        $quiz->load('questions');

        $answers = QuizAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        return view('student.quizzes.results', compact('quiz', 'attempt', 'answers'));
    }

    /**
     * Valid question IDs for the attempt's quiz, cached because it is read on every answer click.
     *
     * @return array<int, int>
     */
    private function questionIds(QuizAttempt $attempt): array
    {
        $load = fn (): array => $attempt->quiz->questions()->pluck('id')->all();

        // A cache outage (e.g. full disk) must never stop a student saving an answer
        try {
            return Cache::remember("quiz_{$attempt->quiz_id}_question_ids", 3600, $load);
        } catch (Throwable $e) {
            report($e);

            return $load();
        }
    }

    private function storeAnswer(QuizAttempt $attempt, int $questionId, ?string $answer): void
    {
        QuizAnswer::updateOrCreate(
            [
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
            ],
            [
                'school_id' => $attempt->school_id,
                'selected_answer' => $answer,
                'answered_at' => now(),
            ]
        );
    }

    private function submitAttempt(QuizAttempt $attempt, string $status): void
    {
        $quiz = $attempt->quiz;
        $quiz->load('questions');

        DB::transaction(function () use ($attempt, $quiz, $status) {
            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($quiz->questions as $question) {
                $totalPoints += $question->points;

                $answer = QuizAnswer::where('attempt_id', $attempt->id)
                    ->where('question_id', $question->id)
                    ->first();

                if ($answer) {
                    $isCorrect = mb_strtolower(trim($answer->selected_answer ?? '')) === mb_strtolower(trim($question->correct_answer));
                    $pointsEarned = $isCorrect ? $question->points : 0;
                    $earnedPoints += $pointsEarned;

                    $answer->update([
                        'is_correct' => $isCorrect,
                        'points_earned' => $pointsEarned,
                    ]);
                } else {
                    // Unanswered — create a record with no answer
                    QuizAnswer::create([
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                        'school_id' => $attempt->school_id,
                        'selected_answer' => null,
                        'is_correct' => false,
                        'points_earned' => 0,
                        'answered_at' => now(),
                    ]);
                }
            }

            $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;

            $attempt->update([
                'score' => $earnedPoints,
                'total_points' => $totalPoints,
                'percentage' => $percentage,
                'passed' => $percentage >= $quiz->passing_score,
                'submitted_at' => now(),
                'time_spent_seconds' => (int) $attempt->started_at->diffInSeconds(now()),
                'status' => $status,
            ]);
        });

        // Check achievements after scoring
        try {
            $student = auth()->user();
            $attempt->refresh();
            $newAchievements = app(AchievementService::class)->processQuizCompletion($student, $attempt);
            if (! empty($newAchievements)) {
                session()->flash('new_achievements', $newAchievements);
            }
        } catch (Throwable) {
            // Don't break quiz flow if achievement check fails
        }
    }
}
