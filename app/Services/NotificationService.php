<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Game;
use App\Models\Notice;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\School;
use App\Models\ScoreAmendment;
use App\Models\StudentTermReport;
use App\Models\User;
use App\Notifications\AcademicPeriodChangedNotification;
use App\Notifications\CreditPurchaseNotification;
use App\Notifications\LowCreditsWarningNotification;
use App\Notifications\NewAssignmentNotification;
use App\Notifications\NewExamNotification;
use App\Notifications\NewGameNotification;
use App\Notifications\NewNoticeNotification;
use App\Notifications\NewQuizNotification;
use App\Notifications\NewResultNotification;
use App\Notifications\NewSchoolNotification;
use App\Notifications\ReportCardPublishedNotification;
use App\Notifications\ReportCardRevisedNotification;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\SubmissionRejectedNotification;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Notify student (and their parents) when a result is uploaded.
     */
    public function notifyResultUploaded(Result $result): void
    {
        $result->loadMissing(['student', 'session:id,name', 'term:id,name']);

        $student = $result->student;
        if (! $student) {
            return;
        }

        // Notify the student (a deactivated student can't log in to see it)
        if ($student->is_active) {
            $student->notify(new NewResultNotification(
                studentName: $student->name,
                sessionName: $result->session?->name ?? '',
                termName: $result->term?->name ?? '',
                resultId: $result->id,
                recipientRole: 'student',
            ));
        }

        // Notify the student's parents (parentUsers — parents() is the link table, not users)
        $parents = $student->parentUsers()->where('users.is_active', true)->get();
        foreach ($parents as $parent) {
            $parent->notify(new NewResultNotification(
                studentName: $student->name,
                sessionName: $result->session?->name ?? '',
                termName: $result->term?->name ?? '',
                resultId: $result->id,
                recipientRole: 'parent',
            ));
        }
    }

    /**
     * Notify students in the class (and their parents) when an assignment is uploaded.
     */
    public function notifyAssignmentUploaded(Assignment $assignment): void
    {
        $assignment->loadMissing(['class:id,name']);
        $className = $assignment->class?->name ?? '';
        $title = $assignment->title ?? '';
        $weekNumber = $assignment->week_number;

        // Get students in this class
        $students = User::where('role', 'student')->where('is_active', true)
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $assignment->class_id))
            ->get();

        foreach ($students as $student) {
            $student->notify(new NewAssignmentNotification($className, $title, $weekNumber, 'student'));
        }

        // Notify parents of students in this class
        $studentIds = $students->pluck('id');
        $parents = User::where('role', 'parent')->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new NewAssignmentNotification($className, $title, $weekNumber, 'parent'));
        }
    }

    /**
     * Notify targeted users when a notice is published.
     */
    public function notifyNoticePublished(Notice $notice): void
    {
        if (! $notice->is_published) {
            return;
        }

        $noticeTitle = $notice->title;
        $noticeId = $notice->id;

        // Build the query for targeted users
        $targetRoles = $notice->target_roles;
        $targetLevels = $notice->target_levels;
        $targetClasses = $notice->target_classes;

        // If no targeting at all, notify all students, parents, and teachers
        $roles = $targetRoles ?: ['student', 'parent', 'teacher'];

        $query = User::where('is_active', true)->whereIn('role', $roles);

        // Filter by level if specified
        if (! empty($targetLevels)) {
            $query->where(function ($q) use ($targetLevels, $targetClasses) {
                // Students: filter by class level or specific classes
                $q->where(function ($sq) use ($targetLevels, $targetClasses) {
                    $sq->where('role', 'student');
                    if (! empty($targetClasses)) {
                        $sq->whereHas('studentProfile', fn ($p) => $p->whereIn('class_id', $targetClasses));
                    } else {
                        $sq->where('level_id', '!=', null)->whereIn('level_id', $targetLevels);
                    }
                });
                // Teachers: filter by level
                $q->orWhere(function ($sq) use ($targetLevels) {
                    $sq->where('role', 'teacher')->whereIn('level_id', $targetLevels);
                });
                // Parents: include if their children are in targeted levels/classes
                $q->orWhere(function ($sq) use ($targetLevels, $targetClasses) {
                    $sq->where('role', 'parent')
                        ->whereHas('children', function ($cq) use ($targetLevels, $targetClasses) {
                            if (! empty($targetClasses)) {
                                $cq->whereHas('studentProfile', fn ($p) => $p->whereIn('class_id', $targetClasses));
                            } else {
                                $cq->whereIn('level_id', $targetLevels);
                            }
                        });
                });
            });
        }

        $users = $query->get();

        Notification::send($users, new NewNoticeNotification($noticeTitle, $noticeId));
    }

    /**
     * Notify students in the class (and their parents) when a quiz is published.
     */
    public function notifyQuizPublished(Quiz $quiz): void
    {
        $quiz->loadMissing(['class:id,name']);
        $className = $quiz->class?->name ?? '';

        // Notify students in the class
        $students = User::where('role', 'student')->where('is_active', true)
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $quiz->class_id))
            ->get();

        foreach ($students as $student) {
            $student->notify(new NewQuizNotification($quiz->title, $className, $quiz->id, 'student'));
        }

        // Notify parents
        $studentIds = $students->pluck('id');
        $parents = User::where('role', 'parent')->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new NewQuizNotification($quiz->title, $className, $quiz->id, 'parent'));
        }
    }

    /**
     * Notify students in the class (and their parents) when a game is published.
     */
    public function notifyGamePublished(Game $game): void
    {
        $game->loadMissing(['class:id,name']);
        $className = $game->class?->name ?? '';

        // Notify students in the class
        $students = User::where('role', 'student')->where('is_active', true)
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $game->class_id))
            ->get();

        foreach ($students as $student) {
            $student->notify(new NewGameNotification($game->title, $className, $game->game_type, $game->id, 'student'));
        }

        // Notify parents
        $studentIds = $students->pluck('id');
        $parents = User::where('role', 'parent')->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new NewGameNotification($game->title, $className, $game->game_type, $game->id, 'parent'));
        }
    }

    /**
     * Notify students in the class (and their parents) when an exam/assessment/assignment is published.
     */
    public function notifyExamPublished(Exam $exam): void
    {
        if (! $exam->is_published) {
            return;
        }

        $exam->loadMissing(['class:id,name']);
        $className = $exam->class?->name ?? '';

        // Notify students in the class
        $students = User::where('role', 'student')->where('is_active', true)
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $exam->class_id))
            ->get();

        foreach ($students as $student) {
            $student->notify(new NewExamNotification($exam->title, $className, $exam->category, $exam->id, 'student'));
        }

        // Notify parents — only when the school shows parents CBT results
        if (! School::withoutGlobalScopes()->find($exam->school_id)?->featureEnabled('enable_cbt_results_for_parents')) {
            return;
        }

        $studentIds = $students->pluck('id');
        $parents = User::where('role', 'parent')->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->whereIn('student_id', $studentIds))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new NewExamNotification($exam->title, $className, $exam->category, $exam->id, 'parent'));
        }
    }

    /**
     * Notify student and parents that a report card has been published.
     */
    public function notifyReportCardPublished(StudentTermReport $report): void
    {
        $report->loadMissing(['student', 'session', 'term']);

        $student = $report->student;
        if (! $student) {
            return;
        }

        $sessionName = $report->session?->name ?? '';
        $termName = $report->term?->name ?? '';

        // Notify the student (a deactivated student can't log in to see it)
        if ($student->is_active) {
            $student->notify(new ReportCardPublishedNotification(
                studentName: $student->name,
                sessionName: $sessionName,
                termName: $termName,
                reportId: $report->id,
                recipientRole: 'student',
            ));
        }

        // Notify parents
        $parents = User::where('role', 'parent')
            ->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->where('student_id', $student->id))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new ReportCardPublishedNotification(
                studentName: $student->name,
                sessionName: $sessionName,
                termName: $termName,
                reportId: $report->id,
                recipientRole: 'parent',
            ));
        }
    }

    /**
     * Notify a student and their parents that a published report card was corrected.
     */
    public function notifyReportCardRevised(StudentTermReport $report): void
    {
        $report->loadMissing([
            'student' => fn ($q) => $q->withoutGlobalScopes(),
            'session' => fn ($q) => $q->withoutGlobalScopes(),
            'term' => fn ($q) => $q->withoutGlobalScopes(),
        ]);

        $student = $report->student;
        if (! $student) {
            return;
        }

        $details = [
            'studentName' => $student->name,
            'sessionName' => $report->session?->name ?? '',
            'termName' => $report->term?->name ?? __('Session'),
            'reportId' => $report->id,
        ];

        if ($student->is_active) {
            $student->notify(new ReportCardRevisedNotification(...$details, recipientRole: 'student'));
        }

        $parents = User::withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->where('role', 'parent')
            ->where('is_active', true)
            ->whereHas('children', fn ($q) => $q->where('student_id', $student->id))
            ->get();

        foreach ($parents as $parent) {
            $parent->notify(new ReportCardRevisedNotification(...$details, recipientRole: 'parent'));
        }
    }

    /**
     * Notify teacher that their submission was approved.
     */
    public function notifySubmissionApproved(User $teacher, string $entityType, string $entityTitle): void
    {
        $teacher->notify(new SubmissionApprovedNotification($entityType, $entityTitle));
    }

    /**
     * Notify teacher that their submission was rejected.
     */
    public function notifySubmissionRejected(User $teacher, string $entityType, string $entityTitle, ?string $reason = null): void
    {
        $teacher->notify(new SubmissionRejectedNotification($entityType, $entityTitle, $reason));
    }

    /**
     * Notify all super admins that a new school was created.
     */
    public function notifySchoolCreated(School $school): void
    {
        $superAdmins = User::withoutGlobalScopes()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->get();

        Notification::send($superAdmins, new NewSchoolNotification($school->name, $school->id));
    }

    /**
     * Notify all active school users (students, teachers, parents, admins) when a term or session changes.
     */
    public function notifyAcademicPeriodChanged(int $schoolId, string $periodType, string $name): void
    {
        $users = User::where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereIn('role', ['student', 'teacher', 'parent', 'school_admin'])
            ->get();

        Notification::send($users, new AcademicPeriodChangedNotification($periodType, $name));
    }

    /**
     * Notify school admins when AI credits fall at or below the warning threshold.
     */
    public function notifyLowCredits(int $schoolId, int $remainingCredits): void
    {
        $admins = User::where('school_id', $schoolId)
            ->where('role', 'school_admin')
            ->where('is_active', true)
            ->get();

        Notification::send($admins, new LowCreditsWarningNotification($remainingCredits));
    }

    /**
     * Notify all super admins of a credit purchase.
     */
    public function notifyCreditPurchased(School $school, int $credits, string $amount): void
    {
        $superAdmins = User::withoutGlobalScopes()
            ->where('role', 'super_admin')
            ->where('is_active', true)
            ->get();

        Notification::send($superAdmins, new CreditPurchaseNotification(
            schoolName: $school->name,
            credits: $credits,
            amount: $amount,
            schoolId: $school->id,
        ));
    }

    /**
     * Get the entity title for display in notifications.
     */
    public function resolveEntityTitle(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'result' => Result::find($entityId)?->student?->name ?? __('Result'),
            'assignment' => Assignment::find($entityId)?->title ?? __('Assignment'),
            'notice' => Notice::find($entityId)?->title ?? __('Notice'),
            'quiz' => Quiz::find($entityId)?->title ?? __('Quiz'),
            'game' => Game::find($entityId)?->title ?? __('Game'),
            'exam' => Exam::find($entityId)?->title ?? __('Exam'),
            'report_card' => StudentTermReport::with('student:id,name')->find($entityId)?->student?->name ?? __('Report Card'),
            'score_amendment' => ($amendment = ScoreAmendment::with(['student:id,name', 'subject:id,name'])->find($entityId))
                ? "{$amendment->student?->name} — {$amendment->subject?->name}"
                : __('Score correction'),
            default => ucfirst($entityType),
        };
    }
}
