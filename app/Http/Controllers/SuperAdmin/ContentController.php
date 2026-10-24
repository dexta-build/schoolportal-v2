<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Game;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(Request $request): View
    {
        // Platform-wide totals (bypass tenant global scope)
        $totals = [
            'quizzes' => Quiz::withoutGlobalScopes()->count(),
            'games' => Game::withoutGlobalScopes()->count(),
            'exams' => Exam::withoutGlobalScope('tenant')->count(),
            'results' => Result::withoutGlobalScope('tenant')->count(),
            'assignments' => Assignment::withoutGlobalScope('tenant')->count(),
        ];

        $pendingCounts = [
            'quizzes' => Quiz::withoutGlobalScopes()->where('status', 'pending')->count(),
            'exams' => Exam::withoutGlobalScope('tenant')->where('status', 'pending')->count(),
            'results' => Result::withoutGlobalScope('tenant')->where('status', 'pending')->count(),
            'assignments' => Assignment::withoutGlobalScope('tenant')->where('status', 'pending')->count(),
        ];

        $query = School::tenants()
            ->withCount([
                'quizzes as quizzes_total' => fn ($q) => $q->withoutGlobalScopes(),
                'quizzes as quizzes_published' => fn ($q) => $q->withoutGlobalScopes()->where('is_published', true),
                'quizzes as quizzes_pending' => fn ($q) => $q->withoutGlobalScopes()->where('status', 'pending'),
                'games as games_total' => fn ($q) => $q->withoutGlobalScopes(),
                'games as games_published' => fn ($q) => $q->withoutGlobalScopes()->where('is_published', true),
                'exams as exams_total' => fn ($q) => $q->withoutGlobalScopes(),
                'exams as exams_published' => fn ($q) => $q->withoutGlobalScopes()->where('is_published', true),
                'results as results_total' => fn ($q) => $q->withoutGlobalScopes(),
                'results as results_approved' => fn ($q) => $q->withoutGlobalScopes()->where('status', 'approved'),
                'results as results_pending' => fn ($q) => $q->withoutGlobalScopes()->where('status', 'pending'),
                'assignments as assignments_total' => fn ($q) => $q->withoutGlobalScopes(),
                'assignments as assignments_pending' => fn ($q) => $q->withoutGlobalScopes()->where('status', 'pending'),
            ]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $sort = $request->string('sort')->toString();
        match ($sort) {
            'games' => $query->orderByDesc('games_total'),
            'exams' => $query->orderByDesc('exams_total'),
            'results' => $query->orderByDesc('results_total'),
            'assignments' => $query->orderByDesc('assignments_total'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('quizzes_total'),
        };

        $schools = $query->paginate(15)->withQueryString();

        return view('super-admin.content.index', compact('schools', 'totals', 'pendingCounts'));
    }
}
