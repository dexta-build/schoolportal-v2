<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Gradebook;
use App\Livewire\Admin\TheoryGrader;
use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\Notice;
use App\Models\ParentStudent;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\SchoolSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * A user of school A must never reach school B's records by putting B's IDs in the URL.
 * Requests go through the real HTTP stack, so the order in which the tenant is resolved and
 * route models are loaded is what is being tested.
 */
class CrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private School $otherSchool;

    /** @var array<string, int> */
    private array $other = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->otherSchool = app(SchoolSetupService::class)->create([
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
        $sid = $this->otherSchool->id;

        $class = SchoolClass::withoutGlobalScopes()->where('school_id', $sid)->firstOrFail();
        $student = User::factory()->create(['school_id' => $sid, 'role' => 'student', 'level_id' => $class->level_id]);
        StudentProfile::withoutGlobalScopes()->create(['user_id' => $student->id, 'school_id' => $sid, 'class_id' => $class->id]);
        $teacher = User::factory()->create(['school_id' => $sid, 'role' => 'teacher']);
        $parent = User::factory()->create(['school_id' => $sid, 'role' => 'parent']);
        ParentStudent::withoutGlobalScopes()->create(['parent_id' => $parent->id, 'student_id' => $student->id, 'school_id' => $sid]);
        $subject = Subject::withoutGlobalScopes()->create(['school_id' => $sid, 'name' => 'Other Maths', 'slug' => 'other-maths', 'short_name' => 'OM', 'category' => 'science', 'is_active' => true]);
        $session = AcademicSession::withoutGlobalScopes()->where('school_id', $sid)->firstOrFail();
        $notice = Notice::withoutGlobalScopes()->create(['school_id' => $sid, 'title' => 'Other notice', 'content' => 'Secret', 'is_published' => true, 'status' => 'approved', 'published_at' => now()]);
        $exam = Exam::withoutGlobalScopes()->create([
            'school_id' => $sid, 'class_id' => $class->id, 'subject_id' => $subject->id, 'session_id' => $session->id,
            'term_id' => $session->terms()->withoutGlobalScopes()->value('id'), 'title' => 'Other Exam', 'category' => 'exam',
            'source_type' => 'manual', 'max_score' => 10, 'total_points' => 10, 'passing_score' => 50, 'max_attempts' => 1,
            'status' => 'approved', 'is_published' => true, 'published_at' => now(),
            'created_by' => User::withoutGlobalScopes()->where('school_id', $sid)->where('role', 'school_admin')->value('id'),
        ]);

        $this->other = [
            'class' => $class->id,
            'level' => SchoolLevel::withoutGlobalScopes()->where('school_id', $sid)->value('id'),
            'session' => $session->id,
            'student' => $student->id,
            'teacher' => $teacher->id,
            'parent' => $parent->id,
            'subject' => $subject->id,
            'notice' => $notice->id,
            'exam' => $exam->id,
        ];

        // Each request resolves the tenant itself, exactly as in production
        app()->forgetInstance('current.school');
    }

    public function test_school_admin_cannot_open_another_schools_records(): void
    {
        $this->assertBlocked($this->admin, [
            "/portal/admin/students/{$this->other['student']}",
            "/portal/admin/students/{$this->other['student']}/edit",
            "/portal/admin/students/{$this->other['student']}/full-profile-export",
            "/portal/admin/classes/{$this->other['class']}/edit",
            "/portal/admin/levels/{$this->other['level']}/edit",
            "/portal/admin/sessions/{$this->other['session']}/edit",
            "/portal/admin/subjects/{$this->other['subject']}/edit",
            "/portal/admin/teachers/{$this->other['teacher']}/edit",
            "/portal/admin/parents/{$this->other['parent']}/edit",
            "/portal/admin/notices/{$this->other['notice']}/edit",
            "/portal/admin/exams/{$this->other['exam']}",
            "/portal/admin/exams/{$this->other['exam']}/results",
            "/portal/admin/exams/{$this->other['exam']}/results/export-csv",
            "/portal/admin/exams/{$this->other['exam']}/reset-access",
            "/portal/admin/scores/class-students/{$this->other['class']}",
        ]);
    }

    public function test_student_cannot_open_another_schools_records(): void
    {
        $student = $this->createSchoolUser('student', ['must_change_password' => false]);
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);

        $this->assertBlocked($student, [
            "/portal/student/notices/{$this->other['notice']}",
            "/portal/student/exams/{$this->other['exam']}",
        ]);
    }

    public function test_parent_cannot_open_another_schools_child_or_notice(): void
    {
        $parent = $this->createSchoolUser('parent', ['must_change_password' => false]);

        $this->assertBlocked($parent, [
            "/portal/parent/children/{$this->other['student']}",
            "/portal/parent/children/{$this->other['student']}/results",
            "/portal/parent/notices/{$this->other['notice']}",
        ]);
    }

    public function test_teacher_cannot_open_another_schools_notice(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);

        $this->assertBlocked($teacher, [
            "/portal/teacher/notices/{$this->other['notice']}",
        ]);
    }

    public function test_school_admin_cannot_change_or_delete_another_schools_records(): void
    {
        foreach ([
            ['delete', "/portal/admin/students/{$this->other['student']}"],
            ['put', "/portal/admin/classes/{$this->other['class']}"],
            ['delete', "/portal/admin/subjects/{$this->other['subject']}"],
            ['delete', "/portal/admin/notices/{$this->other['notice']}"],
        ] as [$method, $path]) {
            app()->forgetInstance('current.school');
            $status = $this->actingAs($this->admin)->{$method}($path, ['name' => 'Hijacked'])->getStatusCode();
            $this->assertContains($status, [403, 404], "{$method} {$path} returned {$status}");
        }

        $this->assertTrue(User::withoutGlobalScopes()->whereKey($this->other['student'])->exists());
        $this->assertNotSame('Hijacked', SchoolClass::withoutGlobalScopes()->find($this->other['class'])->name);
        $this->assertTrue(Notice::withoutGlobalScopes()->whereKey($this->other['notice'])->exists());
    }

    public function test_gradebook_will_not_load_another_schools_class(): void
    {
        $this->actingAs($this->admin);
        app()->instance('current.school', $this->school);

        Livewire::test(Gradebook::class, ['role' => 'admin'])
            ->set('classId', $this->other['class'])
            ->assertSet('grid', [])
            ->assertSet('errorMessage', 'You do not have access to this class.');
    }

    public function test_gradebook_ignores_scores_for_students_outside_the_class(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->class->update(['teacher_id' => $teacher->id]);
        $component = ScoreComponent::create(['school_id' => $this->school->id, 'name' => 'CA', 'short_name' => 'CA', 'max_score' => 20, 'weight' => 100, 'sort_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Maths', 'slug' => 'maths', 'short_name' => 'MTH', 'category' => 'science', 'is_active' => true]);
        $this->class->subjects()->attach($subject->id, ['school_id' => $this->school->id]);

        $this->actingAs($teacher);
        app()->instance('current.school', $this->school);

        Livewire::test(Gradebook::class, ['role' => 'teacher'])
            ->set('termId', Term::withoutGlobalScopes()->where('school_id', $this->school->id)->value('id'))
            ->set('classId', $this->class->id)
            ->call('saveScores', [[
                'student_id' => $this->other['student'], // another school's student
                'subject_id' => $subject->id,
                'component_id' => $component->id,
                'score' => 20,
            ]]);

        $this->assertFalse(StudentSubjectScore::withoutGlobalScopes()->where('student_id', $this->other['student'])->exists());
    }

    public function test_theory_grader_role_and_grading_data_cannot_be_changed_from_the_browser(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id,
            'subject_id' => Subject::create(['school_id' => $this->school->id, 'name' => 'Bio', 'slug' => 'bio', 'short_name' => 'BIO', 'category' => 'science', 'is_active' => true])->id,
            'session_id' => AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->value('id'),
            'term_id' => Term::withoutGlobalScopes()->where('school_id', $this->school->id)->value('id'),
            'title' => 'Bio Exam', 'category' => 'exam', 'source_type' => 'manual', 'max_score' => 10, 'total_points' => 10,
            'passing_score' => 50, 'max_attempts' => 1, 'status' => 'approved', 'is_published' => true, 'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin);
        app()->instance('current.school', $this->school);

        $grader = Livewire::test(TheoryGrader::class, ['examId' => $exam->id, 'role' => 'admin']);

        foreach (['role' => 'teacher', 'examId' => $this->other['exam'], 'currentQuestion' => ['id' => 1, 'points' => 1000]] as $property => $value) {
            try {
                $grader->set($property, $value);
                $this->fail("{$property} could be changed from the browser");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_teacher_cannot_open_the_grader_as_an_admin(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->actingAs($teacher);
        app()->instance('current.school', $this->school);

        Livewire::test(TheoryGrader::class, ['examId' => 1, 'role' => 'admin'])->assertForbidden();
    }

    /**
     * A school's records belong to the school: the platform can't delete its people, so no school
     * can say its records were changed by the platform.
     */
    public function test_super_admin_cannot_delete_a_schools_students_teachers_or_parents(): void
    {
        $platform = School::withoutGlobalScopes()->firstOrCreate(
            ['slug' => 'platform'],
            ['name' => 'Platform', 'email' => 'platform@schoolportal.test', 'country' => 'Nigeria', 'is_active' => true],
        );
        $superAdmin = User::withoutGlobalScopes()->create([
            'school_id' => $platform->id, 'name' => 'Super', 'email' => 'super@schoolportal.test', 'username' => 'super',
            'password' => bcrypt('Password1!'), 'role' => 'super_admin', 'is_active' => true, 'email_verified_at' => now(),
        ])->refresh();

        foreach (['students' => $this->other['student'], 'teachers' => $this->other['teacher'], 'parents' => $this->other['parent']] as $type => $id) {
            app()->forgetInstance('current.school');
            $status = $this->actingAs($superAdmin)->delete("/portal/super-admin/{$type}/{$id}")->getStatusCode();

            $this->assertContains($status, [404, 405], "DELETE super-admin/{$type} returned {$status}");
            $this->assertNotNull(User::withoutGlobalScopes()->find($id), "Super admin deleted a school's {$type} record");
        }
    }

    public function test_notice_cannot_be_opened_by_someone_it_was_not_addressed_to(): void
    {
        $student = $this->createSchoolUser('student', ['must_change_password' => false]);
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);
        $parent = $this->createSchoolUser('parent', ['must_change_password' => false]);
        ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id, 'school_id' => $this->school->id]);
        $otherClass = SchoolClass::create(['school_id' => $this->school->id, 'level_id' => $this->level->id, 'name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $staffOnly = Notice::create(['school_id' => $this->school->id, 'title' => 'Staff meeting', 'content' => 'Teachers only', 'target_roles' => ['teacher'], 'is_published' => true, 'status' => 'approved', 'published_at' => now()]);
        $otherClassOnly = Notice::create(['school_id' => $this->school->id, 'title' => 'Other class trip', 'content' => 'x', 'target_classes' => [$otherClass->id], 'is_published' => true, 'status' => 'approved', 'published_at' => now()]);
        $ownClass = Notice::create(['school_id' => $this->school->id, 'title' => 'Our class trip', 'content' => 'x', 'target_classes' => [$this->class->id], 'is_published' => true, 'status' => 'approved', 'published_at' => now()]);

        app()->forgetInstance('current.school');
        $this->actingAs($student)->get("/portal/student/notices/{$staffOnly->id}")->assertNotFound();
        $this->actingAs($student)->get("/portal/student/notices/{$otherClassOnly->id}")->assertNotFound();
        $this->actingAs($student)->get("/portal/student/notices/{$ownClass->id}")->assertOk();

        $this->actingAs($parent)->get("/portal/parent/notices/{$staffOnly->id}")->assertNotFound();
        $this->actingAs($parent)->get("/portal/parent/notices/{$otherClassOnly->id}")->assertNotFound();
        $this->actingAs($parent)->get("/portal/parent/notices/{$ownClass->id}")->assertOk();
    }

    public function test_teacher_can_only_change_their_own_question_bank_questions(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $adminsQuestion = QuestionBank::create(['school_id' => $this->school->id, 'created_by' => $this->admin->id, 'type' => 'true_false', 'question_text' => 'Admin question', 'points' => 1, 'difficulty' => 'easy']);
        $ownQuestion = QuestionBank::create(['school_id' => $this->school->id, 'created_by' => $teacher->id, 'type' => 'true_false', 'question_text' => 'My question', 'points' => 1, 'difficulty' => 'easy']);

        app()->forgetInstance('current.school');
        $this->actingAs($teacher)->delete("/portal/teacher/question-bank/{$adminsQuestion->id}")->assertForbidden();
        $this->assertNotNull(QuestionBank::withoutGlobalScopes()->find($adminsQuestion->id));

        // Their own question: removed, and they are sent back to their own page (not an admin URL)
        $this->actingAs($teacher)->delete("/portal/teacher/question-bank/{$ownQuestion->id}")
            ->assertRedirect(route('teacher.question-bank.index'));
        $this->assertNull(QuestionBank::withoutGlobalScopes()->find($ownQuestion->id));
    }

    /**
     * @param  list<string>  $paths
     */
    private function assertBlocked(User $user, array $paths): void
    {
        $leaks = [];

        foreach ($paths as $path) {
            app()->forgetInstance('current.school');
            $status = $this->actingAs($user)->get($path)->getStatusCode();

            if (! in_array($status, [403, 404], true)) {
                $leaks[] = "{$path} → {$status}";
            }
        }

        $this->assertSame([], $leaks, "A {$user->role} of school A reached school B's records:\n".implode("\n", $leaks));
    }
}
