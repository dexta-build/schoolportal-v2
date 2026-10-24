<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Notice;
use App\Models\ParentStudent;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\StudentTermReport;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Notifications\NewExamNotification;
use App\Notifications\NewNoticeNotification;
use App\Notifications\NewResultNotification;
use App\Notifications\ReportCardPublishedNotification;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\SubmissionPendingNotification;
use App\Services\NotificationService;
use App\Services\SchoolSetupService;
use App\Traits\NotifiesAdminsOnSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Each notification reaches the right people — and nobody in another class or school.
 */
class NotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private AcademicSession $session;

    private Term $term;

    private User $student;

    private User $parent;

    private User $teacher;

    private User $otherClassStudent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
        Notification::fake();

        $this->session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $this->term = Term::withoutGlobalScopes()->where('session_id', $this->session->id)->orderBy('term_number')->firstOrFail();

        $this->student = $this->studentIn($this->class);
        $this->parent = $this->createSchoolUser('parent');
        ParentStudent::create(['parent_id' => $this->parent->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id]);
        $this->teacher = $this->createSchoolUser('teacher');

        $otherClass = SchoolClass::create(['school_id' => $this->school->id, 'level_id' => $this->level->id, 'name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $this->otherClassStudent = $this->studentIn($otherClass);
    }

    public function test_new_result_reaches_the_student_and_their_parents(): void
    {
        $result = Result::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'class_id' => $this->class->id,
            'file_url' => 'https://res.cloudinary.com/demo/raw/upload/r.pdf',
            'status' => 'approved',
        ]);

        app(NotificationService::class)->notifyResultUploaded($result);

        Notification::assertSentTo($this->student, NewResultNotification::class);
        Notification::assertSentTo($this->parent, NewResultNotification::class);
        Notification::assertNotSentTo($this->otherClassStudent, NewResultNotification::class);
    }

    public function test_published_cbt_reaches_the_class_and_their_parents_only(): void
    {
        // Parents only hear about CBTs when the school shows them CBT results
        $this->school->update(['settings' => array_replace_recursive($this->school->settings ?? [], ['portal' => ['enable_cbt_results_for_parents' => true]])]);

        $exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'slug' => 'english', 'short_name' => 'ENG', 'category' => 'arts', 'is_active' => true])->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => 'English Test',
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

        app(NotificationService::class)->notifyExamPublished($exam);

        Notification::assertSentTo($this->student, NewExamNotification::class);
        Notification::assertSentTo($this->parent, NewExamNotification::class);
        Notification::assertNotSentTo($this->otherClassStudent, NewExamNotification::class);
    }

    public function test_school_wide_notice_reaches_students_parents_and_teachers(): void
    {
        $notice = Notice::create([
            'school_id' => $this->school->id,
            'title' => 'Sports Day',
            'content' => 'Friday',
            'is_published' => true,
            'status' => 'approved',
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        app(NotificationService::class)->notifyNoticePublished($notice);

        Notification::assertSentTo($this->student, NewNoticeNotification::class);
        Notification::assertSentTo($this->otherClassStudent, NewNoticeNotification::class);
        Notification::assertSentTo($this->parent, NewNoticeNotification::class);
        Notification::assertSentTo($this->teacher, NewNoticeNotification::class);
        Notification::assertNotSentTo($this->admin, NewNoticeNotification::class);
    }

    public function test_published_report_card_reaches_the_student_and_their_parents(): void
    {
        $report = StudentTermReport::withoutGlobalScopes()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'report_type' => 'full_term',
            'status' => 'published',
        ]);

        app(NotificationService::class)->notifyReportCardPublished($report);

        Notification::assertSentTo($this->student, ReportCardPublishedNotification::class);
        Notification::assertSentTo($this->parent, ReportCardPublishedNotification::class);
        Notification::assertNotSentTo($this->otherClassStudent, ReportCardPublishedNotification::class);
    }

    public function test_teacher_submission_reaches_only_their_own_schools_admins(): void
    {
        $otherSchool = app(SchoolSetupService::class)->create([
            'name' => 'Other School',
            'email' => 'other@school.test',
            'levels' => ['primary'],
            'admin_name' => 'Other Admin',
            'admin_email' => 'admin@other.test',
            'admin_username' => 'other_admin',
            'admin_password' => 'Password1!',
            'session_name' => '2025/2026',
            'session_start_date' => '2025-09-01',
            'session_end_date' => '2026-07-31',
        ]);
        $otherAdmin = User::withoutGlobalScopes()->where('school_id', $otherSchool->id)->where('role', 'school_admin')->firstOrFail();
        app()->instance('current.school', $this->school);

        $action = TeacherAction::create([
            'school_id' => $this->school->id,
            'teacher_id' => $this->teacher->id,
            'action_type' => 'post_notice',
            'entity_type' => 'notice',
            'entity_id' => 1,
            'status' => 'pending',
        ]);

        $notifier = new class
        {
            use NotifiesAdminsOnSubmission;

            public function send(TeacherAction $action, User $teacher): void
            {
                $this->notifyAdminsOfPendingSubmission($action, $teacher);
            }
        };
        $notifier->send($action, $this->teacher);

        Notification::assertSentTo($this->admin, SubmissionPendingNotification::class);
        Notification::assertNotSentTo($otherAdmin, SubmissionPendingNotification::class);
    }

    public function test_deactivated_students_and_parents_are_not_notified(): void
    {
        $activeClassmate = $this->studentIn($this->class);
        $this->student->update(['is_active' => false]);
        $this->parent->update(['is_active' => false]);

        $exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => Subject::create(['school_id' => $this->school->id, 'name' => 'Maths', 'slug' => 'maths', 'short_name' => 'MTH', 'category' => 'science', 'is_active' => true])->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => 'Maths Test',
            'category' => 'exam',
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
        $result = Result::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'class_id' => $this->class->id,
            'file_url' => 'https://res.cloudinary.com/demo/raw/upload/r.pdf',
            'status' => 'approved',
        ]);
        $report = StudentTermReport::withoutGlobalScopes()->create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'report_type' => 'full_term',
            'status' => 'published',
        ]);

        $service = app(NotificationService::class);
        $service->notifyExamPublished($exam);
        $service->notifyResultUploaded($result);
        $service->notifyReportCardPublished($report);

        Notification::assertNotSentTo($this->student, NewExamNotification::class);
        Notification::assertNotSentTo($this->parent, NewExamNotification::class);
        Notification::assertNotSentTo($this->student, NewResultNotification::class);
        Notification::assertNotSentTo($this->parent, NewResultNotification::class);
        Notification::assertNotSentTo($this->student, ReportCardPublishedNotification::class);
        Notification::assertNotSentTo($this->parent, ReportCardPublishedNotification::class);

        // Active classmates are still notified
        Notification::assertSentTo($activeClassmate, NewExamNotification::class);
    }

    public function test_approval_reaches_the_teacher(): void
    {
        app(NotificationService::class)->notifySubmissionApproved($this->teacher, 'notice', 'Sports Day');

        Notification::assertSentTo($this->teacher, SubmissionApprovedNotification::class);
    }

    private function studentIn(SchoolClass $class): User
    {
        $student = $this->createSchoolUser('student');
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $class->id]);

        return $student;
    }
}
