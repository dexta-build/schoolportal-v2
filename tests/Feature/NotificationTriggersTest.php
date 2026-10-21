<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Term;
use App\Notifications\AcademicPeriodChangedNotification;
use App\Notifications\DeadlineReminderNotification;
use App\Notifications\LowCreditsWarningNotification;
use App\Services\AiCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    // ── Academic Period Change ─────────────────────────────────────────────

    public function test_activating_session_sends_academic_period_notification(): void
    {
        Notification::fake();

        // The setup already created a current session; create a second inactive one
        $session = AcademicSession::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027',
            'start_date' => now()->addYear()->startOfYear()->toDateString(),
            'end_date' => now()->addYear()->endOfYear()->toDateString(),
            'is_current' => false,
            'status' => 'upcoming',
        ]);

        $student = $this->createSchoolUser('student');

        $this->actingAs($this->admin)
            ->post(route('admin.sessions.activate', $session))
            ->assertRedirect();

        Notification::assertSentTo($student, AcademicPeriodChangedNotification::class, function ($n) {
            return $n->toArray($this->admin)['period_type'] === 'session';
        });
    }

    public function test_activating_term_sends_academic_period_notification(): void
    {
        Notification::fake();

        // SchoolSetupService creates 3 terms — find one that is not current
        $term = Term::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->where('is_current', false)
            ->first();

        if (! $term) {
            $this->markTestSkipped('No inactive term available in test school.');
        }

        $student = $this->createSchoolUser('student');

        $this->actingAs($this->admin)
            ->post(route('admin.terms.activate', $term))
            ->assertRedirect();

        Notification::assertSentTo($student, AcademicPeriodChangedNotification::class, function ($n) {
            return $n->toArray($this->admin)['period_type'] === 'term';
        });
    }

    // ── Low Credits Warning ────────────────────────────────────────────────

    public function test_low_credits_notification_sent_when_balance_reaches_threshold(): void
    {
        Notification::fake();

        // Set school to 4 credits — deducting one should drop it to 3 and trigger warning
        $this->school->update([
            'ai_free_credits' => 4,
            'ai_purchased_credits' => 0,
        ]);

        $teacher = $this->createSchoolUser('teacher');

        app(AiCreditService::class)->deductCredit(
            $this->school,
            $teacher,
            'quiz',
        );

        Notification::assertSentTo($this->admin, LowCreditsWarningNotification::class);
    }

    public function test_database_notifications_are_stored_immediately_for_bell(): void
    {
        $this->admin->notify(new LowCreditsWarningNotification(2));

        $this->assertSame(1, $this->admin->fresh()->unreadNotifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $this->admin->getMorphClass(),
            'notifiable_id' => $this->admin->id,
        ]);
    }

    public function test_low_credits_notification_not_sent_when_balance_above_threshold(): void
    {
        Notification::fake();

        $this->school->update([
            'ai_free_credits' => 10,
            'ai_purchased_credits' => 0,
        ]);

        $teacher = $this->createSchoolUser('teacher');

        app(AiCreditService::class)->deductCredit(
            $this->school,
            $teacher,
            'quiz',
        );

        Notification::assertNotSentTo($this->admin, LowCreditsWarningNotification::class);
    }

    // ── Deadline Reminder Notification ────────────────────────────────────

    public function test_deadline_reminder_notification_has_correct_type_label(): void
    {
        $notification = new DeadlineReminderNotification('quiz', 'Photosynthesis Quiz', 1, 'May 20 at 8:00 AM', 'student');
        $data = $notification->toArray($this->admin);

        $this->assertSame('deadline_reminder', $data['type_label']);
        $this->assertSame('quiz', $data['content_type']);
        $this->assertSame(1, $data['entity_id']);
    }

    public function test_deadline_reminder_routes_to_exam_url_for_exam_type(): void
    {
        $notification = new DeadlineReminderNotification('exam', 'Maths Exam', 5, 'May 21 at 9:00 AM', 'student');
        $data = $notification->toArray($this->admin);

        $this->assertStringContainsString('exams/5', $data['action_url']);
    }

    public function test_deadline_reminder_routes_to_quiz_url_for_quiz_type(): void
    {
        $notification = new DeadlineReminderNotification('quiz', 'Science Quiz', 3, 'May 21 at 9:00 AM', 'student');
        $data = $notification->toArray($this->admin);

        // Students have no per-quiz page — the reminder opens their quiz list
        $this->assertSame(url('/portal/student/quizzes'), $data['action_url']);
    }

    // ── Academic Period Changed Notification ──────────────────────────────

    public function test_academic_period_changed_notification_for_session(): void
    {
        $notification = new AcademicPeriodChangedNotification('session', '2025/2026');
        $data = $notification->toArray($this->admin);

        $this->assertSame('academic_period_changed', $data['type_label']);
        $this->assertSame('session', $data['period_type']);
        $this->assertSame('2025/2026', $data['period_name']);
        $this->assertStringContainsString('session', strtolower($data['message']));
    }

    public function test_academic_period_changed_notification_for_term(): void
    {
        $notification = new AcademicPeriodChangedNotification('term', 'First Term');
        $data = $notification->toArray($this->admin);

        $this->assertSame('term', $data['period_type']);
        $this->assertStringContainsString('term', strtolower($data['message']));
    }

    // ── Low Credits Warning Notification ──────────────────────────────────

    public function test_low_credits_notification_message_when_zero(): void
    {
        $notification = new LowCreditsWarningNotification(0);
        $data = $notification->toArray($this->admin);

        $this->assertSame('low_credits', $data['type_label']);
        $this->assertSame(0, $data['remaining_credits']);
        $this->assertStringContainsString('run out', strtolower($data['message']));
    }

    public function test_low_credits_notification_message_when_some_remaining(): void
    {
        $notification = new LowCreditsWarningNotification(2);
        $data = $notification->toArray($this->admin);

        $this->assertSame(2, $data['remaining_credits']);
        $this->assertStringContainsString('2', $data['message']);
    }
}
