<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class StudentQuizTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected User $student;

    protected Quiz $quiz;

    protected QuizQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->student = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
            'must_change_password' => false,
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $session = AcademicSession::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->first();

        $term = Term::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->first();

        $this->quiz = Quiz::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'session_id' => $session->id,
            'term_id' => $term->id,
            'title' => 'Test Quiz',
            'source_type' => 'prompt',
            'passing_score' => 60,
            'max_attempts' => 2,
            'is_published' => true,
            'status' => 'approved',
            'total_questions' => 1,
            'created_by' => $this->admin->id,
        ]);

        $this->question = QuizQuestion::create([
            'quiz_id' => $this->quiz->id,
            'school_id' => $this->school->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is 2+2?',
            'options' => ['2', '4', '6', '8'],
            'correct_answer' => '4',
            'points' => 1,
            'sort_order' => 0,
        ]);
    }

    public function test_student_can_view_quiz_index(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.quizzes.index'))
            ->assertOk();
    }

    public function test_student_can_start_published_quiz(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.quizzes.start', $this->quiz))
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_can_start_legacy_quiz_with_zero_max_attempts(): void
    {
        $this->quiz->update(['max_attempts' => 0]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.start', $this->quiz))
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_can_render_quiz_take_page(): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.quizzes.take', $attempt))
            ->assertOk()
            ->assertViewIs('student.quizzes.take')
            ->assertSee('Submit Now');
    }

    public function test_student_cannot_start_unpublished_quiz(): void
    {
        $this->quiz->update(['is_published' => false]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.start', $this->quiz))
            ->assertForbidden();
    }

    public function test_student_cannot_start_quiz_from_different_class(): void
    {
        $session = AcademicSession::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->first();

        $term = Term::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->first();

        $otherClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'level_id' => $this->level->id,
            'name' => 'Other Class',
            'slug' => 'other-class',
            'is_active' => true,
        ]);

        $otherQuiz = Quiz::create([
            'school_id' => $this->school->id,
            'class_id' => $otherClass->id,
            'session_id' => $session->id,
            'term_id' => $term->id,
            'title' => 'Other Class Quiz',
            'source_type' => 'prompt',
            'passing_score' => 50,
            'max_attempts' => 1,
            'is_published' => true,
            'status' => 'approved',
            'total_questions' => 1,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.start', $otherQuiz))
            ->assertForbidden();
    }

    public function test_student_cannot_exceed_max_attempts(): void
    {
        // Create 2 submitted attempts (max_attempts=2)
        QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 2,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.start', $this->quiz))
            ->assertForbidden();
    }

    public function test_student_can_save_answer(): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_answer' => '4',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quiz_answers', [
            'attempt_id' => $attempt->id,
            'question_id' => $this->question->id,
            'selected_answer' => '4',
        ]);
    }

    public function test_student_can_submit_quiz_and_scores_are_calculated(): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        QuizAnswer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $this->question->id,
            'school_id' => $this->school->id,
            'selected_answer' => '4',
            'answered_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.quizzes.submit', $attempt))
            ->assertRedirect(route('student.quizzes.results', $attempt));

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(1, $attempt->score);
        $this->assertTrue($attempt->passed);
    }

    public function test_student_can_view_results_after_submission(): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'score' => 1,
            'total_points' => 1,
            'percentage' => '100.00',
            'passed' => true,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.quizzes.results', $attempt))
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Timer & answer-save resilience
    // ─────────────────────────────────────────────────────────────────────────

    public function test_json_save_returns_saved_status(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->student)
            ->postJson(route('student.quizzes.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_answer' => '4',
            ])
            ->assertOk()
            ->assertJson(['status' => 'saved']);
    }

    public function test_submit_just_after_time_limit_keeps_answers_sent_with_it(): void
    {
        $this->quiz->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();

        // The browser's auto-submit at 00:00 arrives a few seconds late
        $this->travel(10 * 60 + 30)->seconds();

        $this->actingAs($this->student)
            ->post(route('student.quizzes.submit', $attempt), ['answers' => [$this->question->id => '4']])
            ->assertRedirect(route('student.quizzes.results', $attempt));

        $attempt->refresh();
        $this->assertSame('timed_out', $attempt->status);
        $this->assertSame(1, $attempt->score);
    }

    public function test_submit_after_grace_period_ignores_answers_sent_with_it(): void
    {
        $this->quiz->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();

        $this->travel(10 * 60 + QuizAttempt::LATE_ANSWER_GRACE_SECONDS + 60)->seconds();

        $this->actingAs($this->student)
            ->post(route('student.quizzes.submit', $attempt), ['answers' => [$this->question->id => '4']])
            ->assertRedirect(route('student.quizzes.results', $attempt));

        $attempt->refresh();
        $this->assertSame('timed_out', $attempt->status);
        $this->assertSame(0, $attempt->score);
    }

    public function test_retried_save_within_grace_period_is_kept_and_auto_submits(): void
    {
        $this->quiz->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();

        $this->travel(10 * 60 + 20)->seconds();

        $this->actingAs($this->student)
            ->postJson(route('student.quizzes.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_answer' => '4',
            ])
            ->assertOk()
            ->assertJson(['status' => 'timed_out']);

        $attempt->refresh();
        $this->assertSame('timed_out', $attempt->status);
        $this->assertSame(1, $attempt->score);
    }

    public function test_saving_answer_survives_cache_failure(): void
    {
        $attempt = $this->startAttempt();

        // Fail only the quiz question-ids cache write, like the "Disk quota exceeded" incident
        Cache::partialMock()
            ->shouldReceive('remember')
            ->andReturnUsing(function (string $key, mixed $ttl, \Closure $callback): mixed {
                if (str_starts_with($key, 'quiz_')) {
                    throw new \ErrorException('file_put_contents(): Failed to open stream: Disk quota exceeded');
                }

                return $callback();
            });

        $this->actingAs($this->student)
            ->postJson(route('student.quizzes.save-answer', $attempt), [
                'question_id' => $this->question->id,
                'selected_answer' => '4',
            ])
            ->assertOk()
            ->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('quiz_answers', ['attempt_id' => $attempt->id, 'selected_answer' => '4']);
    }

    public function test_results_for_in_progress_attempt_redirects_to_take_page(): void
    {
        $attempt = $this->startAttempt();

        $this->actingAs($this->student)
            ->get(route('student.quizzes.results', $attempt))
            ->assertRedirect(route('student.quizzes.take', $attempt));
    }

    /**
     * Start through the controller so started_at is written in the same timezone the requests use.
     */
    private function startAttempt(): QuizAttempt
    {
        $this->actingAs($this->student)->post(route('student.quizzes.start', $this->quiz));

        return QuizAttempt::where('quiz_id', $this->quiz->id)
            ->where('student_id', $this->student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();
    }

    public function test_teacher_cannot_access_student_quiz_routes(): void
    {
        $teacher = $this->createSchoolUser('teacher');

        $this->actingAs($teacher)
            ->get(route('student.quizzes.index'))
            ->assertForbidden();
    }
}
