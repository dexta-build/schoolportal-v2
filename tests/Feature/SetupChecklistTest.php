<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\GradingScale;
use App\Models\ParentStudent;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Services\SetupChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class SetupChecklistTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    public function test_new_school_sees_the_checklist_with_its_progress(): void
    {
        $steps = collect(app(SetupChecklistService::class)->steps($this->school))->pluck('done', 'key');

        // School setup already creates classes and the first session & term
        $this->assertTrue($steps['classes']);
        $this->assertFalse($steps['subjects']);
        $this->assertFalse($steps['parents']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Add subjects and give them to classes');
    }

    public function test_steps_are_ticked_from_real_data_and_the_checklist_disappears_when_done(): void
    {
        $this->completeEverySetupStep();

        $steps = app(SetupChecklistService::class)->steps($this->school->fresh());
        $this->assertSame([], collect($steps)->where('done', false)->pluck('key')->all());

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Getting started');
    }

    public function test_admin_can_hide_the_checklist(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.setup-checklist.dismiss'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Getting started');
    }

    private function completeEverySetupStep(): void
    {
        $subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Maths', 'slug' => 'maths', 'short_name' => 'MTH', 'category' => 'science', 'is_active' => true]);
        ClassSubject::create(['school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $subject->id]);
        ScoreComponent::create(['school_id' => $this->school->id, 'name' => 'Exam', 'short_name' => 'EX', 'max_score' => 100, 'weight' => 100, 'sort_order' => 1, 'is_active' => true]);
        GradingScale::create(['school_id' => $this->school->id, 'name' => 'Default', 'is_default' => true, 'is_active' => true]);

        $teacher = $this->createSchoolUser('teacher');
        $this->class->update(['teacher_id' => $teacher->id]);

        $student = $this->createSchoolUser('student');
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);
        $parent = $this->createSchoolUser('parent');
        ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id, 'school_id' => $this->school->id]);
    }
}
