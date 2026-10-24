<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GradingScale;
use App\Models\ReportCardConfig;
use App\Models\SchoolClass;
use App\Models\ScoreAmendment;
use App\Models\ScoreComponent;
use App\Models\StudentProfile;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\StudentTermReportRevision;
use App\Models\Term;
use Illuminate\Validation\ValidationException;

class ScoreAggregationService
{
    public function __construct(
        private readonly GradingScaleResolver $gradingScaleResolver,
    ) {}

    /**
     * Update a student's subject score from a graded CBT exam attempt.
     * Called automatically when an exam attempt is graded/submitted.
     */
    public function updateScoreFromExam(ExamAttempt $attempt): void
    {
        $exam = $attempt->exam;

        // Exam must be linked to a score component and subject
        if (! $exam->score_component_id || ! $exam->subject_id || ! $exam->term_id) {
            return;
        }

        $term = Term::withoutGlobalScopes()
            ->with(['session' => fn ($query) => $query->withoutGlobalScopes()])
            ->find($exam->term_id);
        if (! $term || ! StudentProfile::withoutGlobalScopes()
            ->where('user_id', $attempt->student_id)
            ->enrolledByTerm($term)
            ->exists()) {
            return;
        }

        $component = $exam->scoreComponent;
        if (! $component) {
            return;
        }

        // Normalize score to component max: (attempt_score / exam_max) * component_max
        $examMax = $exam->total_points ?: $exam->max_score;
        if ($examMax <= 0) {
            return;
        }

        $normalizedScore = round(($attempt->score / $examMax) * $component->max_score, 2);

        $existing = StudentSubjectScore::withoutGlobalScopes()
            ->where('student_id', $attempt->student_id)
            ->where('subject_id', $exam->subject_id)
            ->where('term_id', $exam->term_id)
            ->where('score_component_id', $exam->score_component_id)
            ->first();

        // Locked scores are final — a late or re-graded CBT result waits for an admin to approve it
        if ($existing?->is_locked) {
            app(ScoreCorrectionService::class)->recordCbtChange($existing, $normalizedScore, $attempt);

            return;
        }

        $changed = $existing === null || round((float) $existing->score, 2) !== $normalizedScore;

        StudentSubjectScore::withoutGlobalScopes()->updateOrCreate(
            [
                'student_id' => $attempt->student_id,
                'subject_id' => $exam->subject_id,
                'term_id' => $exam->term_id,
                'score_component_id' => $exam->score_component_id,
            ],
            [
                'school_id' => $exam->school_id,
                'class_id' => $exam->class_id,
                'session_id' => $exam->session_id,
                'score' => $normalizedScore,
                'max_score' => $component->max_score,
                'source_type' => 'cbt',
                'source_exam_id' => $exam->id,
                'source_attempt_id' => $attempt->id,
            ]
        );

        if ($changed) {
            app(ScoreCorrectionService::class)->markReportsStale((int) $exam->class_id, (int) $exam->term_id);
        }
    }

    /**
     * Compute weighted total for a student in a subject for a term.
     * Returns: ['weighted_total' => float, 'components' => [...]]
     */
    public function computeSubjectTotal(int $studentId, int $subjectId, int $termId): array
    {
        $scores = StudentSubjectScore::withoutGlobalScopes()
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->with(['scoreComponent' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        $components = [];
        $weightedTotal = 0.0;

        foreach ($scores as $score) {
            $comp = $score->scoreComponent;
            if (! $comp) {
                continue;
            }

            $weighted = $score->weightedScore();
            $weightedTotal += $weighted;

            $components[] = [
                'component_id' => $comp->id,
                'name' => $comp->name,
                'short_name' => $comp->short_name,
                'score' => (float) $score->score,
                'max_score' => $comp->max_score,
                'weight' => $comp->weight,
                'weighted' => $weighted,
            ];
        }

        return [
            'weighted_total' => round($weightedTotal, 2),
            'components' => $components,
        ];
    }

    /**
     * Compute weighted total with optional mid-term filtering and re-normalization.
     *
     * When $midtermOnly = true, only components with include_in_midterm = true are used,
     * and their weighted contributions are re-normalized so the result is out of 100.
     */
    public function computeSubjectTotalFiltered(int $studentId, int $subjectId, int $termId, bool $midtermOnly = false): array
    {
        if (! $midtermOnly) {
            return $this->computeSubjectTotal($studentId, $subjectId, $termId);
        }

        $scores = StudentSubjectScore::withoutGlobalScopes()
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->with(['scoreComponent' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        $components = [];
        $rawWeightedTotal = 0.0;
        $sumOfIncludedWeights = 0;

        // First pass: collect midterm components and their raw weighted scores
        foreach ($scores as $score) {
            $comp = $score->scoreComponent;
            if (! $comp || ! $comp->include_in_midterm) {
                continue;
            }

            $rawWeighted = ($score->score !== null && $comp->max_score > 0)
                ? round(($score->score / $comp->max_score) * $comp->weight, 2)
                : 0.0;

            $rawWeightedTotal += $rawWeighted;
            $sumOfIncludedWeights += $comp->weight;

            $components[] = [
                'component_id' => $comp->id,
                'name' => $comp->name,
                'short_name' => $comp->short_name,
                'score' => (float) $score->score,
                'max_score' => $comp->max_score,
                'weight' => $comp->weight,
                'weighted' => $rawWeighted,
            ];
        }

        // Re-normalize: scale to 100
        $reNormalizedTotal = ($sumOfIncludedWeights > 0)
            ? round($rawWeightedTotal * (100 / $sumOfIncludedWeights), 2)
            : 0.0;

        return [
            'weighted_total' => $reNormalizedTotal,
            'components' => $components,
            'raw_weighted_total' => round($rawWeightedTotal, 2),
            'sum_of_included_weights' => $sumOfIncludedWeights,
        ];
    }

    /**
     * Get grade letter and label from the level grading scale, falling back to the school default.
     */
    public function getGrade(int $schoolId, float $percentage, ?int $levelId = null): ?array
    {
        return $this->gradingScaleResolver->getGrade($schoolId, $percentage, $levelId);
    }

    /**
     * Compute class rankings for a subject in a term.
     * Returns: [student_id => position]
     */
    public function computeSubjectPositions(int $classId, int $subjectId, int $termId, bool $midtermOnly = false): array
    {
        $students = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->with(['scoreComponent' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->groupBy('student_id');

        $totals = [];
        foreach ($students as $studentId => $scores) {
            if ($midtermOnly) {
                $filtered = $scores->filter(fn ($s) => $s->scoreComponent?->include_in_midterm);
                $rawTotal = 0.0;
                $sumWeights = 0;
                foreach ($filtered as $score) {
                    $rawTotal += $score->weightedScore();
                    $sumWeights += $score->scoreComponent->weight;
                }
                $totals[$studentId] = $sumWeights > 0 ? round($rawTotal * (100 / $sumWeights), 2) : 0.0;
            } else {
                $total = 0.0;
                foreach ($scores as $score) {
                    $total += $score->weightedScore();
                }
                $totals[$studentId] = round($total, 2);
            }
        }

        // Sort descending, assign ranks (handling ties)
        arsort($totals);
        $positions = [];
        $rank = 0;
        $lastScore = null;
        $skipCount = 0;

        foreach ($totals as $studentId => $total) {
            $rank++;
            if ($lastScore !== null && $total < $lastScore) {
                $rank += $skipCount;
                $skipCount = 0;
            } elseif ($lastScore !== null && $total === $lastScore) {
                $rank--;
                $skipCount++;
            }
            $positions[$studentId] = $rank;
            $lastScore = $total;
        }

        return $positions;
    }

    /**
     * Compute overall class positions based on average weighted score across all subjects.
     * Returns: [student_id => ['position' => int, 'total' => float, 'average' => float, 'subjects_count' => int]]
     */
    public function computeOverallPositions(int $classId, int $termId, int $schoolId, bool $midtermOnly = false): array
    {
        // Get all students' scores grouped by student → subject
        $allScores = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->with(['scoreComponent' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->groupBy('student_id');

        $studentAverages = [];

        foreach ($allScores as $studentId => $scores) {
            $bySubject = $scores->groupBy('subject_id');
            $subjectTotals = [];

            foreach ($bySubject as $subjectId => $subjectScores) {
                if ($midtermOnly) {
                    $filtered = $subjectScores->filter(fn ($s) => $s->scoreComponent?->include_in_midterm);
                    $rawTotal = 0.0;
                    $sumWeights = 0;
                    foreach ($filtered as $score) {
                        $rawTotal += $score->weightedScore();
                        $sumWeights += $score->scoreComponent->weight;
                    }
                    $subjectTotals[] = $sumWeights > 0 ? round($rawTotal * (100 / $sumWeights), 2) : 0.0;
                } else {
                    $subTotal = 0.0;
                    foreach ($subjectScores as $score) {
                        $subTotal += $score->weightedScore();
                    }
                    $subjectTotals[] = round($subTotal, 2);
                }
            }

            $totalScore = array_sum($subjectTotals);
            $subjectsCount = count($subjectTotals);
            $average = $subjectsCount > 0 ? round($totalScore / $subjectsCount, 2) : 0.0;

            $studentAverages[$studentId] = [
                'total' => $totalScore,
                'average' => $average,
                'subjects_count' => $subjectsCount,
            ];
        }

        // Sort by average descending
        uasort($studentAverages, fn ($a, $b) => $b['average'] <=> $a['average']);

        // Assign positions (with ties)
        $rank = 0;
        $lastAvg = null;
        $skipCount = 0;

        foreach ($studentAverages as $studentId => &$data) {
            $rank++;
            if ($lastAvg !== null && $data['average'] < $lastAvg) {
                $rank += $skipCount;
                $skipCount = 0;
            } elseif ($lastAvg !== null && $data['average'] === $lastAvg) {
                $rank--;
                $skipCount++;
            }
            $data['position'] = $rank;
            $lastAvg = $data['average'];
        }
        unset($data);

        return $studentAverages;
    }

    /**
     * Build full subject scores snapshot for a student's term report.
     * Returns array ready for subject_scores_snapshot JSON.
     *
     * When $midtermOnly = true, only mid-term components are included with re-normalization.
     */
    public function buildSubjectScoresSnapshot(int $studentId, int $classId, int $termId, int $schoolId, bool $midtermOnly = false): array
    {
        $levelId = $this->levelIdForClass($classId);
        $scores = StudentSubjectScore::withoutGlobalScopes()
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->where('school_id', $schoolId)
            ->with([
                'scoreComponent' => fn ($q) => $q->withoutGlobalScopes(),
                'subject' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->get()
            ->groupBy('subject_id');

        $subjectPositions = [];
        $classAverages = [];
        $snapshot = [];

        foreach ($scores as $subjectId => $subjectScores) {
            $subject = $subjectScores->first()->subject;

            // Compute positions and class averages lazily
            if (! isset($subjectPositions[$subjectId])) {
                $subjectPositions[$subjectId] = $this->computeSubjectPositions($classId, $subjectId, $termId, $midtermOnly);
                $classAverages[$subjectId] = $this->computeClassAverage($classId, $subjectId, $termId, $midtermOnly);
            }

            $components = [];
            $rawWeightedTotal = 0.0;
            $sumOfIncludedWeights = 0;

            foreach ($subjectScores as $score) {
                $comp = $score->scoreComponent;
                if (! $comp) {
                    continue;
                }

                if ($midtermOnly && ! $comp->include_in_midterm) {
                    continue;
                }

                $weighted = $score->weightedScore();
                $rawWeightedTotal += $weighted;
                $sumOfIncludedWeights += $comp->weight;

                $components[] = [
                    'component_id' => $comp->id,
                    'name' => $comp->name,
                    'short_name' => $comp->short_name,
                    'score' => (float) $score->score,
                    'max_score' => $comp->max_score,
                    'weight' => $comp->weight,
                ];
            }

            // Re-normalize if midterm, or use raw total for full-term
            $weightedTotal = ($midtermOnly && $sumOfIncludedWeights > 0)
                ? round($rawWeightedTotal * (100 / $sumOfIncludedWeights), 2)
                : round($rawWeightedTotal, 2);

            $grade = $this->getGrade($schoolId, $weightedTotal, $levelId);

            $snapshot[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subject->name,
                'components' => $components,
                'weighted_total' => $weightedTotal,
                'grade' => $grade['grade'] ?? null,
                'grade_label' => $grade['label'] ?? null,
                'position' => $subjectPositions[$subjectId][$studentId] ?? null,
                'class_average' => $classAverages[$subjectId] ?? null,
            ];
        }

        // Sort by subject name
        usort($snapshot, fn ($a, $b) => strcmp($a['subject_name'], $b['subject_name']));

        return $snapshot;
    }

    /**
     * Compute class average weighted score for a subject.
     */
    public function computeClassAverage(int $classId, int $subjectId, int $termId, bool $midtermOnly = false): float
    {
        $studentScores = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->with(['scoreComponent' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->groupBy('student_id');

        if ($studentScores->isEmpty()) {
            return 0.0;
        }

        $totals = [];
        foreach ($studentScores as $studentId => $scores) {
            if ($midtermOnly) {
                $filtered = $scores->filter(fn ($s) => $s->scoreComponent?->include_in_midterm);
                $rawTotal = 0.0;
                $sumWeights = 0;
                foreach ($filtered as $score) {
                    $rawTotal += $score->weightedScore();
                    $sumWeights += $score->scoreComponent->weight;
                }
                $totals[] = $sumWeights > 0 ? round($rawTotal * (100 / $sumWeights), 2) : 0.0;
            } else {
                $total = 0.0;
                foreach ($scores as $score) {
                    $total += $score->weightedScore();
                }
                $totals[] = round($total, 2);
            }
        }

        return round(array_sum($totals) / count($totals), 2);
    }

    /**
     * Finalize and generate/update a student's term report.
     *
     * @param  string  $reportType  'midterm' or 'full_term'
     */
    public function generateTermReport(int $studentId, int $classId, int $sessionId, int $termId, int $schoolId, string $reportType = 'full_term'): StudentTermReport
    {
        $term = Term::withoutGlobalScopes()
            ->with(['session' => fn ($query) => $query->withoutGlobalScopes()])
            ->findOrFail($termId);
        $this->ensureStudentEnrolledByTerm($studentId, $classId, $term);

        $midtermOnly = $reportType === 'midterm';

        $snapshot = $this->buildSubjectScoresSnapshot($studentId, $classId, $termId, $schoolId, $midtermOnly);
        $overallPositions = $this->computeOverallPositions($classId, $termId, $schoolId, $midtermOnly);
        $levelId = $this->levelIdForClass($classId);

        $studentData = $overallPositions[$studentId] ?? [
            'total' => 0,
            'average' => 0,
            'subjects_count' => 0,
            'position' => null,
        ];
        $gradeSnapshot = $this->buildReportGradeSnapshot($schoolId, $levelId, (float) $studentData['average']);

        return $this->saveReport(
            [
                'student_id' => $studentId,
                'session_id' => $sessionId,
                'term_id' => $termId,
                'report_type' => $reportType,
            ],
            [
                'school_id' => $schoolId,
                'class_id' => $classId,
                'subject_scores_snapshot' => $snapshot,
                'total_weighted_score' => $studentData['total'],
                'average_weighted_score' => $studentData['average'],
                'grading_scale_id' => $gradeSnapshot['grading_scale_id'],
                'grading_scale_snapshot' => $gradeSnapshot['grading_scale_snapshot'],
                'overall_grade' => $gradeSnapshot['overall_grade'],
                'overall_grade_label' => $gradeSnapshot['overall_grade_label'],
                'subjects_count' => $studentData['subjects_count'],
                'position' => $studentData['position'],
                'out_of' => count($overallPositions),
            ]
        );
    }

    /**
     * Create a report, or refresh an existing one without losing its workflow state.
     *
     * Regenerating never moves a report back to draft — an approved or published report
     * stays approved/published, with teacher comments and ratings untouched. When its
     * content actually changes (e.g. after a score correction) the previous version is kept
     * as a revision, and students/parents are told if it was already published.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function saveReport(array $keys, array $values): StudentTermReport
    {
        $existing = StudentTermReport::withoutGlobalScopes()->where($keys)->first();

        if (! $existing) {
            return StudentTermReport::withoutGlobalScopes()->create($keys + $values + [
                'status' => 'draft',
                'needs_regeneration' => false,
            ]);
        }

        $changed = $this->reportContentChanged($existing, $values);
        $published = $existing->isPublished();

        if ($changed && ($published || $existing->isApproved())) {
            StudentTermReportRevision::withoutGlobalScopes()->create([
                'school_id' => $existing->school_id,
                'student_term_report_id' => $existing->id,
                'revision_number' => $existing->revision_count + 1,
                'status_at_revision' => $existing->status,
                'subject_scores_snapshot' => $existing->subject_scores_snapshot,
                'total_weighted_score' => $existing->total_weighted_score,
                'average_weighted_score' => $existing->average_weighted_score,
                'overall_grade' => $existing->overall_grade,
                'position' => $existing->position,
                'out_of' => $existing->out_of,
                'revised_by' => auth()->id(),
            ]);

            $values['revision_count'] = $existing->revision_count + 1;
            $values['revised_at'] = now();

            // Published reports keep a frozen grade snapshot — re-freeze it for the corrected content
            if ($existing->isFinalized()) {
                $values['finalized_at'] = now();
            }
        }

        $existing->update($values + ['needs_regeneration' => false]);

        if ($changed && $published) {
            try {
                app(NotificationService::class)->notifyReportCardRevised($existing);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function reportContentChanged(StudentTermReport $report, array $values): bool
    {
        return $this->normaliseSnapshot($report->subject_scores_snapshot) !== $this->normaliseSnapshot($values['subject_scores_snapshot'] ?? null)
            || round((float) $report->average_weighted_score, 2) !== round((float) ($values['average_weighted_score'] ?? 0), 2)
            || round((float) $report->total_weighted_score, 2) !== round((float) ($values['total_weighted_score'] ?? 0), 2)
            || (array_key_exists('position', $values) && $report->position !== $values['position']);
    }

    /**
     * Comparable form of a snapshot: MySQL reorders JSON object keys and may return 85 for 85.0,
     * so sort keys and round numbers before comparing.
     */
    private function normaliseSnapshot(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normaliseSnapshot($item), $value);
        }

        return is_int($value) || is_float($value) ? round((float) $value, 2) : $value;
    }

    /**
     * Generate term reports for all students in a class.
     *
     * @param  string  $reportType  'midterm' or 'full_term'
     */
    public function generateClassReports(int $classId, int $sessionId, int $termId, int $schoolId, string $reportType = 'full_term'): int
    {
        $class = SchoolClass::withoutGlobalScopes()->findOrFail($classId);
        $term = Term::withoutGlobalScopes()
            ->with(['session' => fn ($query) => $query->withoutGlobalScopes()])
            ->findOrFail($termId);
        $studentIds = $class->students()->enrolledByTerm($term)->pluck('user_id')->toArray();

        $count = 0;
        foreach ($studentIds as $studentId) {
            $this->generateTermReport($studentId, $classId, $sessionId, $termId, $schoolId, $reportType);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a session report for a student by aggregating full_term reports across all terms.
     */
    public function generateSessionReport(int $studentId, int $classId, int $sessionId, int $schoolId): StudentTermReport
    {
        $session = AcademicSession::withoutGlobalScopes()->findOrFail($sessionId);
        $this->ensureStudentEnrolledBySession($studentId, $classId, $session);
        $levelId = $this->levelIdForClass($classId);

        // Fetch all full_term reports for this student in this session
        $termReports = StudentTermReport::withoutGlobalScopes()
            ->where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->where('school_id', $schoolId)
            ->where('report_type', 'full_term')
            ->whereNotNull('term_id')
            ->with(['term' => fn ($q) => $q->withoutGlobalScopes()])
            ->get()
            ->keyBy('term_id');

        // Get session calculation config
        $config = ReportCardConfig::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->first();

        $method = $config->session_calculation_method ?? 'average_of_terms';
        $midtermWeight = (float) ($config->midterm_weight ?? 0);
        $fulltermWeight = (float) ($config->fullterm_weight ?? 0);

        // Collect all subjects across all term snapshots
        $subjectTermData = []; // [subject_id => ['name' => ..., 'terms' => [term_id => score]]]

        foreach ($termReports as $termId => $report) {
            $snapshot = $report->subject_scores_snapshot ?? [];
            foreach ($snapshot as $subjectData) {
                $subjectId = $subjectData['subject_id'];
                if (! isset($subjectTermData[$subjectId])) {
                    $subjectTermData[$subjectId] = [
                        'name' => $subjectData['subject_name'],
                        'terms' => [],
                    ];
                }
                $subjectTermData[$subjectId]['terms'][$termId] = [
                    'score' => (float) $subjectData['weighted_total'],
                    'term_name' => $report->term?->name ?? "Term {$termId}",
                ];
            }
        }

        // Compute session score per subject
        $sessionSnapshot = [];
        foreach ($subjectTermData as $subjectId => $data) {
            $termScores = [];
            foreach ($data['terms'] as $tId => $tData) {
                $termScores[] = [
                    'term_id' => $tId,
                    'term_name' => $tData['term_name'],
                    'score' => $tData['score'],
                ];
            }

            $scores = array_column($termScores, 'score');
            $sessionTotal = $this->computeSessionScore($scores, $method, $midtermWeight, $fulltermWeight);

            $grade = $this->getGrade($schoolId, $sessionTotal, $levelId);

            $sessionSnapshot[] = [
                'subject_id' => $subjectId,
                'subject_name' => $data['name'],
                'term_scores' => $termScores,
                'session_total' => $sessionTotal,
                'grade' => $grade['grade'] ?? null,
                'grade_label' => $grade['label'] ?? null,
                'position' => null, // filled in after all students computed
                'class_average' => null,
            ];
        }

        // Sort by subject name
        usort($sessionSnapshot, fn ($a, $b) => strcmp($a['subject_name'], $b['subject_name']));

        // Compute overall stats from session snapshot
        $sessionTotals = array_column($sessionSnapshot, 'session_total');
        $totalScore = round(array_sum($sessionTotals), 2);
        $subjectsCount = count($sessionTotals);
        $average = $subjectsCount > 0 ? round($totalScore / $subjectsCount, 2) : 0.0;
        $gradeSnapshot = $this->buildReportGradeSnapshot($schoolId, $levelId, (float) $average);

        return $this->saveReport(
            [
                'student_id' => $studentId,
                'session_id' => $sessionId,
                'term_id' => null,
                'report_type' => 'session',
            ],
            [
                'school_id' => $schoolId,
                'class_id' => $classId,
                'subject_scores_snapshot' => $sessionSnapshot,
                'total_weighted_score' => $totalScore,
                'average_weighted_score' => $average,
                'grading_scale_id' => $gradeSnapshot['grading_scale_id'],
                'grading_scale_snapshot' => $gradeSnapshot['grading_scale_snapshot'],
                'overall_grade' => $gradeSnapshot['overall_grade'],
                'overall_grade_label' => $gradeSnapshot['overall_grade_label'],
                'subjects_count' => $subjectsCount,
            ]
        );
    }

    /**
     * Generate session reports for all students in a class, then compute positions.
     */
    public function generateClassSessionReports(int $classId, int $sessionId, int $schoolId): int
    {
        $class = SchoolClass::withoutGlobalScopes()->findOrFail($classId);
        $session = AcademicSession::withoutGlobalScopes()->findOrFail($sessionId);
        $studentIds = $class->students()->enrolledBySession($session)->pluck('user_id')->toArray();

        // Generate session reports (without positions)
        $reports = [];
        foreach ($studentIds as $studentId) {
            $reports[$studentId] = $this->generateSessionReport($studentId, $classId, $sessionId, $schoolId);
        }

        // Compute positions based on average session score
        $averages = [];
        foreach ($reports as $studentId => $report) {
            $averages[$studentId] = (float) $report->average_weighted_score;
        }

        arsort($averages);

        $rank = 0;
        $lastAvg = null;
        $skipCount = 0;
        $totalStudents = count($averages);

        foreach ($averages as $studentId => $avg) {
            $rank++;
            if ($lastAvg !== null && $avg < $lastAvg) {
                $rank += $skipCount;
                $skipCount = 0;
            } elseif ($lastAvg !== null && $avg === $lastAvg) {
                $rank--;
                $skipCount++;
            }
            $reports[$studentId]->update([
                'position' => $rank,
                'out_of' => $totalStudents,
            ]);
            $lastAvg = $avg;
        }

        // Update per-subject positions and class averages in session snapshots
        $this->updateSessionSubjectPositionsAndAverages($reports, $schoolId);

        return count($reports);
    }

    /**
     * Compute session score from term scores based on the configured method.
     */
    private function computeSessionScore(array $scores, string $method, float $midtermWeight = 0, float $fulltermWeight = 0): float
    {
        if (empty($scores)) {
            return 0.0;
        }

        return match ($method) {
            'weighted_average' => $this->computeWeightedSessionScore($scores, $midtermWeight, $fulltermWeight),
            'best_two_of_three' => $this->computeBestTwoOfThree($scores),
            default => round(array_sum($scores) / count($scores), 2), // average_of_terms
        };
    }

    /**
     * Weighted average: term weights applied in order (midterm_weight for first, fullterm_weight for last, etc.)
     * For a 3-term system, the weights from config apply as: T1 weight, T2 as equal share of remainder, T3 weight.
     * Simplified: we use equal distribution of the total weight across available terms.
     * With 3 terms and weights configured, T1 gets midterm_weight, T3 gets fullterm_weight, T2 gets (100 - both).
     * With fewer terms, only the terms that have data contribute.
     */
    private function computeWeightedSessionScore(array $scores, float $midtermWeight, float $fulltermWeight): float
    {
        $count = count($scores);
        if ($count === 0) {
            return 0.0;
        }

        // For weighted, distribute weights across available terms
        // midterm_weight applies proportionally and fullterm_weight applies proportionally
        // Simple approach: equal weight per term if only 1-2 terms
        if ($count === 1) {
            return round($scores[0], 2);
        }

        if ($count === 2) {
            // Two terms: use midterm_weight for first, fullterm_weight for second
            $w1 = $midtermWeight > 0 ? $midtermWeight : 50;
            $w2 = $fulltermWeight > 0 ? $fulltermWeight : 50;
            $totalW = $w1 + $w2;

            return round(($scores[0] * $w1 + $scores[1] * $w2) / $totalW, 2);
        }

        // 3 terms: T1 = midterm_weight, T3 = fullterm_weight, T2 = 100 - both
        $w1 = $midtermWeight;
        $w3 = $fulltermWeight;
        $w2 = 100 - $w1 - $w3;
        if ($w2 < 0) {
            $w2 = 0;
        }

        $totalW = $w1 + $w2 + $w3;
        if ($totalW <= 0) {
            return round(array_sum($scores) / $count, 2);
        }

        return round(($scores[0] * $w1 + $scores[1] * $w2 + $scores[2] * $w3) / $totalW, 2);
    }

    /**
     * Best two of three: average of the 2 highest scores.
     * If fewer than 3, average all available.
     */
    private function computeBestTwoOfThree(array $scores): float
    {
        if (count($scores) <= 2) {
            return round(array_sum($scores) / count($scores), 2);
        }

        rsort($scores);

        return round(($scores[0] + $scores[1]) / 2, 2);
    }

    /**
     * After generating all session reports for a class, update per-subject positions and class averages
     * inside each student's session snapshot.
     */
    private function updateSessionSubjectPositionsAndAverages(array $reports, int $schoolId): void
    {
        // Collect per-subject scores across all students
        $subjectStudentScores = []; // [subject_id => [student_id => session_total]]

        foreach ($reports as $studentId => $report) {
            $snapshot = $report->subject_scores_snapshot ?? [];
            foreach ($snapshot as $entry) {
                $subjectStudentScores[$entry['subject_id']][$studentId] = $entry['session_total'];
            }
        }

        // Compute positions and class averages per subject
        $subjectPositions = []; // [subject_id => [student_id => position]]
        $subjectAverages = []; // [subject_id => float]

        foreach ($subjectStudentScores as $subjectId => $studentScores) {
            arsort($studentScores);

            $rank = 0;
            $lastScore = null;
            $skipCount = 0;
            $positions = [];

            foreach ($studentScores as $sId => $score) {
                $rank++;
                if ($lastScore !== null && $score < $lastScore) {
                    $rank += $skipCount;
                    $skipCount = 0;
                } elseif ($lastScore !== null && $score === $lastScore) {
                    $rank--;
                    $skipCount++;
                }
                $positions[$sId] = $rank;
                $lastScore = $score;
            }

            $subjectPositions[$subjectId] = $positions;
            $subjectAverages[$subjectId] = count($studentScores) > 0
                ? round(array_sum($studentScores) / count($studentScores), 2)
                : 0.0;
        }

        // Update each report's snapshot with positions and averages
        foreach ($reports as $studentId => $report) {
            $snapshot = $report->subject_scores_snapshot ?? [];
            $updated = false;

            foreach ($snapshot as &$entry) {
                $subjectId = $entry['subject_id'];
                $entry['position'] = $subjectPositions[$subjectId][$studentId] ?? null;
                $entry['class_average'] = $subjectAverages[$subjectId] ?? null;
                $updated = true;
            }
            unset($entry);

            if ($updated) {
                $report->update(['subject_scores_snapshot' => $snapshot]);
            }
        }
    }

    public function finalizeReportGradeSnapshot(StudentTermReport $report): void
    {
        if ($report->isFinalized()) {
            return;
        }

        $report->loadMissing(['class' => fn ($query) => $query->withoutGlobalScopes()]);

        $gradeSnapshot = $this->buildReportGradeSnapshot(
            (int) $report->school_id,
            $report->class?->level_id ? (int) $report->class->level_id : null,
            (float) ($report->average_weighted_score ?? 0)
        );

        $report->forceFill(array_merge($gradeSnapshot, [
            'finalized_at' => now(),
        ]))->save();
    }

    /**
     * @return array{grading_scale_id: int|null, grading_scale_snapshot: array<string, mixed>|null, overall_grade: string|null, overall_grade_label: string|null}
     */
    private function buildReportGradeSnapshot(int $schoolId, ?int $levelId, float $average): array
    {
        $scale = $this->gradingScaleResolver->resolveForLevel($levelId, $schoolId);
        $scale?->loadMissing('items');

        $grade = $this->getGrade($schoolId, $average, $levelId);

        return [
            'grading_scale_id' => $scale?->id,
            'grading_scale_snapshot' => $scale ? $this->snapshotGradingScale($scale) : null,
            'overall_grade' => $grade['grade'] ?? null,
            'overall_grade_label' => $grade['label'] ?? null,
        ];
    }

    /**
     * @return array{id: int, name: string, is_default: bool, is_active: bool, items: array<int, array{grade: string, label: string, min_score: int, max_score: int, sort_order: int}>}
     */
    private function snapshotGradingScale(GradingScale $scale): array
    {
        return [
            'id' => (int) $scale->id,
            'name' => (string) $scale->name,
            'is_default' => (bool) $scale->is_default,
            'is_active' => (bool) $scale->is_active,
            'items' => $scale->items
                ->sortByDesc('min_score')
                ->map(fn ($item): array => [
                    'grade' => (string) $item->grade,
                    'label' => (string) $item->label,
                    'min_score' => (int) $item->min_score,
                    'max_score' => (int) $item->max_score,
                    'sort_order' => (int) $item->sort_order,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Get the score grid for a class: all students × all subjects × all components.
     */
    public function getClassScoreGrid(int $classId, int $termId, int $schoolId): array
    {
        $class = SchoolClass::withoutGlobalScopes()->with(['subjects' => fn ($q) => $q->withoutGlobalScopes()])->findOrFail($classId);
        $levelId = $class->level_id;
        $term = Term::withoutGlobalScopes()
            ->with(['session' => fn ($query) => $query->withoutGlobalScopes()])
            ->findOrFail($termId);
        $subjects = $class->subjects()->withoutGlobalScopes()->orderBy('name')->get();
        $components = ScoreComponent::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $students = $class->students()
            ->enrolledByTerm($term)
            ->with('user')
            ->get()
            ->sortBy(fn ($sp) => $sp->user->name ?? '');

        $scores = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->get()
            ->groupBy(fn ($s) => $s->student_id.'-'.$s->subject_id.'-'.$s->score_component_id);

        // Correction history per score, shown on locked cells
        $amendments = ScoreAmendment::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->whereIn('status', [ScoreAmendment::STATUS_APPLIED, ScoreAmendment::STATUS_PENDING])
            ->with(['reviewedByUser:id,name'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_subject_score_id');

        $grid = [];
        foreach ($students as $profile) {
            $studentRow = [
                'student_id' => $profile->user_id,
                'student_name' => $profile->user->name ?? 'Unknown',
                'admission_number' => $profile->admission_number,
                'subjects' => [],
            ];

            foreach ($subjects as $subject) {
                $subjectData = [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'components' => [],
                    'weighted_total' => 0.0,
                ];

                foreach ($components as $comp) {
                    $key = $profile->user_id.'-'.$subject->id.'-'.$comp->id;
                    $score = $scores->get($key)?->first();
                    $history = $score ? $amendments->get($score->id, collect()) : collect();
                    $lastApplied = $history->firstWhere('status', ScoreAmendment::STATUS_APPLIED);

                    $subjectData['components'][$comp->id] = [
                        'component_id' => $comp->id,
                        'component_name' => $comp->short_name,
                        'score_id' => $score?->id,
                        'score' => $score?->score,
                        'max_score' => $comp->max_score,
                        'is_locked' => $score?->is_locked ?? false,
                        'source_type' => $score?->source_type,
                        'amended_count' => $history->where('status', ScoreAmendment::STATUS_APPLIED)->count(),
                        'last_amendment' => $lastApplied ? __('Corrected from :old to :new by :name on :date — :reason', [
                            'old' => $lastApplied->old_score + 0,
                            'new' => $lastApplied->new_score + 0,
                            'name' => $lastApplied->reviewedByUser?->name ?? __('admin'),
                            'date' => $lastApplied->reviewed_at?->format('j M Y'),
                            'reason' => $lastApplied->reason,
                        ]) : null,
                        'has_pending' => $history->contains('status', ScoreAmendment::STATUS_PENDING),
                    ];

                    if ($score && $score->score !== null && $comp->max_score > 0) {
                        $subjectData['weighted_total'] += round(($score->score / $comp->max_score) * $comp->weight, 2);
                    }
                }

                $subjectData['weighted_total'] = round($subjectData['weighted_total'], 2);
                $grade = $this->getGrade($schoolId, $subjectData['weighted_total'], $levelId);
                $subjectData['grade'] = $grade['grade'] ?? null;
                $subjectData['grade_label'] = $grade['label'] ?? null;

                $studentRow['subjects'][$subject->id] = $subjectData;
            }

            $grid[] = $studentRow;
        }

        return [
            'students' => $grid,
            'subjects' => $subjects,
            'components' => $components,
        ];
    }

    private function ensureStudentEnrolledByTerm(int $studentId, int $classId, Term $term): void
    {
        $eligible = StudentProfile::withoutGlobalScopes()
            ->where('user_id', $studentId)
            ->where('class_id', $classId)
            ->enrolledByTerm($term)
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages([
                'student_id' => __('This student was enrolled after the selected term.'),
            ]);
        }
    }

    private function levelIdForClass(int $classId): ?int
    {
        $levelId = SchoolClass::withoutGlobalScopes()
            ->whereKey($classId)
            ->value('level_id');

        return $levelId !== null ? (int) $levelId : null;
    }

    private function ensureStudentEnrolledBySession(int $studentId, int $classId, AcademicSession $session): void
    {
        $eligible = StudentProfile::withoutGlobalScopes()
            ->where('user_id', $studentId)
            ->where('class_id', $classId)
            ->enrolledBySession($session)
            ->exists();

        if (! $eligible) {
            throw ValidationException::withMessages([
                'student_id' => __('This student was enrolled after the selected session.'),
            ]);
        }
    }
}
