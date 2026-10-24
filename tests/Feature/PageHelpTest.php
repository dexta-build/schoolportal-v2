<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Support\PageHelp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * "How this page works" guides on the hardest pages, with an optional video.
 */
class PageHelpTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();
    }

    public function test_admin_pages_show_their_guide(): void
    {
        $this->actingAs($this->admin);

        $pages = [
            'admin.scores.index' => 'Lock All',
            'admin.exams.create' => 'Generate Questions',
            'admin.scores.reports' => 'Bulk Approve',
        ];

        foreach ($pages as $route => $stepText) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee(__('How this page works'))
                ->assertSee($stepText);
        }
    }

    public function test_teachers_get_the_teacher_version_of_the_steps(): void
    {
        $teacher = $this->createSchoolUser('teacher', ['must_change_password' => false]);
        $this->class->update(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertSee(__('Save. It goes to the school admin for approval, and students see it once it is approved.'))
            ->assertDontSee(__('If a student misses a CBT or is cut off, use "Let a student retake / resume" in the CBT list.'));

        $this->get(route('teacher.scores.reports'))
            ->assertOk()
            ->assertSee(__('How this page works'))
            ->assertSee(__('Click "Submit All Draft Reports for Approval".'));
    }

    public function test_watch_video_appears_only_when_a_video_is_linked(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.scores.reports'))->assertDontSee(__('Watch video'));

        PlatformSetting::set('help_video_report_cards', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s');

        $this->get(route('admin.scores.reports'))
            ->assertSee(__('Watch video'))
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', false);
    }

    public function test_video_links_are_turned_into_embed_links(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', PageHelp::embedUrl('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', PageHelp::embedUrl('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame('https://player.vimeo.com/video/76979871', PageHelp::embedUrl('https://vimeo.com/76979871'));
        $this->assertNull(PageHelp::embedUrl('https://example.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(PageHelp::embedUrl(''));
    }
}
