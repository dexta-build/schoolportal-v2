<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class HelpGuideTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    public function test_school_admin_help_uses_user_guide(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.help'))
            ->assertOk()
            ->assertSee('Search help guide')
            ->assertSee('DX-SchoolPortal')
            ->assertSee('Complete User Guide');
    }

    public function test_teacher_help_uses_teacher_guide(): void
    {
        $teacher = $this->createSchoolUser('teacher');

        $this->actingAs($teacher)
            ->get(route('teacher.help'))
            ->assertOk()
            ->assertSee('Search help guide')
            ->assertSee('DX-SchoolPortal')
            // The guide body is rendered unescaped, so match its heading as raw HTML
            ->assertSee("Teacher's Complete Guide", false)
            ->assertDontSee("Student's Complete Guide", false)
            ->assertDontSee("Parent's Complete Guide", false);
    }

    public function test_student_help_uses_student_guide(): void
    {
        $student = $this->createSchoolUser('student');

        $this->actingAs($student)
            ->get(route('student.help'))
            ->assertOk()
            ->assertSee('Search help guide')
            ->assertSee('DX-SchoolPortal')
            ->assertSee("Student's Complete Guide", false)
            ->assertDontSee("Teacher's Complete Guide", false)
            ->assertDontSee("Parent's Complete Guide", false);
    }

    public function test_parent_help_uses_parent_guide(): void
    {
        $parent = $this->createSchoolUser('parent', ['level_id' => null]);

        $this->actingAs($parent)
            ->get(route('parent.help'))
            ->assertOk()
            ->assertSee('Search help guide')
            ->assertSee('DX-SchoolPortal')
            ->assertSee("Parent's Complete Guide", false)
            ->assertDontSee("Teacher's Complete Guide", false)
            ->assertDontSee("Student's Complete Guide", false);
    }
}
