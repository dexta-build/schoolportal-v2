<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Gradebook;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SchoolClass;
use App\Models\ScoreAmendment;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Notifications\ReportCardRevisedNotification;
use App\Services\ScoreAggregationService;
use App\Services\ScoreCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Locked scores: lock/unlock with attribution, recorded corrections (admin, teacher request,
 * CBT re-grade), and report cards that follow a correction without losing their status.
 */
class ScoreCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private AcademicSession $session;

    private Term $term;

    private Subject $subject;

    private ScoreComponent $component;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $this->session->update(['is_current' => true, 'status' => 'active']);
        $this->term = Term::withoutGlobalScopes()
            ->where('session_id', $this->session->id)
            ->where('term_number', 1)
            ->firstOrFail();
        $this->term->update(['is_current' => true, 'status' => 'active']);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'short_name' => 'MTH',
            'category' => 'science',
            'is_active' => true,
        ]);
        ClassSubject::create(['school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $this->subject->id]);

        $this->component = ScoreComponent::create([
            'school_id' => $this->school->id,
            'name' => 'Continuous Assessment',
            'short_name' => 'CA',
            'max_score' => 20,
            'weight' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->class->update(['teacher_id' => $this->teacher->id]);

        $this->student = $this->makeStudent('Ada Obi');
    }

    // ── One subject at a time (phones) ───────────────────────────────────────

    public function test_gradebook_shows_only_the_chosen_subject(): void
    {
        $english = $this->addSubject('English Language');

        // Column headers are titled "Subject — Component"
        $this->gradebookAs($this->admin)
            ->assertSee('Mathematics — Continuous Assessment')
            ->assertSee('English Language — Continuous Assessment')
            ->set('subjectId', $english->id)
            ->assertSee('English Language — Continuous Assessment')
            ->assertDontSee('Mathematics — Continuous Assessment');
    }

    public function test_one_subject_mode_keeps_a_subject_picked_when_the_class_changes(): void
    {
        $gradebook = $this->gradebookAs($this->admin)->call('useOneSubjectMode');
        $this->assertSame($this->subject->id, $gradebook->get('subjectId'));

        // A class without Mathematics falls back to its own first subject
        $otherClass = SchoolClass::create(['school_id' => $this->school->id, 'level_id' => $this->class->level_id, 'name' => 'Class B', 'slug' => 'class-b']);
        $english = $this->addSubject('English Language', $otherClass);

        $gradebook->set('classId', $otherClass->id);
        $this->assertSame($english->id, $gradebook->get('subjectId'));
    }

    // ── Locking ──────────────────────────────────────────────────────────────

    public function test_locking_records_who_and_when_and_is_audited(): void
    {
        $score = $this->score($this->student, 12, locked: false);

        $this->gradebookAs($this->admin)->call('lockAll')->assertSet('lockInfo.by', $this->admin->name);

        $score->refresh();
        $this->assertTrue($score->is_locked);
        $this->assertSame($this->admin->id, $score->locked_by);
        $this->assertNotNull($score->locked_at);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'scores.locked')->where('entity_id', $this->class->id)->exists());
    }

    public function test_unlocking_requires_a_reason_and_is_audited(): void
    {
        $score = $this->score($this->student, 12);

        $this->gradebookAs($this->admin)
            ->set('unlockReason', '')
            ->call('unlockAll')
            ->assertHasErrors('unlockReason');
        $this->assertTrue($score->fresh()->is_locked);

        $this->gradebookAs($this->admin)
            ->set('unlockReason', 'CA 2 scores were typed into the CA 1 column')
            ->call('unlockAll')
            ->assertHasNoErrors();

        $this->assertFalse($score->fresh()->is_locked);
        $log = AuditLog::withoutGlobalScopes()->where('action', 'scores.unlocked')->sole();
        $this->assertSame('CA 2 scores were typed into the CA 1 column', $log->new_values['reason']);
    }

    public function test_teacher_cannot_lock_or_unlock(): void
    {
        $score = $this->score($this->student, 12, locked: false);

        $this->gradebookAs($this->teacher, 'teacher')->call('lockAll');
        $this->assertFalse($score->fresh()->is_locked);

        $score->update(['is_locked' => true]);
        $this->gradebookAs($this->teacher, 'teacher')->set('unlockReason', 'Please let me edit')->call('unlockAll');
        $this->assertTrue($score->fresh()->is_locked);
    }

    public function test_gradebook_role_cannot_be_changed_from_the_browser(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->gradebookAs($this->teacher, 'teacher')->set('role', 'admin');
    }

    // ── Admin correction ─────────────────────────────────────────────────────

    public function test_admin_corrects_a_locked_score_with_a_recorded_reason(): void
    {
        $score = $this->score($this->student, 12);

        $this->gradebookAs($this->admin)
            ->call('openCorrection', $score->id)
            ->set('correctionNewScore', '18')
            ->set('correctionReason', 'Typing error — script shows 18')
            ->call('submitCorrection')
            ->assertHasNoErrors();

        $score->refresh();
        $this->assertSame('18.00', $score->score);
        $this->assertTrue($score->is_locked, 'A corrected score stays locked');

        $amendment = ScoreAmendment::withoutGlobalScopes()->sole();
        $this->assertSame(ScoreAmendment::STATUS_APPLIED, $amendment->status);
        $this->assertSame('12.00', $amendment->old_score);
        $this->assertSame('18.00', $amendment->new_score);
        $this->assertSame('Typing error — script shows 18', $amendment->reason);
        $this->assertSame($this->admin->id, $amendment->reviewed_by);

        // The score change itself is in the audit log, old → new
        $log = AuditLog::withoutGlobalScopes()->where('action', 'studentsubjectscore.updated')->where('entity_id', $score->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals(12, $log->old_values['score']);
        $this->assertEquals(18, $log->new_values['score']);
    }

    public function test_correction_is_validated(): void
    {
        $score = $this->score($this->student, 12);

        $component = $this->gradebookAs($this->admin)->call('openCorrection', $score->id);

        $component->set('correctionNewScore', '25')->set('correctionReason', 'Too high')->call('submitCorrection')
            ->assertHasErrors('correctionNewScore');
        $component->set('correctionNewScore', '15')->set('correctionReason', '')->call('submitCorrection')
            ->assertHasErrors('correctionReason');
        $component->set('correctionNewScore', '12')->set('correctionReason', 'Same as before')->call('submitCorrection')
            ->assertHasErrors('new_score');

        $this->assertSame('12.00', $score->fresh()->score);
        $this->assertSame(0, ScoreAmendment::withoutGlobalScopes()->count());
    }

    // ── Teacher requests ─────────────────────────────────────────────────────

    public function test_teacher_request_waits_for_admin_approval_on_the_approvals_page(): void
    {
        Notification::fake();
        $score = $this->score($this->student, 12);

        $this->gradebookAs($this->teacher, 'teacher')
            ->call('openCorrection', $score->id)
            ->set('correctionNewScore', '16')
            ->set('correctionReason', 'Missed a page when adding up')
            ->call('submitCorrection')
            ->assertHasNoErrors();

        $this->assertSame('12.00', $score->fresh()->score, 'A teacher request does not change the score');
        $amendment = ScoreAmendment::withoutGlobalScopes()->sole();
        $this->assertSame(ScoreAmendment::STATUS_PENDING, $amendment->status);
        $action = TeacherAction::withoutGlobalScopes()->where('entity_type', 'score_amendment')->sole();

        $this->actingAs($this->admin)->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('Score Correction')
            ->assertSee('Missed a page when adding up');

        $this->actingAs($this->admin)->post(route('admin.approvals.approve', $action))->assertRedirect();

        $this->assertSame('16.00', $score->fresh()->score);
        $this->assertSame(ScoreAmendment::STATUS_APPLIED, $amendment->fresh()->status);
        $this->assertSame('approved', $action->fresh()->status);
    }

    public function test_admin_rejects_teacher_request_and_score_is_unchanged(): void
    {
        $score = $this->score($this->student, 12);
        $amendment = app(ScoreCorrectionService::class)->requestCorrection($score, 16, 'Please change', $this->teacher);
        $action = TeacherAction::withoutGlobalScopes()->where('entity_id', $amendment->id)->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.approvals.reject', $action), ['rejection_reason' => 'Script confirms 12'])
            ->assertRedirect();

        $this->assertSame('12.00', $score->fresh()->score);
        $this->assertSame(ScoreAmendment::STATUS_REJECTED, $amendment->fresh()->status);
        $this->assertSame('Script confirms 12', $amendment->fresh()->review_note);
    }

    public function test_approving_from_the_gradebook_also_closes_the_approval_queue_item(): void
    {
        $score = $this->score($this->student, 12);
        $amendment = app(ScoreCorrectionService::class)->requestCorrection($score, 16, 'Please change', $this->teacher);

        $this->gradebookAs($this->admin)
            ->assertCount('pendingAmendments', 1)
            ->call('approveAmendment', $amendment->id)
            ->assertCount('pendingAmendments', 0);

        $this->assertSame('16.00', $score->fresh()->score);
        $this->assertSame('approved', TeacherAction::withoutGlobalScopes()->where('entity_id', $amendment->id)->value('status'));
    }

    // ── CBT results and locks ────────────────────────────────────────────────

    public function test_cbt_grading_does_not_overwrite_a_locked_score(): void
    {
        $score = $this->score($this->student, 10);
        $attempt = $this->gradedCbtAttempt($this->student, 9); // 9/10 → 18/20

        app(ScoreAggregationService::class)->updateScoreFromExam($attempt);

        $this->assertSame('10.00', $score->fresh()->score);
        $amendment = ScoreAmendment::withoutGlobalScopes()->sole();
        $this->assertSame(ScoreAmendment::SOURCE_CBT, $amendment->source);
        $this->assertSame(ScoreAmendment::STATUS_PENDING, $amendment->status);
        $this->assertSame('18.00', $amendment->new_score);

        // The admin can still accept it
        app(ScoreCorrectionService::class)->approve($amendment, $this->admin);
        $this->assertSame('18.00', $score->fresh()->score);
    }

    public function test_cbt_grading_updates_an_unlocked_score_and_marks_reports_out_of_date(): void
    {
        $this->score($this->student, 10, locked: false);
        $report = $this->generateReport($this->student);
        $this->assertFalse($report->needs_regeneration);

        app(ScoreAggregationService::class)->updateScoreFromExam($this->gradedCbtAttempt($this->student, 9));

        $this->assertSame('18.00', StudentSubjectScore::withoutGlobalScopes()->value('score'));
        $this->assertTrue($report->fresh()->needs_regeneration);
    }

    // ── Report cards ─────────────────────────────────────────────────────────

    public function test_regenerating_keeps_published_status_keeps_old_version_and_notifies(): void
    {
        Notification::fake();
        $score = $this->score($this->student, 10);
        $report = $this->generateReport($this->student);
        $report->update(['status' => 'published', 'published_at' => now(), 'teacher_comment' => 'Good effort', 'finalized_at' => now()]);
        $oldAverage = (float) $report->average_weighted_score;

        app(ScoreCorrectionService::class)->amend($score, 16, 'Typing error', $this->admin);
        $this->assertTrue($report->fresh()->needs_regeneration);

        $this->gradebookAs($this->admin)
            ->assertSet('staleReportCount', 1)
            ->call('regenerateReports')
            ->assertSet('staleReportCount', 0);

        $report->refresh();
        $this->assertSame('published', $report->status, 'Regenerating never unpublishes a report card');
        $this->assertSame('Good effort', $report->teacher_comment);
        $this->assertFalse($report->needs_regeneration);
        $this->assertSame(1, $report->revision_count);
        $this->assertNotNull($report->revised_at);
        $this->assertEqualsWithDelta(80.0, (float) $report->average_weighted_score, 0.01);

        $revision = $report->revisions()->sole();
        $this->assertSame('published', $revision->status_at_revision);
        $this->assertEqualsWithDelta($oldAverage, (float) $revision->average_weighted_score, 0.01);

        Notification::assertSentTo($this->student, ReportCardRevisedNotification::class);
    }

    public function test_regenerating_an_unchanged_report_creates_no_revision_or_notification(): void
    {
        Notification::fake();
        $this->score($this->student, 10);
        $report = $this->generateReport($this->student);
        $report->update(['status' => 'published', 'published_at' => now()]);

        $this->generateReport($this->student);

        $this->assertSame(0, $report->fresh()->revision_count);
        $this->assertSame('published', $report->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_draft_report_stays_draft_without_a_revision(): void
    {
        $score = $this->score($this->student, 10);
        $report = $this->generateReport($this->student);

        app(ScoreCorrectionService::class)->amend($score, 16, 'Typing error', $this->admin);
        $this->generateReport($this->student);

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertSame(0, $report->fresh()->revision_count);
    }

    public function test_one_correction_updates_positions_for_the_whole_class(): void
    {
        $second = $this->makeStudent('Bola Ade');
        $this->score($this->student, 15);
        $secondScore = $this->score($second, 10);

        $this->generateReport($this->student);
        $this->assertSame(1, $this->reportFor($this->student)->position);
        $this->assertSame(2, $this->reportFor($second)->position);
        StudentTermReport::withoutGlobalScopes()->update(['status' => 'published', 'published_at' => now()]);

        app(ScoreCorrectionService::class)->amend($secondScore, 20, 'Wrong script used', $this->admin);

        // Both reports are out of date — the other student's position changes too
        $this->assertSame(2, StudentTermReport::withoutGlobalScopes()->where('needs_regeneration', true)->count());

        $this->gradebookAs($this->admin)->call('regenerateReports');

        $this->assertSame(2, $this->reportFor($this->student)->position);
        $this->assertSame(1, $this->reportFor($second)->position);
        $this->assertSame('published', $this->reportFor($this->student)->status);
        $this->assertSame(1, $this->reportFor($this->student)->revision_count);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeStudent(string $name): User
    {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'name' => $name,
            'level_id' => $this->level->id,
        ]);

        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);

        return $student;
    }

    private function score(User $student, float $value, bool $locked = true): StudentSubjectScore
    {
        return StudentSubjectScore::withoutGlobalScopes()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'score_component_id' => $this->component->id,
            'score' => $value,
            'max_score' => 20,
            'source_type' => 'manual',
            'is_locked' => $locked,
            'locked_by' => $locked ? $this->admin->id : null,
            'locked_at' => $locked ? now() : null,
        ]);
    }

    private function generateReport(User $student): StudentTermReport
    {
        app(ScoreAggregationService::class)->generateClassReports($this->class->id, $this->session->id, $this->term->id, $this->school->id);

        return $this->reportFor($student);
    }

    private function reportFor(User $student): StudentTermReport
    {
        return StudentTermReport::withoutGlobalScopes()->where('student_id', $student->id)->where('term_id', $this->term->id)->firstOrFail();
    }

    private function addSubject(string $name, ?SchoolClass $class = null): Subject
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => $name, 'slug' => Str::slug($name), 'short_name' => mb_substr($name, 0, 3), 'category' => 'general', 'is_active' => true]);
        ClassSubject::create(['school_id' => $this->school->id, 'class_id' => ($class ?? $this->class)->id, 'subject_id' => $subject->id]);

        return $subject;
    }

    private function gradebookAs(User $user, string $role = 'admin'): Testable
    {
        $this->actingAs($user);

        return Livewire::test(Gradebook::class, ['role' => $role])
            ->set('termId', $this->term->id)
            ->set('classId', $this->class->id);
    }

    private function gradedCbtAttempt(User $student, int $score): ExamAttempt
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'score_component_id' => $this->component->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => 'Maths CA',
            'category' => 'assessment',
            'source_type' => 'manual',
            'max_score' => 10,
            'total_points' => 10,
            'passing_score' => 50,
            'max_attempts' => 1,
            'status' => 'approved',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'score' => $score,
            'total_points' => 10,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);
    }
}
