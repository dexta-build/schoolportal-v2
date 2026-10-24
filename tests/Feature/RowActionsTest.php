<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * List pages put row actions in a "⋯" menu with written labels; deletes ask through the app's dialog.
 */
class RowActionsTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    public function test_admin_lists_render_labelled_action_menus(): void
    {
        $student = $this->createSchoolUser('student', ['name' => 'Ada Obi']);
        StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);
        $this->createSchoolUser('teacher', ['name' => 'Mrs Ade']);

        $this->actingAs($this->admin);

        $pages = [
            'admin.students.index' => 'Deactivate (block login)',
            'admin.teachers.index' => 'Deactivate (block login)',
            'admin.classes.index' => 'Edit class',
            'admin.levels.index' => 'Edit level',
            'admin.sessions.index' => 'Edit dates',
        ];

        foreach ($pages as $route => $label) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee(__('Actions'))
                ->assertSee(__($label));
        }

        foreach (['admin.parents.index', 'admin.subjects.index', 'admin.exams.index', 'admin.results.index', 'admin.assignments.index', 'admin.announcements.index', 'admin.grading.index', 'notifications.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_notice_menu_offers_publish_actions_and_a_confirmed_delete(): void
    {
        Notice::create([
            'school_id' => $this->school->id,
            'title' => 'Sports Day',
            'content' => 'Friday',
            'is_published' => true,
            'status' => 'approved',
            'published_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.notices.index'))
            ->assertOk()
            ->assertSee(__('Actions for :name', ['name' => 'Sports Day']))
            ->assertSee(__('Unpublish (hide from readers)'))
            ->assertSee('appConfirm', false)
            ->assertSee(__('Readers will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.'));
    }
}
