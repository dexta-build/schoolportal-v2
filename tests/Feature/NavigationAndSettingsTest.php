<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ParentStudent;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Notifications\NewExamNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * The admin menu (clear names, approvals first), the app's own confirm dialog on every page,
 * and the school's "CBT Results for Parents" setting being enforced — not just hidden.
 */
class NavigationAndSettingsTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $student;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->student = $this->createSchoolUser('student', ['must_change_password' => false]);
        StudentProfile::create(['user_id' => $this->student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);
        $this->parent = $this->createSchoolUser('parent', ['must_change_password' => false]);
        ParentStudent::create(['parent_id' => $this->parent->id, 'student_id' => $this->student->id, 'school_id' => $this->school->id]);
    }

    public function test_admin_menu_uses_clear_names_and_puts_approvals_first(): void
    {
        $teacher = $this->createSchoolUser('teacher');
        TeacherAction::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'action_type' => 'post_notice', 'entity_type' => 'notice', 'entity_id' => 1, 'status' => 'pending']);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['Approvals', 'Promote Students (New Session)', 'Change Class (Same Session)', 'Result Sheets (PDF)', 'Report Cards', 'Notice Board', 'Banner Announcements', 'Grading &amp; Score Setup'] as $label) {
            $this->assertStringContainsString($label, $html, "Menu is missing \"{$label}\"");
        }
        foreach (['Uploaded Results', '>Promotions<', 'Move Student'] as $old) {
            $this->assertStringNotContainsString($old, $html, "Menu still shows the old name \"{$old}\"");
        }

        // Approvals sits above the menu groups, before People
        $this->assertLessThan(strpos($html, '>People<') ?: PHP_INT_MAX, strpos($html, 'Approvals'));
    }

    public function test_every_role_gets_the_app_confirm_dialog(): void
    {
        foreach ([[$this->admin, 'admin.dashboard'], [$this->student, 'student.dashboard'], [$this->parent, 'parent.dashboard']] as [$user, $route]) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertSee('app-confirm-title', false);
        }
    }

    public function test_parents_cannot_see_cbt_results_when_the_school_turns_them_off(): void
    {
        $this->setCbtResultsForParents(false);

        $this->actingAs($this->parent)->get(route('parent.cbt.index'))->assertNotFound();
        $this->actingAs($this->parent)->get(route('parent.children.cbt-results', $this->student))->assertNotFound();
        $this->actingAs($this->parent)->get(route('parent.dashboard'))->assertOk()->assertDontSee(route('parent.cbt.index'), false);
    }

    public function test_parents_can_see_cbt_results_when_the_school_turns_them_on(): void
    {
        $this->setCbtResultsForParents(true);

        $this->actingAs($this->parent)->get(route('parent.cbt.index'))->assertOk();
        $this->actingAs($this->parent)->get(route('parent.children.cbt-results', $this->student))->assertOk();
    }

    public function test_parents_are_only_told_about_new_cbts_when_the_school_shows_them_cbt_results(): void
    {
        Notification::fake();
        $exam = $this->publishedExam();

        $this->setCbtResultsForParents(false);
        app(NotificationService::class)->notifyExamPublished($exam);
        Notification::assertSentTo($this->student, NewExamNotification::class);
        Notification::assertNotSentTo($this->parent, NewExamNotification::class);

        $this->setCbtResultsForParents(true);
        app(NotificationService::class)->notifyExamPublished($exam);
        Notification::assertSentTo($this->parent, NewExamNotification::class);
    }

    private function setCbtResultsForParents(bool $enabled): void
    {
        $settings = $this->school->settings ?? [];
        data_set($settings, 'portal.enable_cbt_results_for_parents', $enabled);
        $this->school->update(['settings' => $settings]);
        app()->instance('current.school', $this->school->fresh());
    }

    private function publishedExam(): Exam
    {
        $session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();

        return Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'slug' => 'english', 'short_name' => 'ENG', 'category' => 'arts', 'is_active' => true])->id,
            'session_id' => $session->id,
            'term_id' => Term::withoutGlobalScopes()->where('session_id', $session->id)->value('id'),
            'title' => 'English Test',
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
    }
}
