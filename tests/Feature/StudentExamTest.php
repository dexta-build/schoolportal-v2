<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamAccessReset;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\GradingScale;
use App\Models\GradingScaleItem;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class StudentExamTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $student;

    private Exam $exam;

    private AcademicSession $session;

    private Term $term;

    private Subject $subject;

    private ScoreComponent $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->session = AcademicSession::withoutGlobalScopes()
            ->where('school_id', $this->school->id)->first();
        $this->session->update(['is_current' => true, 'status' => 'active']);

        $this->term = Term::withoutGlobalScopes()
            ->where('school_id', $this->school->id)->first();
        $this->term->update(['is_current' => true, 'status' => 'active']);

        $this->student = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'English',
            'slug' => 'english',
            'short_name' => 'ENG',
            'category' => 'arts',
            'is_active' => true,
        ]);

        $this->component = ScoreComponent::create([
            'school_id' => $this->school->id,
            'name' => 'Exam',
            'short_name' => 'EXM',
            'max_score' => 60,
            'weight' => 40,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->exam = $this->createPublishedExam();
    }

    public function test_student_can_view_exams_index(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertViewIs('student.exams.index');
    }

    public function test_student_can_view_published_exam(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam))
            ->assertOk()
            ->assertViewIs('student.exams.show');
    }

    public function test_student_cannot_view_unpublished_exam(): void
    {
        $this->exam->update(['is_published' => false]);

        $this->actingAs($this->student)
            ->get(route('student.exams.show', $this->exam))
            ->assertForbidden();
    }

    public function test_student_can_start_exam(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_stale_attempt_link_redirects_to_their_exam_start_page(): void
    {
        $otherStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
        ]);

        StudentProfile::create([
            'user_id' => $otherStudent->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $otherAttempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $otherStudent->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.take', $otherAttempt))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_can_start_legacy_exam_with_zero_max_attempts(): void
    {
        $this->exam->update(['max_attempts' => 0]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_attempts', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
        ]);
    }

    public function test_student_cannot_exceed_max_attempts(): void
    {
        // Use up all attempts
        ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
            'score' => 5,
            'total_points' => 10,
            'percentage' => 50.0,
            'passed' => true,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect(route('student.exams.show', $this->exam))
            ->assertSessionHas('error');
    }

    public function test_student_can_save_answer(): void
    {
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();
        $answer = ExamAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        $this->actingAs($this->student)
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $question->id,
                'selected_answer' => 'Water',
            ])
            ->assertOk();

        $this->assertEquals('Water', $answer->fresh()->selected_answer);
    }

    public function test_student_can_submit_exam(): void
    {
        $attempt = $this->startAttempt();

        // Save an answer first
        $question = $this->exam->questions->first();
        ExamAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->update(['selected_answer' => 'Water', 'answered_at' => now()]);

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt))
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(10, $attempt->score);
        $this->assertEquals(100.0, (float) $attempt->percentage);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_objective_exam_results_show_score_immediately_after_submit(): void
    {
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        $response = $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt), [
                'answers' => [
                    $question->id => 'Water',
                ],
            ]);

        $response->assertRedirect(route('student.exams.results', $attempt));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertOk()
            ->assertDontSee('Grading in Progress')
            ->assertSee('10/10')
            ->assertSee('100.0%');
    }

    public function test_matching_exam_results_show_score_immediately_after_submit(): void
    {
        $matchingQuestion = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'school_id' => $this->school->id,
            'type' => 'matching',
            'question_text' => 'Match each country to its capital',
            'options' => [
                ['left' => 'Nigeria', 'right' => 'Abuja'],
                ['left' => 'Ghana', 'right' => 'Accra'],
                ['left' => 'Kenya', 'right' => 'Nairobi'],
                ['left' => 'Egypt', 'right' => 'Cairo'],
            ],
            'correct_answer' => null,
            'points' => 4,
            'sort_order' => 2,
        ]);
        $this->exam->load('questions');

        $attempt = $this->startAttempt();
        $multipleChoiceQuestion = $this->exam->questions->firstWhere('type', 'multiple_choice');

        $response = $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt), [
                'answers' => [
                    $multipleChoiceQuestion->id => 'Water',
                    $matchingQuestion->id => json_encode([
                        0 => 'Abuja',
                        1 => 'Accra',
                        2 => 'Nairobi',
                        3 => 'Cairo',
                    ]),
                ],
            ]);

        $response->assertRedirect(route('student.exams.results', $attempt));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(14, $attempt->score);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertOk()
            ->assertDontSee('Grading in Progress')
            ->assertSee('14/14')
            ->assertSee('100.0%');
    }

    public function test_student_can_view_results(): void
    {
        $attempt = $this->startAttempt();
        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'score' => 8,
            'total_points' => 10,
            'percentage' => 80.0,
            'passed' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertOk()
            ->assertViewIs('student.exams.results');
    }

    public function test_student_cbt_result_uses_exam_class_level_grading_scale(): void
    {
        $secondaryLevel = SchoolLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Secondary',
            'slug' => 'secondary',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $secondaryClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'level_id' => $secondaryLevel->id,
            'name' => 'Secondary 1',
            'slug' => 'secondary-1',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $primaryScale = $this->createGradingScale('Primary Scale', 'Primary Excellent', $this->level->id);
        $this->createGradingScale('Secondary Scale', 'Secondary A1', $secondaryLevel->id);

        StudentProfile::where('user_id', $this->student->id)->update([
            'class_id' => $secondaryClass->id,
        ]);
        $this->student->update(['level_id' => $secondaryLevel->id]);

        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'score' => 8,
            'total_points' => 10,
            'percentage' => 82.0,
            'passed' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertOk()
            ->assertViewHas('grade', fn (?array $grade): bool => $grade !== null
                && $grade['label'] === 'Primary Excellent'
                && $grade['grading_scale_id'] === $primaryScale->id);
    }

    public function test_student_cbt_results_tab_uses_each_exam_class_level_grading_scale(): void
    {
        $secondaryLevel = SchoolLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Secondary',
            'slug' => 'secondary',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $secondaryClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'level_id' => $secondaryLevel->id,
            'name' => 'Secondary 1',
            'slug' => 'secondary-1',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $primaryScale = $this->createGradingScale('Primary Scale', 'Primary Excellent', $this->level->id);
        $this->createGradingScale('Secondary Scale', 'Secondary A1', $secondaryLevel->id);

        StudentProfile::where('user_id', $this->student->id)->update([
            'class_id' => $secondaryClass->id,
        ]);
        $this->student->update(['level_id' => $secondaryLevel->id]);

        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'score' => 8,
            'total_points' => 10,
            'percentage' => 82.0,
            'passed' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.report-cards.index', ['tab' => 'cbt-results']))
            ->assertOk()
            ->assertViewHas('grades', fn ($grades): bool => ($grades[$attempt->id]['label'] ?? null) === 'Primary Excellent'
                && ($grades[$attempt->id]['grading_scale_id'] ?? null) === $primaryScale->id);
    }

    public function test_attempt_pages_use_exam_category_label(): void
    {
        $this->exam->update(['category' => 'assignment', 'title' => 'English Assignment']);
        $attempt = $this->startAttempt();

        $this->actingAs($this->student)
            ->get(route('student.exams.take', $attempt))
            ->assertOk()
            ->assertViewHas('label', 'Assignment');

        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'score' => 8,
            'total_points' => 10,
            'percentage' => 80.0,
            'passed' => true,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertOk()
            ->assertViewHas('label', 'Assignment');
    }

    public function test_student_cannot_access_other_students_attempt(): void
    {
        $otherStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
        ]);

        StudentProfile::create([
            'user_id' => $otherStudent->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $otherStudent->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.results', $attempt))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_student_exam_routes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('student.exams.index'))
            ->assertForbidden();
    }

    // ── Closed exam scope tests ──

    public function test_scope_closed_returns_exam_past_deadline(): void
    {
        $closedExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(3),
            availableUntil: now()->subHour(),
        );

        $result = Exam::closed()->where('school_id', $this->school->id)->get();

        $this->assertTrue($result->contains($closedExam));
    }

    public function test_scope_closed_excludes_currently_open_exam(): void
    {
        $openExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subHour(),
            availableUntil: now()->addHour(),
        );

        $result = Exam::closed()->where('school_id', $this->school->id)->get();

        $this->assertFalse($result->contains($openExam));
    }

    public function test_scope_closed_excludes_exam_with_no_deadline(): void
    {
        // $this->exam has no available_until — should never appear in closed
        $result = Exam::closed()->where('school_id', $this->school->id)->get();

        $this->assertFalse($result->contains($this->exam));
    }

    // ── Closed section feature tests ──

    public function test_student_sees_closed_exam_with_score_in_index(): void
    {
        $closedExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(2),
            availableUntil: now()->subHour(),
        );

        ExamAttempt::create([
            'exam_id' => $closedExam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2),
            'score' => 8,
            'total_points' => 10,
            'percentage' => 80.0,
            'passed' => true,
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertViewHas('closed', fn ($closed) => $closed->contains($closedExam));
        $response->assertSee('80%');
        $response->assertSee('Passed');
        $response->assertSee('View Results');
    }

    public function test_student_sees_missed_badge_for_unattempted_closed_exam(): void
    {
        $closedExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(2),
            availableUntil: now()->subHour(),
        );

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertViewHas('closed', fn ($closed) => $closed->contains($closedExam));
        $response->assertSee('Missed');
        $response->assertSee('You missed this exam.');
    }

    public function test_elapsed_in_progress_attempt_is_not_shown_as_resumable(): void
    {
        $this->exam->update([
            'time_limit_minutes' => 10,
            'max_attempts' => 2,
            'available_until' => now()->addHour(),
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertDontSee('Resume')
            ->assertDontSee('Retake')
            ->assertSee('View Results');

        // Objective exams are graded straight after the auto-submit, so status moves past 'timed_out'
        $this->assertFalse($attempt->fresh()->isInProgress());
        $this->assertSame('time_elapsed', $attempt->fresh()->completion_reason);
    }

    public function test_reset_access_allows_student_to_start_again_after_time_elapsed(): void
    {
        $this->exam->update([
            'time_limit_minutes' => 10,
            'max_attempts' => 2,
            'available_until' => now()->addHour(),
        ]);

        ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'timed_out',
            'completion_reason' => 'time_elapsed',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(10),
        ]);

        ExamAccessReset::create([
            'school_id' => $this->school->id,
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'granted_by' => $this->admin->id,
            'available_from' => now()->subMinute(),
            'available_until' => now()->addHour(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee('Reset access')
            ->assertSee('Start Again')
            ->assertDontSee('Retake');

        $this->assertTrue($this->exam->fresh()->canStudentAttempt($this->student->id));
    }

    public function test_active_reset_allows_a_fresh_attempt_on_a_closed_exam(): void
    {
        $closedExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(2),
            availableUntil: now()->subHour(),
        );

        ExamAttempt::create([
            'exam_id' => $closedExam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'timed_out',
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
        ]);

        $reset = ExamAccessReset::create([
            'school_id' => $this->school->id,
            'exam_id' => $closedExam->id,
            'student_id' => $this->student->id,
            'granted_by' => $this->admin->id,
            'available_from' => now()->subMinute(),
            'available_until' => now()->addHour(),
        ]);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee('Reset access')
            ->assertSee($closedExam->title);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $closedExam))
            ->assertRedirect();

        $freshAttempt = ExamAttempt::where('exam_id', $closedExam->id)
            ->where('student_id', $this->student->id)
            ->where('attempt_number', 2)
            ->firstOrFail();

        $this->assertSame('in_progress', $freshAttempt->status);
        $this->assertSame($freshAttempt->id, $reset->fresh()->attempt_id);
        $this->assertNotNull($reset->fresh()->used_at);
    }

    public function test_reset_access_does_not_apply_to_another_student(): void
    {
        $closedExam = $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(2),
            availableUntil: now()->subHour(),
        );

        ExamAccessReset::create([
            'school_id' => $this->school->id,
            'exam_id' => $closedExam->id,
            'student_id' => $this->student->id,
            'granted_by' => $this->admin->id,
            'available_from' => now()->subMinute(),
            'available_until' => now()->addHour(),
        ]);

        $otherStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
        ]);
        StudentProfile::create([
            'user_id' => $otherStudent->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $this->actingAs($otherStudent)
            ->post(route('student.exams.start', $closedExam))
            ->assertRedirect(route('student.exams.show', $closedExam))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('exam_attempts', [
            'exam_id' => $closedExam->id,
            'student_id' => $otherStudent->id,
        ]);
    }

    public function test_empty_state_shown_only_when_all_four_buckets_are_empty(): void
    {
        $this->exam->update(['is_published' => false]);

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertViewHas('exams', fn ($e) => $e->isEmpty());
        $response->assertViewHas('upcoming', fn ($c) => $c->isEmpty());
        $response->assertViewHas('closed', fn ($c) => $c->isEmpty());
        $response->assertSee('No items available');
    }

    public function test_empty_state_not_shown_when_closed_exam_exists(): void
    {
        $this->exam->update(['is_published' => false]);

        $this->createPublishedExamWithWindow(
            availableFrom: now()->subDays(2),
            availableUntil: now()->subHour(),
        );

        $response = $this->actingAs($this->student)
            ->get(route('student.exams.index'));

        $response->assertOk();
        $response->assertDontSee('No items available');
    }

    // ── Helpers ──

    // ─────────────────────────────────────────────────────────────────────────
    // Timer & answer-save resilience
    // ─────────────────────────────────────────────────────────────────────────

    public function test_remaining_time_counts_down_across_reloads(): void
    {
        $this->exam->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();

        $this->travel(4)->minutes();

        $remaining = $this->actingAs($this->student)
            ->get(route('student.exams.take', $attempt))
            ->assertOk()
            ->viewData('remainingSeconds');

        $this->assertGreaterThanOrEqual(355, $remaining);
        $this->assertLessThanOrEqual(360, $remaining);
    }

    public function test_submit_just_after_time_limit_keeps_answers_sent_with_it(): void
    {
        $this->exam->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        // The browser's auto-submit at 00:00 arrives a few seconds late
        $this->travel(10 * 60 + 30)->seconds();

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt), ['answers' => [$question->id => 'Water']])
            ->assertRedirect(route('student.exams.results', $attempt));

        $this->assertSame('Water', ExamAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->value('selected_answer'));
        $this->assertSame('time_elapsed', $attempt->fresh()->completion_reason);
        $this->assertSame(10, $attempt->fresh()->score);
    }

    public function test_submit_after_grace_period_ignores_answers_sent_with_it(): void
    {
        $this->exam->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        $this->travel(10 * 60 + ExamAttempt::LATE_ANSWER_GRACE_SECONDS + 60)->seconds();

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt), ['answers' => [$question->id => 'Water']])
            ->assertRedirect(route('student.exams.results', $attempt));

        $this->assertNull(ExamAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->value('selected_answer'));
        $this->assertFalse($attempt->fresh()->isInProgress());
    }

    public function test_retried_save_within_grace_period_is_kept_and_auto_submits(): void
    {
        $this->exam->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        $this->travel(10 * 60 + 20)->seconds();

        $this->actingAs($this->student)
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $question->id,
                'selected_answer' => 'Water',
            ])
            ->assertOk()
            ->assertJson(['status' => 'timed_out']);

        $this->assertSame('Water', ExamAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->value('selected_answer'));
        $this->assertFalse($attempt->fresh()->isInProgress());
    }

    public function test_saving_answer_survives_cache_failure(): void
    {
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        // Fail only the exam question-types cache write, like the "Disk quota exceeded" incident
        Cache::partialMock()
            ->shouldReceive('remember')
            ->andReturnUsing(function (string $key, mixed $ttl, \Closure $callback): mixed {
                if (str_starts_with($key, 'exam_')) {
                    throw new \ErrorException('file_put_contents(): Failed to open stream: Disk quota exceeded');
                }

                return $callback();
            });

        $this->actingAs($this->student)
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $question->id,
                'selected_answer' => 'Water',
            ])
            ->assertOk()
            ->assertJson(['status' => 'saved']);

        $this->assertSame('Water', ExamAnswer::where('attempt_id', $attempt->id)->where('question_id', $question->id)->value('selected_answer'));
    }

    public function test_theory_answer_is_shown_again_after_reload(): void
    {
        $theory = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'school_id' => $this->school->id,
            'type' => 'theory',
            'question_text' => 'Explain the water cycle.',
            'options' => [],
            'correct_answer' => '',
            'points' => 10,
            'sort_order' => 2,
        ]);
        $this->exam->load('questions');

        $attempt = $this->startAttempt();

        $this->actingAs($this->student)
            ->postJson(route('student.exams.save-answer', $attempt), [
                'question_id' => $theory->id,
                'selected_answer' => 'Water evaporates, condenses and falls as rain.',
            ])
            ->assertOk();

        $answers = $this->actingAs($this->student)
            ->get(route('student.exams.take', $attempt))
            ->assertOk()
            ->viewData('answers');

        $this->assertSame('Water evaporates, condenses and falls as rain.', $answers[$theory->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Network (IP) lock, "Allow resume", and reopening a missed CBT
    // ─────────────────────────────────────────────────────────────────────────

    public function test_resume_from_a_new_network_explains_why_instead_of_silently_reloading(): void
    {
        $attempt = $this->startAttempt();
        $attempt->update(['ip_address' => '10.9.8.7']);

        $this->actingAs($this->student)
            ->followingRedirects()
            ->post(route('student.exams.start', $this->exam))
            ->assertOk()
            ->assertViewIs('student.exams.index')
            ->assertSee('Ask your school admin to allow you to resume');
    }

    public function test_admin_allow_resume_lets_student_continue_with_saved_answers(): void
    {
        $attempt = $this->startAttempt();
        $question = $this->exam->questions->first();

        $this->actingAs($this->student)
            ->postJson(route('student.exams.save-answer', $attempt), ['question_id' => $question->id, 'selected_answer' => 'Water'])
            ->assertOk();

        // The student's network changed mid-exam
        $attempt->update(['ip_address' => '10.9.8.7']);

        $this->actingAs($this->admin)
            ->get(route('admin.exams.reset-access', $this->exam))
            ->assertOk()
            ->assertSee('Students with an attempt in progress')
            ->assertSee($this->student->name)
            ->assertSee('Allow resume');

        $this->actingAs($this->admin)
            ->post(route('admin.exams.allow-resume', $this->exam), ['attempt_id' => $attempt->id])
            ->assertRedirect(route('admin.exams.reset-access', $this->exam))
            ->assertSessionHas('success');

        $this->assertNull($attempt->fresh()->ip_address);

        $answers = $this->actingAs($this->student)
            ->get(route('student.exams.take', $attempt))
            ->assertOk()
            ->viewData('answers');

        $this->assertSame('Water', $answers[$question->id]);
        $this->assertSame('in_progress', $attempt->fresh()->status);
        // Locked again, to the network the student resumed from
        $this->assertSame('127.0.0.1', $attempt->fresh()->ip_address);
    }

    public function test_allow_resume_is_refused_once_time_has_run_out(): void
    {
        $this->exam->update(['time_limit_minutes' => 10]);
        $attempt = $this->startAttempt();
        $attempt->update(['ip_address' => '10.9.8.7']);

        $this->travel(11)->minutes();

        $this->actingAs($this->admin)
            ->post(route('admin.exams.allow-resume', $this->exam), ['attempt_id' => $attempt->id])
            ->assertRedirect(route('admin.exams.reset-access', $this->exam))
            ->assertSessionHas('error');

        $this->assertSame('10.9.8.7', $attempt->fresh()->ip_address);
    }

    public function test_teacher_cannot_allow_resume(): void
    {
        $attempt = $this->startAttempt();
        $attempt->update(['ip_address' => '10.9.8.7']);

        $this->actingAs($this->createSchoolUser('teacher'))
            ->post(route('admin.exams.allow-resume', $this->exam), ['attempt_id' => $attempt->id])
            ->assertForbidden();

        $this->assertSame('10.9.8.7', $attempt->fresh()->ip_address);
    }

    public function test_admin_can_reopen_a_closed_assessment_for_a_student_who_missed_it(): void
    {
        // Opening the page first also puts the test process on the school's timezone
        $this->actingAs($this->admin)->get(route('admin.exams.reset-access', $this->exam))->assertOk();

        $this->exam->update([
            'category' => 'assessment',
            'available_from' => now()->subDays(2),
            'available_until' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.exams.reset-access', $this->exam))
            ->assertOk()
            ->assertSee('Missed');

        $this->actingAs($this->admin)
            ->post(route('admin.exams.reset-access.store', $this->exam), [
                'scope' => 'students',
                'student_ids' => [$this->student->id],
                'available_until' => now()->addDay()->format('Y-m-d H:i:s'),
                'reason' => 'Was absent',
            ])
            ->assertRedirect(route('admin.exams.show', $this->exam));

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect();

        // Same CBT record — not a copy
        $attempt = ExamAttempt::where('student_id', $this->student->id)->sole();
        $this->assertSame($this->exam->id, $attempt->exam_id);
        $this->assertSame('in_progress', $attempt->status);
    }

    private function createPublishedExam(): Exam
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'score_component_id' => $this->component->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => 'English Exam',
            'category' => 'exam',
            'source_type' => 'manual',
            'max_score' => 100,
            'total_points' => 10,
            'passing_score' => 50,
            'max_attempts' => 1,
            'status' => 'approved',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is H2O?',
            'options' => ['Water', 'Oxygen', 'Carbon', 'Nitrogen'],
            'correct_answer' => 'Water',
            'points' => 10,
            'sort_order' => 1,
        ]);

        $exam->load('questions');

        return $exam;
    }

    private function createPublishedExamWithWindow(
        ?\DateTimeInterface $availableFrom = null,
        ?\DateTimeInterface $availableUntil = null,
        string $title = 'Scheduled Exam',
    ): Exam {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'score_component_id' => $this->component->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => $title,
            'category' => 'exam',
            'source_type' => 'manual',
            'max_score' => 100,
            'total_points' => 10,
            'passing_score' => 50,
            'max_attempts' => 1,
            'status' => 'approved',
            'is_published' => true,
            'published_at' => now()->subDays(3),
            'available_from' => $availableFrom,
            'available_until' => $availableUntil,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now()->subDays(3),
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'type' => 'multiple_choice',
            'question_text' => 'What is H2O?',
            'options' => ['Water', 'Oxygen', 'Carbon', 'Nitrogen'],
            'correct_answer' => 'Water',
            'points' => 10,
            'sort_order' => 1,
        ]);

        $exam->load('questions');

        return $exam;
    }

    private function startAttempt(): ExamAttempt
    {
        // Use the controller's start action to create a proper attempt with answer slots
        $response = $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam));

        return ExamAttempt::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->where('status', 'in_progress')
            ->firstOrFail();
    }

    private function createGradingScale(string $name, string $excellentLabel, int $levelId): GradingScale
    {
        $scale = GradingScale::create([
            'school_id' => $this->school->id,
            'name' => $name,
            'is_default' => false,
            'is_active' => true,
        ]);

        foreach ([
            ['grade' => 'A', 'label' => $excellentLabel, 'min_score' => 80, 'max_score' => 100],
            ['grade' => 'B', 'label' => 'Good', 'min_score' => 50, 'max_score' => 79],
            ['grade' => 'F', 'label' => 'Needs Improvement', 'min_score' => 0, 'max_score' => 49],
        ] as $index => $item) {
            GradingScaleItem::create([
                'grading_scale_id' => $scale->id,
                'school_id' => $this->school->id,
                'grade' => $item['grade'],
                'label' => $item['label'],
                'min_score' => $item['min_score'],
                'max_score' => $item['max_score'],
                'sort_order' => $index,
            ]);
        }

        $scale->levels()->attach($levelId, ['school_id' => $this->school->id]);

        return $scale;
    }
}
