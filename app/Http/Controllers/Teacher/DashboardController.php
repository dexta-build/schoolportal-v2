<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Game;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Result;
use App\Models\TeacherAction;
use App\Services\AiCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $teacher = auth()->user();
        $school = app('current.school');
        $classIds = $teacher->assignedClasses()->pluck('id');

        // ── Session/Term filtering (default to current) ──────────────
        $allSessions = AcademicSession::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        if ($request->filled('session_id')) {
            $currentSession = AcademicSession::find((int) $request->input('session_id'));
        } else {
            $currentSession = $school->currentSession();
        }

        if ($currentSession && $request->filled('term_id')) {
            $currentTerm = $currentSession->terms()->find((int) $request->input('term_id'));
        } elseif ($currentSession) {
            $currentTerm = $request->filled('session_id')
                ? $currentSession->terms()->first()
                : $school->currentTerm();
        } else {
            $currentTerm = null;
        }

        $sessionTerms = $currentSession?->terms()->orderBy('term_number')->get(['id', 'name', 'term_number', 'is_current']) ?? collect();

        // ── Assigned classes with student counts ─────────────────────
        $assignedClasses = $teacher->assignedClasses()
            ->where('is_active', true)
            ->withCount(['students' => fn ($q) => $q->whereHas('user', fn ($u) => $u->where('is_active', true))])
            ->with('level:id,name')
            ->orderBy('name')
            ->get();

        $totalStudents = $assignedClasses->sum('students_count');

        // ── Submission stats (cached 5 min) ──────────────────────────
        $statsCacheKey = "school:{$school->id}:teacher:{$teacher->id}:dashboard:stats";
        $stats = Cache::remember($statsCacheKey, now()->addMinutes(5), function () use ($teacher, $classIds): array {
            return [
                'totalResults' => Result::where('uploaded_by', $teacher->id)->count(),
                'totalAssignments' => Assignment::where('uploaded_by', $teacher->id)->count(),
                'totalNotices' => Notice::where('created_by', $teacher->id)->count(),
                'publishedQuizzes' => Quiz::whereIn('class_id', $classIds)->where('created_by', $teacher->id)->where('is_published', true)->count(),
                'publishedGames' => Game::whereIn('class_id', $classIds)->where('created_by', $teacher->id)->where('is_published', true)->count(),
                'quizAttempts' => QuizAttempt::whereHas('quiz', fn ($q) => $q->whereIn('class_id', $classIds)->where('created_by', $teacher->id))->whereIn('status', ['submitted', 'timed_out'])->count(),
                'avgQuizScore' => QuizAttempt::whereHas('quiz', fn ($q) => $q->whereIn('class_id', $classIds)->where('created_by', $teacher->id))->whereIn('status', ['submitted', 'timed_out'])->avg('percentage'),
                'publishedCbtExams' => Exam::whereIn('class_id', $classIds)->where('is_published', true)->forCategory('exam')->count(),
                'publishedAssessments' => Exam::whereIn('class_id', $classIds)->where('is_published', true)->forCategory('assessment')->count(),
                'publishedCbtAssignments' => Exam::whereIn('class_id', $classIds)->where('is_published', true)->forCategory('assignment')->count(),
                'cbtAttempts' => ExamAttempt::whereHas('exam', fn ($q) => $q->whereIn('class_id', $classIds))->whereIn('status', ['submitted', 'timed_out'])->count(),
                'avgCbtScore' => ExamAttempt::whereHas('exam', fn ($q) => $q->whereIn('class_id', $classIds))->whereIn('status', ['submitted', 'timed_out'])->avg('percentage'),
            ];
        });

        $totalResults = $stats['totalResults'];
        $totalAssignments = $stats['totalAssignments'];
        $totalNotices = $stats['totalNotices'];
        $publishedQuizzes = $stats['publishedQuizzes'];
        $publishedGames = $stats['publishedGames'];
        $quizAttempts = $stats['quizAttempts'];
        $avgQuizScore = $stats['avgQuizScore'];
        $publishedCbtExams = $stats['publishedCbtExams'];
        $publishedAssessments = $stats['publishedAssessments'];
        $publishedCbtAssignments = $stats['publishedCbtAssignments'];
        $cbtAttempts = $stats['cbtAttempts'];
        $avgCbtScore = $stats['avgCbtScore'];

        $pendingCount = TeacherAction::where('teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->count();

        // ── Rejected submissions alert ───────────────────────────────
        $rejectedSubmissions = TeacherAction::where('teacher_id', $teacher->id)
            ->where('status', 'rejected')
            ->latest('created_at')
            ->take(3)
            ->get(['id', 'action_type', 'entity_type', 'rejection_reason', 'created_at']);

        // ── Recent submissions with status ───────────────────────────
        $recentSubmissions = TeacherAction::where('teacher_id', $teacher->id)
            ->with('reviewer:id,name')
            ->latest('created_at')
            ->take(10)
            ->get();

        // ── Results upload progress per class (current term) ─────────
        $resultsProgress = collect();
        if ($currentSession && $currentTerm && $classIds->isNotEmpty()) {
            $resultsByClass = Result::whereIn('class_id', $classIds)
                ->where('session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('status', 'approved')
                ->selectRaw('class_id, count(*) as uploaded')
                ->groupBy('class_id')
                ->pluck('uploaded', 'class_id');

            $resultsProgress = $assignedClasses->map(fn ($cls) => [
                'name' => $cls->name,
                'level' => $cls->level?->name,
                'uploaded' => $resultsByClass[$cls->id] ?? 0,
                'total' => $cls->students_count,
            ]);
        }

        // ── Assignments coverage per class (current term) ────────────
        $weeksPerTerm = $school->setting('academic.weeks_per_term', 12);
        $assignmentsCoverage = collect();
        if ($currentSession && $currentTerm && $classIds->isNotEmpty()) {
            $assignmentsByClass = Assignment::whereIn('class_id', $classIds)
                ->where('session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('status', 'approved')
                ->selectRaw('class_id, count(*) as uploaded_weeks')
                ->groupBy('class_id')
                ->pluck('uploaded_weeks', 'class_id');

            $assignmentsCoverage = $assignedClasses->map(fn ($cls) => [
                'name' => $cls->name,
                'uploaded' => $assignmentsByClass[$cls->id] ?? 0,
                'total' => $weeksPerTerm,
            ]);
        }

        // ── Quiz & Game stats ────────────────────────────────────────
        $publishedQuizzes = Quiz::whereIn('class_id', $classIds)
            ->where('created_by', $teacher->id)
            ->where('is_published', true)
            ->count();

        $publishedGames = Game::whereIn('class_id', $classIds)
            ->where('created_by', $teacher->id)
            ->where('is_published', true)
            ->count();

        // ── Quiz & Game stats ────────────────────────────────────────
        // (now covered by the cached $stats block above)

        // ── CBT Exam/Assessment/Assignment stats ─────────────────────
        // (now covered by the cached $stats block above)

        // ── AI credits remaining (level allocation or school pool) ───
        $creditService = app(AiCreditService::class);
        $levelId = $creditService->resolveTeacherLevelId($teacher);
        $aiCreditsRemaining = $creditService->getAvailableCredits($school, $levelId);
        $aiCreditsLabel = __('School pool');
        if ($levelId) {
            $aiCreditsLabel = $assignedClasses->firstWhere('level_id', $levelId)?->level?->name
                ?? $teacher->level?->name
                ?? __('Your level');
        }

        // ── Upcoming due dates (assignments with future due_date) ────
        $upcomingDeadlines = collect();
        if ($currentSession && $currentTerm && $classIds->isNotEmpty()) {
            $upcomingDeadlines = Assignment::whereIn('class_id', $classIds)
                ->where('session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)
                ->where('status', 'approved')
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now())
                ->with('class:id,name')
                ->orderBy('due_date')
                ->take(5)
                ->get(['id', 'title', 'week_number', 'class_id', 'due_date']);
        }

        return view('teacher.dashboard', compact(
            'teacher', 'currentSession', 'currentTerm',
            'allSessions', 'sessionTerms',
            'assignedClasses', 'totalStudents',
            'totalResults', 'totalAssignments', 'totalNotices', 'pendingCount',
            'rejectedSubmissions', 'recentSubmissions',
            'resultsProgress', 'assignmentsCoverage', 'weeksPerTerm',
            'publishedQuizzes', 'publishedGames', 'quizAttempts', 'avgQuizScore',
            'publishedCbtExams', 'publishedAssessments', 'publishedCbtAssignments',
            'cbtAttempts', 'avgCbtScore',
            'aiCreditsRemaining', 'aiCreditsLabel',
            'upcomingDeadlines',
        ));
    }
}
