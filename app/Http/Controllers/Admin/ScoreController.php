<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ReportCardConfig;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\GradingScaleResolver;
use App\Services\NotificationService;
use App\Services\ScoreAggregationService;
use App\Services\ScoreCorrectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function __construct(
        private ScoreAggregationService $scoreService,
    ) {}

    /**
     * Score overview — select class and term to view score grid.
     */
    public function index(Request $request)
    {
        $school = auth()->user()->school;
        $classes = SchoolClass::where('is_active', true)->orderBy('sort_order')->get();
        $levels = SchoolLevel::with(['classes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();

        $currentSession = AcademicSession::where('is_current', true)->first();
        $currentTerm = Term::where('is_current', true)->first();

        $selectedClassId = $request->input('class_id');
        $selectedTermId = $request->input('term_id', $currentTerm?->id);
        $selectedSubjectId = $request->input('subject_id');

        $grid = null;
        $selectedClass = null;
        $selectedTerm = null;
        $subjects = collect();
        $components = collect();

        if ($selectedClassId && $selectedTermId) {
            $selectedClass = SchoolClass::findOrFail($selectedClassId);
            $selectedTerm = Term::with('session')->findOrFail($selectedTermId);
            $subjects = $selectedClass->subjects()->orderBy('name')->get();
            $components = ScoreComponent::where('school_id', $school->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $grid = $this->scoreService->getClassScoreGrid((int) $selectedClassId, (int) $selectedTermId, $school->id);
        }

        $config = $school->reportCardConfig;
        $enabledReportTypes = $config?->enabled_report_types ?? ['full_term'];

        return view('admin.scores.index', compact(
            'classes',
            'levels',
            'sessions',
            'currentSession',
            'currentTerm',
            'selectedClassId',
            'selectedTermId',
            'selectedSubjectId',
            'selectedClass',
            'selectedTerm',
            'subjects',
            'components',
            'grid',
            'enabledReportTypes',
        ));
    }

    /**
     * Save manually entered/edited scores.
     */
    public function saveScores(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
            'scores' => 'required|array',
        ]);

        $school = auth()->user()->school;
        $class = SchoolClass::findOrFail($request->class_id);
        $term = Term::with('session')->findOrFail($request->term_id);
        $eligibleStudentIds = StudentProfile::where('class_id', $class->id)
            ->enrolledByTerm($term)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id);

        if (collect(array_keys($request->scores))->map(fn ($id) => (string) $id)->diff($eligibleStudentIds)->isNotEmpty()) {
            return redirect()->back()->withErrors([
                'scores' => __('Scores cannot be entered for a student before their enrollment term.'),
            ]);
        }

        $updated = 0;
        $userId = auth()->id();

        DB::transaction(function () use ($request, $school, $class, $term, &$updated, $userId) {
            // scores[student_id][subject_id][component_id] = score_value
            foreach ($request->scores as $studentId => $subjects) {
                foreach ($subjects as $subjectId => $components) {
                    foreach ($components as $componentId => $score) {
                        if ($score === null || $score === '') {
                            continue;
                        }

                        $component = ScoreComponent::findOrFail($componentId);
                        $score = min((float) $score, $component->max_score);

                        $existing = StudentSubjectScore::where('student_id', $studentId)
                            ->where('subject_id', $subjectId)
                            ->where('term_id', $term->id)
                            ->where('score_component_id', $componentId)
                            ->first();

                        if ($existing && $existing->is_locked) {
                            continue;
                        }

                        StudentSubjectScore::updateOrCreate(
                            [
                                'student_id' => $studentId,
                                'subject_id' => $subjectId,
                                'term_id' => $term->id,
                                'score_component_id' => $componentId,
                            ],
                            [
                                'school_id' => $school->id,
                                'class_id' => $class->id,
                                'session_id' => $term->session_id,
                                'score' => $score,
                                'max_score' => $component->max_score,
                                'source_type' => 'manual',
                                'entered_by' => $userId,
                            ]
                        );

                        $updated++;
                    }
                }
            }
        });

        if ($updated > 0) {
            app(ScoreCorrectionService::class)->markReportsStale($class->id, $term->id);
        }

        return redirect()->back()->with('success', "{$updated} scores saved successfully.");
    }

    /**
     * Lock scores for a class/term (prevent further changes).
     */
    public function lockScores(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
        ]);

        $count = app(ScoreCorrectionService::class)->lockClass((int) $request->class_id, (int) $request->term_id, auth()->user());

        return redirect()->back()->with('success', "{$count} scores locked for this class/term.");
    }

    /**
     * Generate/refresh term reports for all students in a class.
     */
    public function generateReports(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'report_type' => 'required|in:midterm,full_term,session',
            'term_id' => 'required_unless:report_type,session|nullable|exists:terms,id',
            'session_id' => 'required_if:report_type,session|nullable|exists:academic_sessions,id',
            'generation_scope' => 'nullable|in:bulk,single',
            'student_id' => 'required_if:generation_scope,single|nullable|exists:users,id',
        ]);

        $school = auth()->user()->school;
        $reportType = $request->input('report_type');
        $scope = $request->input('generation_scope', 'bulk');

        // Check if the report type is enabled in school config
        $config = ReportCardConfig::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->first();

        $enabledTypes = $config?->enabled_report_types ?? ['full_term', 'midterm', 'session'];

        if (! in_array($reportType, $enabledTypes)) {
            return redirect()->back()->withErrors([
                'report_type' => 'This report type is not enabled for your school.',
            ]);
        }

        if ($reportType === 'session') {
            $session = AcademicSession::findOrFail($request->session_id);

            if ($scope === 'single' && $request->student_id) {
                $this->scoreService->generateSessionReport(
                    (int) $request->student_id,
                    (int) $request->class_id,
                    $session->id,
                    $school->id,
                );

                $student = User::findOrFail($request->student_id);

                return redirect()->route('admin.scores.reports', [
                    'class_id' => $request->class_id,
                    'report_type' => 'session',
                ])->with('success', "Session report card generated for {$student->name}.");
            }

            $count = $this->scoreService->generateClassSessionReports(
                (int) $request->class_id,
                $session->id,
                $school->id,
            );

            return redirect()->route('admin.scores.reports', [
                'class_id' => $request->class_id,
                'report_type' => 'session',
            ])->with('success', "Session report cards generated for {$count} students.");
        }

        $term = Term::with('session')->findOrFail($request->term_id);

        if ($scope === 'single' && $request->student_id) {
            $this->scoreService->generateTermReport(
                (int) $request->student_id,
                (int) $request->class_id,
                $term->session_id,
                $term->id,
                $school->id,
                $reportType,
            );

            $student = User::findOrFail($request->student_id);

            return redirect()->route('admin.scores.reports', [
                'class_id' => $request->class_id,
                'term_id' => $request->term_id,
                'report_type' => $reportType,
            ])->with('success', "Report card generated for {$student->name}.");
        }

        $count = $this->scoreService->generateClassReports(
            (int) $request->class_id,
            $term->session_id,
            $term->id,
            $school->id,
            $reportType,
        );

        return redirect()->route('admin.scores.reports', [
            'class_id' => $request->class_id,
            'term_id' => $request->term_id,
            'report_type' => $reportType,
        ])->with('success', "Report cards generated for {$count} students.");
    }

    /**
     * Get students for a class (JSON endpoint for generate modal).
     */
    public function classStudents(Request $request, SchoolClass $class)
    {
        $students = $class->students();

        if ($request->filled('term_id')) {
            $term = Term::with('session')->findOrFail($request->integer('term_id'));
            $students->enrolledByTerm($term);
        } elseif ($request->filled('session_id')) {
            $session = AcademicSession::findOrFail($request->integer('session_id'));
            $students->enrolledBySession($session);
        }

        $students = $students
            ->with('user')
            ->get()
            ->sortBy(fn (StudentProfile $sp) => $sp->user?->name)
            ->values()
            ->map(fn (StudentProfile $sp) => [
                'id' => $sp->user_id,
                'name' => $sp->user?->name ?? 'Unknown',
                'admission_number' => $sp->admission_number,
            ]);

        return response()->json($students);
    }

    /**
     * View all term reports for a class.
     */
    public function reports(Request $request)
    {
        $school = auth()->user()->school;
        $classes = SchoolClass::where('is_active', true)->orderBy('sort_order')->get();
        $levels = SchoolLevel::with(['classes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $sessions = AcademicSession::with('terms')->orderByDesc('start_date')->get();
        $currentTerm = Term::where('is_current', true)->first();

        $selectedClassId = $request->input('class_id');
        $selectedTermId = $request->input('term_id', $currentTerm?->id);
        $selectedReportType = $request->input('report_type');

        $reports = collect();
        $selectedClass = null;
        $selectedTerm = null;

        if ($selectedClassId) {
            $selectedClass = SchoolClass::findOrFail($selectedClassId);

            $query = StudentTermReport::where('class_id', $selectedClassId)
                ->with(['student.studentProfile', 'teacher']);

            if ($selectedReportType === 'session') {
                $query->where('report_type', 'session');
            } else {
                if ($selectedTermId) {
                    $selectedTerm = Term::with('session')->findOrFail($selectedTermId);
                    $query->where('term_id', $selectedTermId);
                }
                if ($selectedReportType) {
                    $query->where('report_type', $selectedReportType);
                }
            }

            $reports = $query->orderBy('position')->get();
        }

        $config = $school->reportCardConfig;
        $enabledReportTypes = $config?->enabled_report_types ?? ['full_term'];

        return view('admin.scores.reports', compact(
            'classes',
            'levels',
            'sessions',
            'currentTerm',
            'selectedClassId',
            'selectedTermId',
            'selectedReportType',
            'selectedClass',
            'selectedTerm',
            'reports',
            'enabledReportTypes',
            'config',
        ));
    }

    /**
     * View single student term report (preview).
     */
    public function showReport(StudentTermReport $report)
    {
        $report->load(['student', 'class', 'session', 'term', 'teacher', 'approvedByUser']);
        $school = auth()->user()->school;

        $config = $school->reportCardConfig;
        $gradingScale = app(GradingScaleResolver::class)
            ->resolveForLevel($report->class?->level_id, $school->id)
            ?->load('items');

        return view('admin.scores.show-report', compact('report', 'school', 'config', 'gradingScale'));
    }

    /**
     * Approve a pending report (add principal comment).
     */
    public function approveReport(Request $request, StudentTermReport $report)
    {
        $request->validate([
            'principal_comment' => 'nullable|string|max:1000',
        ]);

        if ($report->status !== 'pending_approval') {
            return redirect()->back()->with('error', 'Report is not pending approval.');
        }

        $report->update([
            'principal_comment' => $request->principal_comment,
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Report approved successfully.');
    }

    /**
     * Bulk approve all pending reports for a class/term.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
            'principal_comment' => 'nullable|string|max:1000',
        ]);

        $count = StudentTermReport::where('class_id', $request->class_id)
            ->where('term_id', $request->term_id)
            ->where('status', 'pending_approval')
            ->update([
                'principal_comment' => $request->principal_comment,
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

        return redirect()->back()->with('success', "{$count} reports approved.");
    }

    /**
     * Publish approved reports (visible to students/parents).
     */
    public function publishReports(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
        ]);

        $reports = StudentTermReport::where('class_id', $request->class_id)
            ->where('term_id', $request->term_id)
            ->where('status', 'approved')
            ->get();

        $count = $reports->count();

        if ($count > 0) {
            // Notify students and parents
            $notificationService = app(NotificationService::class);
            foreach ($reports as $report) {
                $this->scoreService->finalizeReportGradeSnapshot($report);
                $report->update([
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                $notificationService->notifyReportCardPublished($report);
            }
        }

        return redirect()->back()->with('success', "{$count} report cards published and now visible to students and parents.");
    }

    /**
     * Show form to enter report data (attendance, psychomotor, affective, comment) for a single student.
     */
    public function editReportData(StudentTermReport $report)
    {
        if (in_array($report->status, ['approved', 'published'])) {
            return redirect()->back()->with('error', 'Cannot edit report data after approval.');
        }

        $report->load(['student', 'class', 'session', 'term']);
        $config = auth()->user()->school->reportCardConfig;

        return view('admin.scores.edit-report-data', compact('report', 'config'));
    }

    /**
     * Save report data (attendance, psychomotor, affective, comment) for a single student.
     */
    public function saveReportData(Request $request, StudentTermReport $report)
    {
        if (in_array($report->status, ['approved', 'published'])) {
            return redirect()->back()->with('error', 'Cannot edit report data after approval.');
        }

        $config = auth()->user()->school->reportCardConfig;
        $maxRating = $this->getMaxRating($config);

        $request->validate([
            'attendance_present' => 'nullable|integer|min:0',
            'attendance_absent' => 'nullable|integer|min:0',
            'attendance_total' => 'nullable|integer|min:0',
            'psychomotor' => 'nullable|array',
            'psychomotor.*' => "nullable|integer|min:1|max:{$maxRating}",
            'affective' => 'nullable|array',
            'affective.*' => "nullable|integer|min:1|max:{$maxRating}",
            'teacher_comment' => 'nullable|string|max:1000',
        ]);

        $report->update([
            'attendance_present' => $request->input('attendance_present'),
            'attendance_absent' => $request->input('attendance_absent'),
            'attendance_total' => $request->input('attendance_total'),
            'psychomotor_ratings' => $request->input('psychomotor'),
            'affective_ratings' => $request->input('affective'),
            'teacher_comment' => $request->input('teacher_comment'),
        ]);

        return redirect()->back()->with('success', 'Report data saved successfully.');
    }

    /**
     * Show bulk form to enter report data for all students in a class.
     */
    public function bulkEditReportData(Request $request)
    {
        $classes = SchoolClass::where('is_active', true)->orderBy('sort_order')->get();
        $sessions = AcademicSession::with('terms')->orderByDesc('start_date')->get();
        $currentTerm = Term::where('is_current', true)->first();

        $selectedClassId = $request->input('class_id');
        $selectedTermId = $request->input('term_id', $currentTerm?->id);

        $reports = collect();
        $selectedClass = null;
        $selectedTerm = null;
        $config = null;

        if ($selectedClassId && $selectedTermId) {
            $selectedClass = SchoolClass::findOrFail($selectedClassId);
            $selectedTerm = Term::with('session')->findOrFail($selectedTermId);
            $config = auth()->user()->school->reportCardConfig;

            $reports = StudentTermReport::where('class_id', $selectedClassId)
                ->where('term_id', $selectedTermId)
                ->whereNotIn('status', ['approved', 'published'])
                ->with('student')
                ->orderBy('position')
                ->get();
        }

        return view('admin.scores.bulk-edit-report-data', compact(
            'classes',
            'sessions',
            'currentTerm',
            'selectedClassId',
            'selectedTermId',
            'selectedClass',
            'selectedTerm',
            'reports',
            'config',
        ));
    }

    /**
     * Save bulk report data for all students in a class.
     */
    public function bulkSaveReportData(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
            'reports' => 'required|array',
        ]);

        $config = auth()->user()->school->reportCardConfig;
        $maxRating = $this->getMaxRating($config);

        $count = 0;
        foreach ($request->input('reports') as $reportId => $data) {
            $report = StudentTermReport::where('class_id', $request->class_id)
                ->where('term_id', $request->term_id)
                ->whereNotIn('status', ['approved', 'published'])
                ->find($reportId);

            if (! $report) {
                continue;
            }

            $psychomotor = $data['psychomotor'] ?? null;
            $affective = $data['affective'] ?? null;
            if ($psychomotor) {
                $psychomotor = array_map(fn ($v) => $v !== null && $v !== '' ? min((int) $v, $maxRating) : null, $psychomotor);
            }
            if ($affective) {
                $affective = array_map(fn ($v) => $v !== null && $v !== '' ? min((int) $v, $maxRating) : null, $affective);
            }

            $report->update([
                'attendance_present' => isset($data['attendance_present']) && $data['attendance_present'] !== '' ? (int) $data['attendance_present'] : null,
                'attendance_absent' => isset($data['attendance_absent']) && $data['attendance_absent'] !== '' ? (int) $data['attendance_absent'] : null,
                'attendance_total' => isset($data['attendance_total']) && $data['attendance_total'] !== '' ? (int) $data['attendance_total'] : null,
                'psychomotor_ratings' => $psychomotor,
                'affective_ratings' => $affective,
                'teacher_comment' => $data['teacher_comment'] ?? null,
            ]);
            $count++;
        }

        return redirect()->back()->with('success', "Report data saved for {$count} students.");
    }

    /**
     * Download a single report card as PDF.
     */
    public function downloadReport(StudentTermReport $report)
    {
        $report->load(['student', 'class', 'session', 'term', 'teacher', 'approvedByUser']);
        $school = auth()->user()->school;

        $config = $school->reportCardConfig;
        $gradingScale = app(GradingScaleResolver::class)
            ->resolveForLevel($report->class?->level_id, $school->id)
            ?->load('items');
        $this->scoreService->finalizeReportGradeSnapshot($report);
        $report->refresh()->load(['student', 'class', 'session', 'term', 'teacher', 'approvedByUser']);

        $pdf = Pdf::loadView('admin.scores.report-pdf', compact(
            'report', 'school', 'config', 'gradingScale'
        ));

        $studentName = str_replace(' ', '_', $report->student->name ?? 'Student');
        $reportType = $report->report_type ?? 'full_term';

        $filename = match ($reportType) {
            'midterm' => "MidTerm_Report_{$studentName}_{$report->term?->name}.pdf",
            'session' => "Session_Report_{$studentName}_{$report->session?->name}.pdf",
            default => "Report_Card_{$studentName}_{$report->term?->name}.pdf",
        };
        $filename = str_replace(['/', '\\', ' '], ['_', '_', '_'], $filename);

        return $pdf->download($filename);
    }

    /**
     * Export scores as CSV for a class/term.
     */
    public function exportCsv(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'required|exists:terms,id',
        ]);

        $school = auth()->user()->school;
        $class = SchoolClass::findOrFail($request->class_id);
        $term = Term::with('session')->findOrFail($request->term_id);
        $grid = $this->scoreService->getClassScoreGrid((int) $request->class_id, (int) $request->term_id, $school->id);

        $components = $grid['components'];
        $subjects = $grid['subjects'];

        $headers = ['Student Name', 'Admission No'];
        foreach ($subjects as $subject) {
            foreach ($components as $comp) {
                $headers[] = "{$subject->name} - {$comp->short_name} ({$comp->max_score})";
            }
            $headers[] = "{$subject->name} - Total (%)";
            $headers[] = "{$subject->name} - Grade";
        }

        $rows = [];
        foreach ($grid['students'] as $student) {
            $row = [$student['student_name'], $student['admission_number'] ?? ''];
            foreach ($student['subjects'] as $subjectData) {
                foreach ($components as $comp) {
                    $compScore = $subjectData['components'][$comp->id] ?? null;
                    $row[] = $compScore['score'] ?? '';
                }
                $row[] = $subjectData['weighted_total'];
                $row[] = $subjectData['grade'] ?? '';
            }
            $rows[] = $row;
        }

        $filename = "Scores_{$class->name}_{$term->name}.csv";

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get the maximum rating value from the trait rating scale.
     */
    private function getMaxRating(?ReportCardConfig $config): int
    {
        if (! $config || ! $config->trait_rating_scale) {
            return 5;
        }

        return (int) collect($config->trait_rating_scale)->max('value') ?: 5;
    }

    /**
     * Export report cards for a class as CSV.
     */
    public function exportReportsCsv(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'nullable|exists:terms,id',
            'report_type' => 'nullable|in:midterm,full_term,session',
        ]);

        $school = auth()->user()->school;
        $class = SchoolClass::findOrFail($request->class_id);

        $query = StudentTermReport::where('class_id', $request->class_id)
            ->with(['student.studentProfile', 'session', 'term']);

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->term_id);
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        $reports = $query->orderBy('position')->get();

        if ($reports->isEmpty()) {
            return redirect()->back()->with('error', 'No reports found for this selection.');
        }

        $first = $reports->first();
        $isSession = ($first->report_type ?? 'full_term') === 'session';

        $headers = ['Position', 'Student Name', 'Admission No.'];

        if ($isSession && is_array($first->subject_scores_snapshot)) {
            // Collect term names from first report's snapshot
            $termNames = [];
            foreach ($first->subject_scores_snapshot as $snap) {
                foreach ($snap['term_scores'] ?? [] as $ts) {
                    $termNames[$ts['term_id']] = $ts['term_name'];
                }
            }
            ksort($termNames);

            // Build subject headers for session report
            foreach ($first->subject_scores_snapshot as $snap) {
                $subjectName = $snap['subject_name'] ?? 'Subject';
                foreach ($termNames as $termName) {
                    $headers[] = "{$subjectName} - {$termName}";
                }
                $headers[] = "{$subjectName} - Session Avg";
                $headers[] = "{$subjectName} - Grade";
                $headers[] = "{$subjectName} - Position";
            }
        } else {
            // Build subject headers for term reports (midterm/full_term)
            if (is_array($first->subject_scores_snapshot)) {
                foreach ($first->subject_scores_snapshot as $snap) {
                    $subjectName = $snap['subject_name'] ?? 'Subject';
                    foreach ($snap['components'] ?? [] as $comp) {
                        $headers[] = "{$subjectName} - {$comp['short_name']}";
                    }
                    $headers[] = "{$subjectName} - Total (%)";
                    $headers[] = "{$subjectName} - Grade";
                    $headers[] = "{$subjectName} - Position";
                }
            }
        }

        $headers[] = 'Average (%)';
        $headers[] = 'Teacher Comment';
        $headers[] = 'Principal Comment';
        $headers[] = 'Status';

        $rows = [];
        foreach ($reports as $report) {
            $row = [
                $report->position ?? '',
                $report->student->name ?? '',
                $report->student->studentProfile?->admission_number ?? '',
            ];

            $snapshot = $report->subject_scores_snapshot ?? [];

            if ($isSession) {
                foreach ($snapshot as $snap) {
                    $termScoresMap = collect($snap['term_scores'] ?? [])->keyBy('term_id');
                    foreach ($termNames as $termId => $termName) {
                        $row[] = isset($termScoresMap[$termId]) ? number_format($termScoresMap[$termId]['score'], 1) : '';
                    }
                    $row[] = isset($snap['session_total']) ? number_format($snap['session_total'], 1) : '';
                    $row[] = $snap['grade'] ?? '';
                    $row[] = $snap['position'] ?? '';
                }
            } else {
                foreach ($snapshot as $snap) {
                    foreach ($snap['components'] ?? [] as $comp) {
                        $row[] = $comp['score'] ?? '';
                    }
                    $row[] = isset($snap['weighted_total']) ? number_format($snap['weighted_total'], 1) : '';
                    $row[] = $snap['grade'] ?? '';
                    $row[] = $snap['position'] ?? '';
                }
            }

            $row[] = $report->average_weighted_score ? number_format((float) $report->average_weighted_score, 1) : '';
            $row[] = $report->teacher_comment ?? '';
            $row[] = $report->principal_comment ?? '';
            $row[] = $report->status;

            $rows[] = $row;
        }

        $typeSuffix = $request->input('report_type') ? '_'.ucfirst($request->input('report_type')) : '';
        $filename = "Reports_{$class->name}{$typeSuffix}.csv";
        $filename = str_replace(['/', '\\', ' '], ['_', '_', '_'], $filename);

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Download all report cards for a class as a combined PDF.
     */
    public function downloadAllReportsPdf(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'term_id' => 'nullable|exists:terms,id',
            'report_type' => 'nullable|in:midterm,full_term,session',
        ]);

        $school = auth()->user()->school;
        $class = SchoolClass::findOrFail($request->class_id);
        $config = $school->reportCardConfig;
        $gradingScale = app(GradingScaleResolver::class)
            ->resolveForLevel($class->level_id, $school->id)
            ?->load('items');

        $query = StudentTermReport::where('class_id', $request->class_id)
            ->with(['student.studentProfile', 'class', 'session', 'term']);

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->term_id);
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        $reports = $query->orderBy('position')->get();

        if ($reports->isEmpty()) {
            return redirect()->back()->with('error', 'No reports found for this selection.');
        }

        $html = '';
        foreach ($reports as $index => $report) {
            $this->scoreService->finalizeReportGradeSnapshot($report);
            $report->refresh()->load(['student.studentProfile', 'class', 'session', 'term']);

            $html .= view('admin.scores.report-pdf', compact(
                'report', 'school', 'config', 'gradingScale'
            ))->render();

            if ($index < $reports->count() - 1) {
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait');

        $typeSuffix = $request->input('report_type') ? '_'.ucfirst($request->input('report_type')) : '';
        $filename = "All_Reports_{$class->name}{$typeSuffix}.pdf";
        $filename = str_replace(['/', '\\', ' '], ['_', '_', '_'], $filename);

        return $pdf->download($filename);
    }
}
