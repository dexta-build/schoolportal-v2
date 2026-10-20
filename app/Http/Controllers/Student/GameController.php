<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePlay;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        $games = Game::with(['class:id,name', 'plays' => fn ($q) => $q->where('student_id', $student->id)])
            ->published()
            ->forClass($classId)
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('student.games.index', compact('games'));
    }

    public function play(Game $game): View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ($game->class_id !== $classId || ! $game->isAvailableForStudent()) {
            abort(403, 'This game is not available.');
        }

        $bestPlay = $game->bestPlayForStudent($student->id);
        $playCount = $game->playCountForStudent($student->id);

        return view('student.games.play', compact('game', 'bestPlay', 'playCount'));
    }

    public function complete(Request $request, Game $game): JsonResponse
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ($game->class_id !== $classId || ! $game->isAvailableForStudent()) {
            abort(403);
        }

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0'],
            'max_score' => ['required', 'integer', 'min:1'],
            'time_spent_seconds' => ['required', 'integer', 'min:0'],
            'game_state' => ['nullable', 'array'],
            'submission_id' => ['nullable', 'string', 'max:64'],
        ]);

        // The browser retries until it gets a response, so a retry after a lost response
        // must not record the same play twice
        $submissionId = $validated['submission_id'] ?? null;
        if ($submissionId !== null) {
            $existing = GamePlay::where('game_id', $game->id)
                ->where('student_id', $student->id)
                ->where('game_state->submission_id', $submissionId)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'play_id' => $existing->id,
                    'percentage' => (float) $existing->percentage,
                    'new_achievements' => [],
                ]);
            }
        }

        $percentage = round(($validated['score'] / $validated['max_score']) * 100, 2);

        $play = GamePlay::create([
            'game_id' => $game->id,
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'score' => $validated['score'],
            'max_score' => $validated['max_score'],
            'percentage' => $percentage,
            'time_spent_seconds' => $validated['time_spent_seconds'],
            'completed' => true,
            'game_state' => $submissionId !== null
                ? [...($validated['game_state'] ?? []), 'submission_id' => $submissionId]
                : ($validated['game_state'] ?? null),
            'started_at' => now()->subSeconds($validated['time_spent_seconds']),
            'completed_at' => now(),
        ]);

        // Check achievements after game completion
        $newAchievements = [];
        try {
            $newAchievements = app(AchievementService::class)->processGameCompletion($student);
        } catch (\Throwable) {
            // Don't break game flow if achievement check fails
        }

        return response()->json([
            'success' => true,
            'play_id' => $play->id,
            'percentage' => $percentage,
            'new_achievements' => $newAchievements,
        ]);
    }

    public function leaderboard(Game $game): View
    {
        $student = auth()->user();
        $classId = $student->studentProfile?->class_id;

        if ($game->class_id !== $classId) {
            abort(403);
        }

        // Get the best play per student
        $leaderboard = GamePlay::where('game_id', $game->id)
            ->where('completed', true)
            ->with('student:id,name,username')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($plays) => $plays->sortByDesc('score')->first())
            ->sortByDesc('score')
            ->values();

        return view('student.games.leaderboard', compact('game', 'leaderboard'));
    }
}
