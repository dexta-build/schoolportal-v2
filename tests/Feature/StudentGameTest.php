<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithSchoolContext;
use Tests\TestCase;

class StudentGameTest extends TestCase
{
    use RefreshDatabase;
    use WithSchoolContext;

    private User $student;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchoolContext();

        $this->student = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
            'level_id' => $this->level->id,
            'must_change_password' => false,
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $this->game = Game::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'session_id' => AcademicSession::withoutGlobalScopes()->where('school_id', $this->school->id)->value('id'),
            'term_id' => Term::withoutGlobalScopes()->where('school_id', $this->school->id)->value('id'),
            'title' => 'Science Flashcards',
            'game_type' => 'flashcard',
            'source_type' => 'manual',
            'game_data' => ['cards' => [['front' => 'H2O', 'back' => 'Water']]],
            'difficulty' => 'easy',
            'is_published' => true,
            'published_at' => now(),
            'status' => 'approved',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_student_can_record_a_completed_play(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('student.games.complete', $this->game), [
                'score' => 8,
                'max_score' => 10,
                'time_spent_seconds' => 45,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'percentage' => 80]);

        $this->assertSame(1, GamePlay::where('game_id', $this->game->id)->count());
    }

    public function test_retried_score_with_same_submission_id_is_recorded_once(): void
    {
        $payload = [
            'submission_id' => 'a1b2c3d4-retry-test',
            'score' => 8,
            'max_score' => 10,
            'time_spent_seconds' => 45,
        ];

        // First response was lost on the way back, so the browser sends the same play again
        $first = $this->actingAs($this->student)->postJson(route('student.games.complete', $this->game), $payload)->assertOk();
        $second = $this->actingAs($this->student)->postJson(route('student.games.complete', $this->game), $payload)->assertOk();

        $this->assertSame($first->json('play_id'), $second->json('play_id'));
        $this->assertSame(1, GamePlay::where('game_id', $this->game->id)->count());
        $this->assertSame('a1b2c3d4-retry-test', GamePlay::first()->game_state['submission_id']);
    }

    public function test_different_submission_ids_are_recorded_as_separate_plays(): void
    {
        foreach (['play-one', 'play-two'] as $submissionId) {
            $this->actingAs($this->student)
                ->postJson(route('student.games.complete', $this->game), [
                    'submission_id' => $submissionId,
                    'score' => 5,
                    'max_score' => 10,
                    'time_spent_seconds' => 30,
                ])
                ->assertOk();
        }

        $this->assertSame(2, GamePlay::where('game_id', $this->game->id)->count());
    }
}
