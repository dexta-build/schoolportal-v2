<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\SchoolClass;
use App\Models\ScoreComponent;
use App\Models\Subject;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class TeacherExamTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $teacher;

    private Subject $subject;

    private ScoreComponent $component;

    private AcademicSession $session;

    private Term $term;

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

        $this->teacher = $this->createSchoolUser('teacher');

        // Assign teacher to the test class
        $this->class->update(['teacher_id' => $this->teacher->id]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathematics',
            'slug' => 'mathematics',
            'short_name' => 'MTH',
            'category' => 'science',
            'is_active' => true,
        ]);

        ClassSubject::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
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
    }

    public function test_teacher_can_view_exams_index(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.exams.index'))
            ->assertOk()
            ->assertViewIs('teacher.exams.index');
    }

    public function test_teacher_can_view_cbt_create_form(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertViewIs('teacher.exams.create')
            ->assertSee('addSubject($el)', false)
            ->assertSee('saveSubject($el)', false);
    }

    public function test_teacher_can_create_exam_with_pending_status(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Math Quiz',
                'class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'score_component_id' => $this->component->id,
                'source_type' => 'manual',
                'max_score' => 100,
                'passing_score' => 50,
                'max_attempts' => 1,
                'questions' => [
                    [
                        'type' => 'multiple_choice',
                        'question_text' => 'What is 2 + 2?',
                        'options' => ['3', '4', '5', '6'],
                        'correct_answer' => '4',
                        'points' => 10,
                    ],
                ],
            ])
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseHas('exams', [
            'title' => 'Math Quiz',
            'school_id' => $this->school->id,
            'status' => 'pending', // teacher-created → needs approval
            'created_by' => $this->teacher->id,
        ]);
    }

    public function test_teacher_cannot_create_exam_for_unassigned_class(): void
    {
        $otherClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'level_id' => $this->level->id,
            'name' => 'Other Class',
            'slug' => 'other-class',
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Unauthorized Exam',
                'class_id' => $otherClass->id,
                'subject_id' => $this->subject->id,
                'source_type' => 'manual',
                'max_score' => 100,
                'passing_score' => 50,
                'max_attempts' => 1,
                'questions' => [
                    [
                        'type' => 'multiple_choice',
                        'question_text' => 'Test?',
                        'options' => ['A', 'B', 'C', 'D'],
                        'correct_answer' => 'A',
                        'points' => 10,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_teacher_inline_subject_creation_assigns_subject_to_selected_class(): void
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('teacher.exams.store-subject'), [
                'name' => 'Basic Technology',
                'class_id' => $this->class->id,
            ])
            ->assertOk();

        $subjectId = $response->json('subject.id');

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectId,
            'school_id' => $this->school->id,
            'created_by' => $this->teacher->id,
        ]);
        $this->assertDatabaseHas('class_subject', [
            'class_id' => $this->class->id,
            'subject_id' => $subjectId,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_teacher_inline_existing_subject_requires_explicit_use_action(): void
    {
        $this->actingAs($this->teacher)
            ->postJson(route('teacher.exams.store-subject'), [
                'name' => $this->subject->name,
                'class_id' => $this->class->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', 'already_assigned')
            ->assertJsonPath('subject.id', $this->subject->id);

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.exams.store-subject'), [
                'name' => $this->subject->name,
                'subject_id' => $this->subject->id,
                'class_id' => $this->class->id,
                'assign_existing' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'assigned_existing')
            ->assertJsonPath('subject.id', $this->subject->id);
    }

    public function test_teacher_cannot_inline_add_subject_to_unassigned_class(): void
    {
        $otherClass = SchoolClass::create([
            'school_id' => $this->school->id,
            'level_id' => $this->level->id,
            'name' => 'Other Class',
            'slug' => 'other-class-inline-subject',
        ]);

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.exams.store-subject'), [
                'name' => 'Unauthorized Subject',
                'class_id' => $otherClass->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('subjects', ['slug' => 'unauthorized-subject']);
    }

    public function test_teacher_can_view_own_exam(): void
    {
        $exam = $this->createTeacherExam();

        $this->actingAs($this->teacher)
            ->get(route('teacher.exams.show', $exam))
            ->assertOk()
            ->assertViewIs('teacher.exams.show');
    }

    public function test_teacher_can_delete_own_draft_exam(): void
    {
        $exam = $this->createTeacherExam(['status' => 'draft']);

        $this->actingAs($this->teacher)
            ->delete(route('teacher.exams.destroy', $exam))
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertSoftDeleted('exams', ['id' => $exam->id]);
    }

    public function test_student_cannot_access_teacher_exam_routes(): void
    {
        $student = $this->createSchoolUser('student');

        $this->actingAs($student)
            ->get(route('teacher.exams.index'))
            ->assertForbidden();
    }

    public function test_teacher_can_view_cbt_submissions_without_exam_slug_column(): void
    {
        $exam = $this->createTeacherExam(['category' => 'assessment']);

        TeacherAction::create([
            'school_id' => $this->school->id,
            'teacher_id' => $this->teacher->id,
            'action_type' => 'create',
            'entity_type' => 'exam',
            'entity_id' => $exam->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.submissions.index'))
            ->assertOk()
            ->assertSee('Assessment');
    }

    // ── Helper ──

    private function createTeacherExam(array $overrides = []): Exam
    {
        $exam = Exam::create(array_merge([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'score_component_id' => $this->component->id,
            'session_id' => $this->session->id,
            'term_id' => $this->term->id,
            'title' => 'Teacher Exam',
            'category' => 'exam',
            'source_type' => 'manual',
            'max_score' => 100,
            'total_points' => 10,
            'passing_score' => 50,
            'status' => 'pending',
            'created_by' => $this->teacher->id,
        ], $overrides));

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'school_id' => $this->school->id,
            'type' => 'multiple_choice',
            'question_text' => 'Sample?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_answer' => 'A',
            'points' => 10,
            'sort_order' => 1,
        ]);

        return $exam;
    }
}
