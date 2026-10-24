<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Notice;
use App\Models\Result;
use App\Models\School;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolClass;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Deletes that can be undone (Undo bar, Recently deleted page) for 30 days; files stay on Cloudinary until
 * the record is purged.
 */
class UndoDeleteTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $this->term = Term::withoutGlobalScopes()->where('session_id', $session->id)->where('term_number', 1)->firstOrFail();
    }

    public function test_admin_deletes_a_notice_and_undoes_it(): void
    {
        $this->mock(FileUploadService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('deleteAnyType'));
        $notice = $this->makeNotice($this->admin->id, ['file_public_id' => 'schoolportal/1/notices/file']);
        $restoreUrl = route('admin.recently-deleted.restore', ['type' => 'notice', 'id' => $notice->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('undo.url', $restoreUrl);

        $this->assertSoftDeleted('notices', ['id' => $notice->id, 'deleted_by' => $this->admin->id]);

        // The list shows the Undo bar, and no longer the notice
        $this->get(route('admin.notices.index'))
            ->assertSee(__('Undo'))
            ->assertSee($restoreUrl)
            ->assertDontSee(route('admin.notices.edit', $notice));

        $this->from(route('admin.notices.index'))
            ->post($restoreUrl)
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('notices', ['id' => $notice->id, 'deleted_by' => null]);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('action', 'notice.restored')->where('entity_id', $notice->id)->exists());
    }

    public function test_only_the_author_can_restore_a_notice(): void
    {
        $notice = $this->makeNotice($this->admin->id);
        $notice->delete();

        $otherAdmin = $this->createSchoolUser('school_admin');
        $this->actingAs($otherAdmin)
            ->post(route('admin.recently-deleted.restore', ['type' => 'notice', 'id' => $notice->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('notices', ['id' => $notice->id]);
    }

    public function test_another_schools_items_cannot_be_restored(): void
    {
        $notice = $this->makeNotice($this->admin->id);
        $notice->delete();
        Notice::withTrashed()->withoutGlobalScopes()->whereKey($notice->id)->update(['school_id' => School::factory()->create()->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.recently-deleted.restore', ['type' => 'notice', 'id' => $notice->id]))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('notices', ['id' => $notice->id]);
    }

    public function test_teachers_cannot_restore_types_outside_their_portal(): void
    {
        $assignment = $this->makeAssignment(2);
        $assignment->delete();

        $this->actingAs($this->createSchoolUser('teacher', ['must_change_password' => false]))
            ->post(route('teacher.recently-deleted.restore', ['type' => 'assignment', 'id' => $assignment->id]))
            ->assertNotFound();
    }

    public function test_teacher_restoring_a_pending_notice_puts_it_back_in_the_approval_queue(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $notice = $this->makeNotice($teacher->id, ['status' => 'pending', 'is_published' => false]);
        TeacherAction::create(['school_id' => $this->school->id, 'teacher_id' => $teacher->id, 'action_type' => 'post_notice', 'entity_type' => 'notice', 'entity_id' => $notice->id, 'status' => 'pending']);

        $this->actingAs($teacher)->delete(route('teacher.notices.destroy', $notice))->assertSessionHas('undo');
        $this->assertSame(0, TeacherAction::where('entity_id', $notice->id)->count());

        $this->post(route('teacher.recently-deleted.restore', ['type' => 'notice', 'id' => $notice->id]))->assertSessionHas('success');
        $this->assertSame(1, TeacherAction::where('entity_type', 'notice')->where('entity_id', $notice->id)->where('status', 'pending')->count());
    }

    public function test_deleted_assignment_is_restored_unless_its_week_was_reused(): void
    {
        $assignment = $this->makeAssignment(5);
        $restoreUrl = route('admin.recently-deleted.restore', ['type' => 'assignment', 'id' => $assignment->id]);

        $this->actingAs($this->admin)->delete(route('admin.assignments.destroy', $assignment))->assertSessionHas('undo');
        $this->post($restoreUrl)->assertSessionHas('success');
        $this->assertNotSoftDeleted('assignments', ['id' => $assignment->id]);

        // Deleted again, then week 5 is given a new assignment: the old one can't come back over it
        $assignment->delete();
        $this->post(route('admin.assignments.store'), [
            'class_id' => $this->class->id,
            'session_id' => $this->term->session_id,
            'term_id' => $this->term->id,
            'week_number' => 5,
            'title' => 'New week 5',
        ])->assertSessionHasNoErrors();

        // The deleted one was released for good so week 5 could be reused
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
        $this->post($restoreUrl)->assertSessionHas('error');
    }

    public function test_a_week_that_already_has_an_assignment_gives_a_clear_error(): void
    {
        $this->makeAssignment(3);

        $this->actingAs($this->admin)
            ->from(route('admin.assignments.create'))
            ->post(route('admin.assignments.store'), [
                'class_id' => $this->class->id,
                'session_id' => $this->term->session_id,
                'term_id' => $this->term->id,
                'week_number' => 3,
            ])
            ->assertRedirect(route('admin.assignments.create'))
            ->assertSessionHasErrors('week_number');
    }

    public function test_exam_result_sheet_class_and_banner_deletes_can_be_undone(): void
    {
        $exam = $this->makeExam($this->admin);
        $result = $this->makeResult($this->makeStudent());
        $emptyClass = SchoolClass::create(['school_id' => $this->school->id, 'level_id' => $this->class->level_id, 'name' => 'Spare Class', 'slug' => 'spare-class']);
        $banner = SchoolAnnouncement::create(['school_id' => $this->school->id, 'title' => 'Exams week', 'content' => 'Good luck', 'priority' => 'info', 'is_active' => true, 'created_by' => $this->admin->id]);

        $this->actingAs($this->admin);
        $this->delete(route('admin.exams.destroy', $exam))->assertSessionHas('undo');
        $this->delete(route('admin.results.destroy', $result))->assertSessionHas('undo');
        $this->delete(route('admin.classes.destroy', $emptyClass))->assertSessionHas('undo');
        $this->delete(route('admin.announcements.destroy', $banner))->assertSessionHas('undo');

        // All four wait on the Recently deleted page
        $this->get(route('admin.recently-deleted.index'))
            ->assertOk()
            ->assertSee('Spare Class')
            ->assertSee('Exams week')
            ->assertSee($exam->title)
            ->assertSee(__('Result sheet: :name', ['name' => 'Ada Obi']), false);

        foreach (['exam' => $exam, 'result' => $result, 'class' => $emptyClass, 'banner' => $banner] as $type => $model) {
            $this->post(route('admin.recently-deleted.restore', ['type' => $type, 'id' => $model->id]))->assertSessionHas('success');
            $this->assertNotSoftDeleted($model->getTable(), ['id' => $model->id]);
        }
    }

    public function test_a_new_result_sheet_replaces_a_deleted_one_for_the_same_term(): void
    {
        $student = $this->makeStudent();
        $old = $this->makeResult($student);
        $old->delete();

        $this->assertNotNull($this->makeResult($student, release: true));
        $this->assertDatabaseMissing('results', ['id' => $old->id]);
    }

    public function test_class_with_score_history_cannot_be_deleted(): void
    {
        $student = $this->makeStudent();
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Maths', 'slug' => 'maths', 'is_active' => true]);
        $component = ScoreComponent::create(['school_id' => $this->school->id, 'name' => 'Exam', 'short_name' => 'EX', 'max_score' => 100, 'weight' => 100, 'sort_order' => 1, 'is_active' => true]);
        StudentSubjectScore::create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'class_id' => $this->class->id, 'subject_id' => $subject->id, 'score_component_id' => $component->id,
            'session_id' => $this->term->session_id, 'term_id' => $this->term->id, 'score' => 50, 'max_score' => 100, 'source_type' => 'manual',
        ]);
        StudentProfile::where('user_id', $student->id)->delete(); // the class is empty now, but its history remains

        $this->actingAs($this->admin)
            ->delete(route('admin.classes.destroy', $this->class))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('classes', ['id' => $this->class->id]);
    }

    public function test_a_deleted_class_name_is_explained_when_reused(): void
    {
        $spare = SchoolClass::create(['school_id' => $this->school->id, 'level_id' => $this->class->level_id, 'name' => 'Spare Class', 'slug' => 'spare-class']);
        $spare->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.classes.store'), ['name' => 'Spare Class', 'level_id' => $this->class->level_id])
            ->assertSessionHasErrors(['name' => __('A deleted class is called ":name". Restore it from Recently deleted, or choose another name.', ['name' => 'Spare Class'])]);
    }

    public function test_teacher_sees_only_their_own_deleted_items(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->makeNotice($teacher->id, ['title' => 'Teacher notice'])->delete();
        $this->makeNotice($this->admin->id, ['title' => 'Admin notice'])->delete();

        $this->actingAs($teacher)
            ->get(route('teacher.recently-deleted.index'))
            ->assertOk()
            ->assertSee('Teacher notice')
            ->assertDontSee('Admin notice');
    }

    public function test_pruning_removes_old_deleted_items_and_their_files(): void
    {
        $old = $this->makeNotice($this->admin->id, ['file_public_id' => 'old-file', 'image_public_id' => 'old-image']);
        $recent = $this->makeNotice($this->admin->id, ['file_public_id' => 'recent-file']);
        $old->delete();
        $recent->delete();
        Notice::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(Notice::RESTORE_DAYS + 1)]);

        $this->mock(FileUploadService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteAnyType')->once()->with('old-file');
            $mock->shouldReceive('deleteAnyType')->once()->with('old-image');
        });

        $this->artisan('model:prune', ['--model' => [Notice::class]])->assertSuccessful();

        $this->assertDatabaseMissing('notices', ['id' => $old->id]);
        $this->assertSoftDeleted('notices', ['id' => $recent->id]);
    }

    public function test_a_link_to_a_deleted_notice_explains_it_was_removed(): void
    {
        $student = $this->makeStudent();
        $notice = $this->makeNotice($this->admin->id);
        $notice->delete();

        $this->actingAs($student)
            ->get(route('student.notices.show', $notice->id))
            ->assertNotFound()
            ->assertSee(__('This notice is no longer available'))
            ->assertSee(route('student.notices.index'));
    }

    public function test_school_admin_downloads_the_school_data(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.export-data'))
            ->assertOk()
            ->assertDownload();

        $this->assertTrue(AuditLog::withoutGlobalScopes()
            ->where('school_id', $this->school->id)
            ->where('action', 'school.data_exported')
            ->where('user_id', $this->admin->id)
            ->exists());
    }

    public function test_teachers_cannot_download_the_school_data(): void
    {
        $this->actingAs($this->createSchoolUser('teacher', ['must_change_password' => false]))
            ->get(route('admin.settings.export-data'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeNotice(int $authorId, array $attributes = []): Notice
    {
        return Notice::create(array_merge([
            'school_id' => $this->school->id,
            'title' => 'School Fair',
            'content' => 'Join us on Friday.',
            'is_published' => true,
            'status' => 'approved',
            'published_at' => now(),
            'created_by' => $authorId,
        ], $attributes));
    }

    private function makeAssignment(int $week): Assignment
    {
        return Assignment::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'session_id' => $this->term->session_id,
            'term_id' => $this->term->id,
            'week_number' => $week,
            'title' => "Week {$week}",
            'uploaded_by' => $this->admin->id,
            'status' => 'approved',
        ]);
    }

    private function makeExam(User $creator): Exam
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Basic Science', 'slug' => 'basic-science', 'is_active' => true]);

        return Exam::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $subject->id,
            'session_id' => $this->term->session_id,
            'term_id' => $this->term->id,
            'title' => 'Science Test',
            'category' => 'exam',
            'source_type' => 'manual',
            'max_score' => 10,
            'total_points' => 10,
            'passing_score' => 50,
            'max_attempts' => 1,
            'status' => 'draft',
            'created_by' => $creator->id,
        ]);
    }

    private function makeStudent(): User
    {
        $student = $this->createSchoolUser('student', ['name' => 'Ada Obi', 'must_change_password' => false]);
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);

        return $student;
    }

    private function makeResult(User $student, bool $release = false): Result
    {
        if ($release) {
            Result::releaseDeletedSlot($student->id, $this->term->session_id, $this->term->id);
        }

        return Result::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'session_id' => $this->term->session_id,
            'term_id' => $this->term->id,
            'class_id' => $this->class->id,
            'file_url' => 'https://res.cloudinary.com/demo/raw/upload/result.pdf',
            'file_public_id' => 'result',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
