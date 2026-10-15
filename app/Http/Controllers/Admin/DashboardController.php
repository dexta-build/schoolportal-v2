<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Notice;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\TeacherAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $school = app('current.school');

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

        // ── Core Counts (cached for 5 minutes) ──────────────────────
        $cacheKey = "school:{$school->id}:dashboard:stats";
        $stats = Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return [
                'totalStudents' => User::where('role', 'student')->where('is_active', true)->count(),
                'maleStudents' => User::where('role', 'student')->where('is_active', true)->where('gender', 'male')->count(),
                'femaleStudents' => User::where('role', 'student')->where('is_active', true)->where('gender', 'female')->count(),
                'totalTeachers' => User::where('role', 'teacher')->where('is_active', true)->count(),
                'totalParents' => User::where('role', 'parent')->where('is_active', true)->count(),
                'totalClasses' => SchoolClass::where('is_active', true)->count(),
            ];
        });

        $totalStudents = $stats['totalStudents'];
        $maleStudents = $stats['maleStudents'];
        $femaleStudents = $stats['femaleStudents'];
        $totalTeachers = $stats['totalTeachers'];
        $totalParents = $stats['totalParents'];
        $totalClasses = $stats['totalClasses'];

        // ── AI Credits Balance ───────────────────────────────────────
        $aiFreeCredits = $school->ai_free_credits;
        $aiPurchasedCredits = $school->ai_purchased_credits;
        $aiTotalCredits = $school->aiCreditsBalance();
        $aiFreeResetDate = $school->ai_free_credits_reset_at;

        // ── Quick Stats for Current Term ─────────────────────────────
        $termResultsCount = 0;
        $termAssignmentsCount = 0;
        $termNoticesCount = 0;
        if ($currentSession && $currentTerm) {
            $termResultsCount = Result::where('session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)->count();
            $termAssignmentsCount = Assignment::where('session_id', $currentSession->id)
                ->where('term_id', $currentTerm->id)->count();
            $termNoticesCount = Notice::where('is_published', true)
                ->where('created_at', '>=', $currentTerm->start_date ?? now()->startOfYear())
                ->count();
        }

        // ── Unassigned Teachers Alert ────────────────────────────────
        $unassignedTeachers = User::where('role', 'teacher')
            ->where('is_active', true)
            ->whereDoesntHave('assignedClasses')
            ->get(['id', 'name']);
        // ── CBT Counts (published across school) ─────────────────────
        $cbtExamsCount = Exam::published()->forCategory('exam')->count();
        $cbtAssessmentsCount = Exam::published()->forCategory('assessment')->count();
        $cbtAssignmentsCount = Exam::published()->forCategory('assignment')->count();
        // ── Pending Approvals ────────────────────────────────────────
        $pendingApprovals = TeacherAction::with(['teacher:id,name,avatar_url', 'reviewer:id,name'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $pendingCount = TeacherAction::where('status', 'pending')->count();

        // ── Recent Activity ──────────────────────────────────────────
        $recentActivity = AuditLog::with(['user' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest()
            ->take(10)
            ->get();

        // ── Dashboard Widget Preferences ─────────────────────────────
        $widgetPreferences = auth()->user()->getDashboardWidgets();
        $widgetOrder = [];
        foreach ($widgetPreferences as $i => $pref) {
            $widgetOrder[$pref['id']] = [
                'order' => $i,
                'visible' => $pref['visible'],
            ];
        }

        return view('admin.dashboard', compact(
            'school', 'currentSession', 'currentTerm',
            'allSessions', 'sessionTerms',
            'totalStudents', 'maleStudents', 'femaleStudents',
            'totalTeachers', 'totalParents', 'totalClasses',
            'aiFreeCredits', 'aiPurchasedCredits', 'aiTotalCredits', 'aiFreeResetDate',
            'termResultsCount', 'termAssignmentsCount', 'termNoticesCount',
            'cbtExamsCount', 'cbtAssessmentsCount', 'cbtAssignmentsCount',
            'unassignedTeachers',
            'pendingApprovals', 'pendingCount',
            'recentActivity',
            'widgetOrder',
        ));
    }
}
