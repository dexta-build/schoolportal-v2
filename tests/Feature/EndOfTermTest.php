<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\EndOfTermService;
use App\Services\ScoreAggregationService;
use App\Services\ScoreCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

/**
 * Walks one class through the end of a term and checks each step reports what is missing.
 */
class EndOfTermTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private Term $term;

    private Subject $subject;

    private ScoreComponent $component;

    /** @var list<User> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $session = AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->firstOrFail();
        $session->update(['is_current' => true, 'status' => 'active']);
        $this->term = Term::withoutGlobalScopes()->where('session_id', $session->id)->where('term_number', 1)->firstOrFail();
        $this->term->update(['is_current' => true, 'status' => 'active']);

        $this->subject = Subject::create(['school_id' => $this->school->id, 'name' => 'Mathematics', 'slug' => 'maths', 'short_name' => 'MTH', 'category' => 'science', 'is_active' => true]);
        $teacher = $this->createSchoolUser('teacher', ['name' => 'Mrs Ade']);
        ClassSubject::create(['school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $this->subject->id, 'teacher_id' => $teacher->id]);
        $this->component = ScoreComponent::create(['school_id' => $this->school->id, 'name' => 'Exam', 'short_name' => 'EX', 'max_score' => 100, 'weight' => 100, 'sort_order' => 1, 'is_active' => true]);

        foreach (['Ada', 'Bola'] as $name) {
            $student = $this->createSchoolUser('student', ['name' => $name]);
            StudentProfile::create(['user_id' => $student->id, 'school_id' => $this->school->id, 'class_id' => $this->class->id]);
            $this->students[] = $student;
        }
    }

    public function test_each_step_shows_what_is_missing_until_the_term_is_wrapped_up(): void
    {
        // 1. One of two scores entered — Maths is missing one, and its teacher is named
        $this->enterScore($this->students[0], 70);
        $steps = $this->steps();
        $this->assertSame(EndOfTermService::STATUS_IN_PROGRESS, $steps['scores']['status']);
        $this->assertStringContainsString('Mathematics — 1 score missing', $steps['scores']['items'][0]['text']);
        $this->assertSame('Mrs Ade', $steps['scores']['items'][0]['who']);

        // 2. All scores in, not locked yet
        $this->enterScore($this->students[1], 55);
        $steps = $this->steps();
        $this->assertSame(EndOfTermService::STATUS_DONE, $steps['scores']['status']);
        $this->assertNotSame(EndOfTermService::STATUS_DONE, $steps['lock']['status']);

        app(ScoreCorrectionService::class)->lockClass($this->class->id, $this->term->id, $this->admin);
        $steps = $this->steps();
        $this->assertSame(EndOfTermService::STATUS_DONE, $steps['lock']['status']);
        $this->assertStringContainsString('2 report cards not generated', $steps['generate']['items'][0]['text']);

        // 3. Report cards generated — they now wait for the class teacher's comment
        app(ScoreAggregationService::class)->generateClassReports($this->class->id, $this->term->session_id, $this->term->id, $this->school->id);
        $steps = $this->steps();
        $this->assertSame(EndOfTermService::STATUS_DONE, $steps['generate']['status']);
        $this->assertStringContainsString('waiting for the class teacher', $steps['approve']['items'][0]['text']);

        // 4. Submitted, then approved — waiting to be published
        StudentTermReport::withoutGlobalScopes()->update(['status' => 'pending_approval']);
        $this->assertStringContainsString('waiting for your approval', $this->steps()['approve']['items'][0]['text']);

        StudentTermReport::withoutGlobalScopes()->update(['status' => 'approved']);
        $steps = $this->steps();
        $this->assertSame(EndOfTermService::STATUS_DONE, $steps['approve']['status']);
        $this->assertStringContainsString('approved but not yet published', $steps['publish']['items'][0]['text']);

        // 5. Published — every step done
        StudentTermReport::withoutGlobalScopes()->update(['status' => 'published']);
        $steps = $this->steps();
        foreach (['scores', 'lock', 'generate', 'approve', 'publish'] as $key) {
            $this->assertSame(EndOfTermService::STATUS_DONE, $steps[$key]['status'], "{$key} is not done");
        }
    }

    public function test_promotion_step_only_appears_in_the_last_term_of_the_session(): void
    {
        $this->assertArrayNotHasKey('promote', $this->steps());

        $lastTerm = Term::withoutGlobalScopes()->where('session_id', $this->term->session_id)->orderByDesc('term_number')->firstOrFail();
        $this->assertArrayHasKey('promote', $this->steps($lastTerm));
    }

    public function test_admin_sees_the_end_of_term_page(): void
    {
        $this->enterScore($this->students[0], 70);

        $this->actingAs($this->admin)
            ->get(route('admin.end-of-term'))
            ->assertOk()
            ->assertSee('Enter all scores')
            ->assertSee('Mathematics — 1 score missing')
            ->assertSee('Teacher: Mrs Ade');
    }

    public function test_teacher_cannot_open_the_end_of_term_page(): void
    {
        $this->actingAs($this->createSchoolUser('teacher'))
            ->get(route('admin.end-of-term'))
            ->assertForbidden();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function steps(?Term $term = null): array
    {
        return collect(app(EndOfTermService::class)->overview($this->school->id, $term ?? $this->term)['steps'])->keyBy('key')->all();
    }

    private function enterScore(User $student, float $score): void
    {
        StudentSubjectScore::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'session_id' => $this->term->session_id,
            'term_id' => $this->term->id,
            'score_component_id' => $this->component->id,
            'score' => $score,
            'max_score' => 100,
            'source_type' => 'manual',
        ]);
    }
}
