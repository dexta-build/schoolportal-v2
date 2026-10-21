<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\PortalUrl;
use App\Livewire\NotificationBell;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Notice;
use App\Models\ParentStudent;
use App\Models\Result;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Notifications\AcademicPeriodChangedNotification;
use App\Notifications\CreditPurchaseNotification;
use App\Notifications\DatabaseNotification;
use App\Notifications\DeadlineReminderNotification;
use App\Notifications\ExamAccessResetNotification;
use App\Notifications\ExamGradingFailedNotification;
use App\Notifications\LowCreditsWarningNotification;
use App\Notifications\NewAssignmentNotification;
use App\Notifications\NewExamNotification;
use App\Notifications\NewGameNotification;
use App\Notifications\NewNoticeNotification;
use App\Notifications\NewQuizNotification;
use App\Notifications\NewResultNotification;
use App\Notifications\NewSchoolNotification;
use App\Notifications\PasswordResetByAdmin;
use App\Notifications\ReportCardPublishedNotification;
use App\Notifications\ReportCardRevisedNotification;
use App\Notifications\StudentMovedClassNotification;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\SubmissionPendingApproval;
use App\Notifications\SubmissionPendingNotification;
use App\Notifications\SubmissionRejectedNotification;
use App\Notifications\SubmissionReviewed;
use App\Notifications\WelcomeNewUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Every in-app notification, opened by each role that receives it, must land on a page that
 * role can actually see — never a 403, 404 or 500 — and stay on the user's own domain.
 */
class NotificationLinksTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $student;

    private User $parent;

    private User $teacher;

    private User $superAdmin;

    private Exam $exam;

    private Notice $notice;

    private Result $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $session->update(['is_current' => true, 'status' => 'active']);
        $term = Term::withoutGlobalScopes()->where('session_id', $session->id)->orderBy('term_number')->firstOrFail();
        $term->update(['is_current' => true, 'status' => 'active']);

        $this->student = $this->createSchoolUser('student', ['must_change_password' => false]);
        StudentProfile::create(['user_id' => $this->student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);

        $this->parent = $this->createSchoolUser('parent', ['must_change_password' => false]);
        ParentStudent::create(['parent_id' => $this->parent->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id]);

        $this->teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->class->update(['teacher_id' => $this->teacher->id]);

        $platform = School::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'platform'],
            ['name' => 'Platform', 'email' => 'platform@schoolportal.test', 'country' => 'Nigeria', 'is_active' => true],
        );
        $this->superAdmin = User::withoutGlobalScopes()->create([
            'school_id' => $platform->id,
            'name' => 'Super Admin',
            'email' => 'super@schoolportal.test',
            'username' => 'super',
            'password' => bcrypt('Password1!'),
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ])->refresh();

        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'slug' => 'english', 'short_name' => 'ENG', 'category' => 'arts', 'is_active' => true]);

        $this->exam = Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'session_id' => $session->id,
            'term_id' => $term->id,
            'title' => 'English Exam',
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

        $this->notice = Notice::create([
            'school_id' => $this->school->id,
            'title' => 'Sports Day',
            'content' => 'Sports day is on Friday.',
            'is_published' => true,
            'status' => 'approved',
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->result = Result::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'session_id' => $session->id,
            'term_id' => $term->id,
            'class_id' => $this->class->id,
            'file_url' => 'https://res.cloudinary.com/demo/raw/upload/result.pdf',
            'file_public_id' => 'result',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function test_student_notification_links_open(): void
    {
        $this->assertLinksOpenFor($this->student, [
            new NewAssignmentNotification('JSS 1', 'Essay', 3, 'student'),
            new NewExamNotification('English Exam', 'JSS 1', 'exam', $this->exam->id, 'student'),
            new NewExamNotification('English Test', 'JSS 1', 'assessment', $this->exam->id, 'student'),
            new NewExamNotification('English Task', 'JSS 1', 'assignment', $this->exam->id, 'student'),
            new NewGameNotification('Word Game', 'JSS 1', 'flashcard', 1, 'student'),
            new NewQuizNotification('Quiz', 'JSS 1', 1, 'student'),
            new NewResultNotification($this->student->name, '2025/2026', 'First Term', $this->result->id, 'student'),
            new NewNoticeNotification('Sports Day', $this->notice->id),
            new ReportCardPublishedNotification($this->student->name, '2025/2026', 'First Term', 1, 'student'),
            new ReportCardRevisedNotification($this->student->name, '2025/2026', 'First Term', 1, 'student'),
            new StudentMovedClassNotification('JSS 1', 'JSS 2', $this->student->name, 'student'),
            new DeadlineReminderNotification('quiz', 'Quiz', 1, 'tomorrow', 'student'),
            new DeadlineReminderNotification('exam', 'English Exam', $this->exam->id, 'tomorrow', 'student'),
            new ExamAccessResetNotification('English Exam', 'exam', $this->exam->id, now()->addDay()),
            new AcademicPeriodChangedNotification('term', 'First Term'),
        ]);
    }

    public function test_parent_notification_links_open(): void
    {
        $this->assertLinksOpenFor($this->parent, [
            new NewAssignmentNotification('JSS 1', 'Essay', 3, 'parent'),
            new NewExamNotification('English Exam', 'JSS 1', 'exam', $this->exam->id, 'parent'),
            new NewExamNotification('English Test', 'JSS 1', 'assessment', $this->exam->id, 'parent'),
            new NewExamNotification('English Task', 'JSS 1', 'assignment', $this->exam->id, 'parent'),
            new NewGameNotification('Word Game', 'JSS 1', 'flashcard', 1, 'parent'),
            new NewQuizNotification('Quiz', 'JSS 1', 1, 'parent'),
            new NewResultNotification($this->student->name, '2025/2026', 'First Term', $this->result->id, 'parent'),
            new NewNoticeNotification('Sports Day', $this->notice->id),
            new ReportCardPublishedNotification($this->student->name, '2025/2026', 'First Term', 1, 'parent'),
            new ReportCardRevisedNotification($this->student->name, '2025/2026', 'First Term', 1, 'parent'),
            new StudentMovedClassNotification('JSS 1', 'JSS 2', $this->student->name, 'parent'),
            new DeadlineReminderNotification('quiz', 'Quiz', 1, 'tomorrow', 'parent'),
            new DeadlineReminderNotification('exam', 'English Exam', $this->exam->id, 'tomorrow', 'parent'),
            new AcademicPeriodChangedNotification('term', 'First Term'),
        ]);
    }

    public function test_teacher_notification_links_open(): void
    {
        $this->assertLinksOpenFor($this->teacher, [
            new NewNoticeNotification('Sports Day', $this->notice->id),
            new SubmissionApprovedNotification('score_amendment', 'Ada — Maths'),
            new SubmissionRejectedNotification('notice', 'Sports Day', 'Please add the date'),
            new AcademicPeriodChangedNotification('term', 'First Term'),
        ]);
    }

    public function test_admin_notification_links_open(): void
    {
        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'school_id' => $this->school->id,
            'attempt_number' => 1,
            'status' => 'grading_failed',
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);

        $this->assertLinksOpenFor($this->admin, [
            new SubmissionPendingNotification('score_amendment', $this->teacher->name, 1),
            new LowCreditsWarningNotification(2),
            new ExamGradingFailedNotification($attempt->load(['student', 'exam'])),
            new AcademicPeriodChangedNotification('term', 'First Term'),
        ]);
    }

    public function test_super_admin_notification_links_open(): void
    {
        $this->assertLinksOpenFor($this->superAdmin, [
            new NewSchoolNotification($this->school->name, $this->school->id),
            new CreditPurchaseNotification($this->school->name, 10, '2,000', $this->school->id),
        ]);
    }

    public function test_opening_a_notification_stays_on_the_users_own_domain(): void
    {
        $this->student->notify(new NewNoticeNotification('Sports Day', $this->notice->id));
        $notification = $this->student->notifications()->sole();

        // Stored while running in the queue worker, so it carries the platform domain
        $notification->update(['data' => array_merge($notification->data, [
            'action_url' => 'https://dexta.website/portal/student/notices/'.$this->notice->id,
        ])]);

        $this->actingAs($this->student)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect('/portal/student/notices/'.$this->notice->id);

        $this->actingAs($this->student);
        $this->assertSame(
            '/portal/student/notices/'.$this->notice->id,
            Livewire::test(NotificationBell::class)->instance()->markAsRead($notification->id)
        );
    }

    public function test_school_user_emails_link_to_the_schools_own_domain(): void
    {
        $this->school->update(['custom_domain' => 'portal.myschool.test']);
        $this->teacher->refresh();

        $emails = [
            (new WelcomeNewUser('teacher', 'teacher1', $this->school->name, 'Secret123'))->toMail($this->teacher),
            (new PasswordResetByAdmin($this->school->name))->toMail($this->teacher),
            (new SubmissionReviewed('notice', 'approved'))->toMail($this->teacher),
        ];

        foreach ($emails as $email) {
            $this->assertStringStartsWith('https://portal.myschool.test/portal/', $email->actionUrl);
        }

        $action = TeacherAction::create([
            'school_id' => $this->school->id,
            'teacher_id' => $this->teacher->id,
            'action_type' => 'post_notice',
            'entity_type' => 'notice',
            'entity_id' => $this->notice->id,
            'status' => 'pending',
        ]);
        $this->admin->refresh();
        $mail = (new SubmissionPendingApproval($action, $this->teacher->name))->toMail($this->admin);
        $this->assertSame('https://portal.myschool.test/portal/admin/approvals', $mail->actionUrl);
    }

    public function test_every_app_page_has_the_page_expired_dialog_instead_of_a_browser_confirm(): void
    {
        foreach ([[$this->admin, '/portal/admin/dashboard'], [$this->teacher, '/portal/teacher/dashboard'], [$this->student, '/portal/student/dashboard'], [$this->parent, '/portal/parent/dashboard']] as [$user, $path]) {
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('livewire-page-expired', false)
                ->assertSee('Please reload the page');
        }
    }

    public function test_portal_url_local_strips_the_domain(): void
    {
        $this->assertSame('/portal/student/quizzes', PortalUrl::local('https://dexta.website/portal/student/quizzes'));
        $this->assertSame('/portal/parent/cbt?tab=1#top', PortalUrl::local('https://evil.example/portal/parent/cbt?tab=1#top'));
        $this->assertSame('#', PortalUrl::local(null));
        $this->assertSame('#', PortalUrl::local('#'));
    }

    /**
     * @param  list<DatabaseNotification>  $notifications
     */
    private function assertLinksOpenFor(User $user, array $notifications): void
    {
        foreach ($notifications as $notification) {
            $data = $notification->toArray($user);
            $this->assertNotEmpty($data['message'] ?? null, $notification::class.' has no message');

            $path = PortalUrl::local($data['action_url'] ?? null);
            $this->assertNotSame('#', $path, $notification::class.' has no link');

            $response = $this->actingAs($user)->get($path);
            $status = $response->getStatusCode();

            $this->assertTrue(
                $status === 200 || ($status >= 300 && $status < 400),
                sprintf('%s → %s returned %d for a %s: %s', class_basename($notification), $path, $status, $user->role, $response->exception?->getMessage() ?? '')
            );
        }
    }
}
