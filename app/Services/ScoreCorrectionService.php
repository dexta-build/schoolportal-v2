<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExamAttempt;
use App\Models\ScoreAmendment;
use App\Models\StudentSubjectScore;
use App\Models\StudentTermReport;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Models\User;
use App\Traits\NotifiesAdminsOnSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Locking and controlled correction of class scores.
 *
 * Locking marks a class's scores as final. After that a score only changes through an
 * amendment, which always records the old value, the new value, a reason, and who did it:
 *  - a school admin corrects it directly (applied immediately)
 *  - a teacher requests a correction (applied when an admin approves it)
 *  - a CBT is re-graded (held as pending until an admin approves it)
 *
 * Any score change also marks the class's report cards as out of date, so they are
 * regenerated before the new numbers reach students and parents.
 */
class ScoreCorrectionService
{
    use NotifiesAdminsOnSubmission;

    // ── Locking ──────────────────────────────────────────────────────────────

    public function lockClass(int $classId, int $termId, User $by): int
    {
        $count = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('is_locked', false)
            ->update(['is_locked' => true, 'locked_by' => $by->id, 'locked_at' => now()]);

        $this->audit($by, 'scores.locked', $classId, ['term_id' => $termId, 'scores' => $count]);

        return $count;
    }

    public function unlockClass(int $classId, int $termId, User $by, string $reason): int
    {
        $count = StudentSubjectScore::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where('term_id', $termId)
            ->where('is_locked', true)
            ->update(['is_locked' => false, 'locked_by' => null, 'locked_at' => null]);

        $this->audit($by, 'scores.unlocked', $classId, ['term_id' => $termId, 'scores' => $count, 'reason' => $reason]);

        return $count;
    }

    // ── Amendments ───────────────────────────────────────────────────────────

    /**
     * A school admin corrects a locked score directly. The score stays locked.
     */
    public function amend(StudentSubjectScore $score, float $newScore, string $reason, User $admin): ScoreAmendment
    {
        $this->ensureLocked($score);
        $newScore = $this->validatedScore($score, $newScore);

        $amendment = DB::transaction(function () use ($score, $newScore, $reason, $admin): ScoreAmendment {
            $score = StudentSubjectScore::withoutGlobalScopes()->lockForUpdate()->findOrFail($score->id);

            $amendment = $this->newAmendment($score, $newScore, $reason, ScoreAmendment::SOURCE_ADMIN, $admin);
            $amendment->fill([
                'status' => ScoreAmendment::STATUS_APPLIED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            $this->applyToScore($score, $newScore, $admin);

            // Any outstanding request for this score was overtaken by the admin's own correction
            ScoreAmendment::withoutGlobalScopes()
                ->where('student_subject_score_id', $score->id)
                ->pending()
                ->get()
                ->each(fn (ScoreAmendment $pending) => $this->closeAmendment(
                    $pending, ScoreAmendment::STATUS_REJECTED, $admin, __('Superseded by an admin correction.')
                ));

            return $amendment;
        });

        $this->markReportsStale($score->class_id, $score->term_id);

        return $amendment;
    }

    /**
     * A teacher asks for a locked score to be corrected. Goes to the admin approval queue.
     */
    public function requestCorrection(StudentSubjectScore $score, float $newScore, string $reason, User $teacher): ScoreAmendment
    {
        $this->ensureLocked($score);
        $newScore = $this->validatedScore($score, $newScore);

        // One open request per teacher per score — a second request replaces the first
        $existing = ScoreAmendment::withoutGlobalScopes()
            ->where('student_subject_score_id', $score->id)
            ->where('source', ScoreAmendment::SOURCE_TEACHER_REQUEST)
            ->where('requested_by', $teacher->id)
            ->pending()
            ->first();

        if ($existing) {
            $existing->update(['old_score' => $score->score, 'new_score' => $newScore, 'reason' => $reason]);

            return $existing;
        }

        $amendment = $this->newAmendment($score, $newScore, $reason, ScoreAmendment::SOURCE_TEACHER_REQUEST, $teacher);
        $amendment->save();

        $action = TeacherAction::withoutGlobalScopes()->create([
            'school_id' => $score->school_id,
            'teacher_id' => $teacher->id,
            'action_type' => 'request_score_correction',
            'entity_type' => 'score_amendment',
            'entity_id' => $amendment->id,
            'status' => 'pending',
        ]);

        try {
            $this->notifyAdminsOfPendingSubmission($action, $teacher);
        } catch (Throwable $e) {
            Log::warning('Score correction request notification failed — request was saved', ['amendment_id' => $amendment->id, 'error' => $e->getMessage()]);
        }

        return $amendment;
    }

    /**
     * A CBT was graded or re-graded after its score was locked. Instead of overwriting the
     * locked score, hold the new result as a pending correction for an admin to review.
     */
    public function recordCbtChange(StudentSubjectScore $score, float $newScore, ExamAttempt $attempt): ?ScoreAmendment
    {
        if (round((float) $score->score, 2) === round($newScore, 2)) {
            return null;
        }

        $attempt->loadMissing(['exam' => fn ($q) => $q->withoutGlobalScopes()]);
        $reason = __('":exam" was graded after scores were locked.', ['exam' => $attempt->exam?->title ?? __('CBT')]);

        $existing = ScoreAmendment::withoutGlobalScopes()
            ->where('student_subject_score_id', $score->id)
            ->where('source', ScoreAmendment::SOURCE_CBT)
            ->pending()
            ->first();

        if ($existing) {
            $existing->update([
                'old_score' => $score->score,
                'new_score' => $newScore,
                'reason' => $reason,
                'source_attempt_id' => $attempt->id,
            ]);

            return $existing;
        }

        $amendment = $this->newAmendment($score, $newScore, $reason, ScoreAmendment::SOURCE_CBT, null);
        $amendment->source_attempt_id = $attempt->id;
        $amendment->save();

        return $amendment;
    }

    /**
     * Apply a pending correction. Safe to call from the gradebook or the approvals page.
     */
    public function approve(ScoreAmendment $amendment, User $admin, ?string $note = null): void
    {
        if (! $amendment->isPending()) {
            throw ValidationException::withMessages(['amendment' => __('This correction has already been reviewed.')]);
        }

        $score = DB::transaction(function () use ($amendment, $admin, $note): ?StudentSubjectScore {
            $score = $amendment->student_subject_score_id
                ? StudentSubjectScore::withoutGlobalScopes()->lockForUpdate()->find($amendment->student_subject_score_id)
                : null;

            if (! $score) {
                $this->closeAmendment($amendment, ScoreAmendment::STATUS_REJECTED, $admin, __('The score no longer exists.'));

                return null;
            }

            // Record the value actually being replaced, which may have changed since the request
            $amendment->old_score = $score->score;
            $this->closeAmendment($amendment, ScoreAmendment::STATUS_APPLIED, $admin, $note);
            $this->applyToScore($score, (float) $amendment->new_score, $admin);

            return $score;
        });

        if ($score) {
            $this->markReportsStale($score->class_id, $score->term_id);
        }

        $this->resolveTeacherAction($amendment, 'approved', $admin);
    }

    public function reject(ScoreAmendment $amendment, User $admin, string $reason): void
    {
        if (! $amendment->isPending()) {
            throw ValidationException::withMessages(['amendment' => __('This correction has already been reviewed.')]);
        }

        $this->closeAmendment($amendment, ScoreAmendment::STATUS_REJECTED, $admin, $reason);
        $this->resolveTeacherAction($amendment, 'rejected', $admin, $reason);
    }

    // ── Report cards ─────────────────────────────────────────────────────────

    /**
     * Flag a class's report cards for a term (and that session's session reports) as out of date.
     */
    public function markReportsStale(int $classId, int $termId): int
    {
        $sessionId = Term::withoutGlobalScopes()->whereKey($termId)->value('session_id');

        return StudentTermReport::withoutGlobalScopes()
            ->where('class_id', $classId)
            ->where(fn ($q) => $q->where('term_id', $termId)
                ->orWhere(fn ($session) => $session->where('report_type', 'session')->where('session_id', $sessionId)))
            ->where('needs_regeneration', false)
            ->update(['needs_regeneration' => true]);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function newAmendment(StudentSubjectScore $score, float $newScore, string $reason, string $source, ?User $requestedBy): ScoreAmendment
    {
        return new ScoreAmendment([
            'school_id' => $score->school_id,
            'student_subject_score_id' => $score->id,
            'student_id' => $score->student_id,
            'class_id' => $score->class_id,
            'subject_id' => $score->subject_id,
            'term_id' => $score->term_id,
            'score_component_id' => $score->score_component_id,
            'old_score' => $score->score,
            'new_score' => $newScore,
            'reason' => $reason,
            'source' => $source,
            'status' => ScoreAmendment::STATUS_PENDING,
            'requested_by' => $requestedBy?->id,
        ]);
    }

    private function closeAmendment(ScoreAmendment $amendment, string $status, User $admin, ?string $note): void
    {
        $amendment->fill([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();
    }

    private function applyToScore(StudentSubjectScore $score, float $newScore, User $by): void
    {
        // Saved through the model so the audit log records old → new
        $score->fill([
            'score' => $newScore,
            'source_type' => 'manual',
            'entered_by' => $by->id,
        ])->save();
    }

    /**
     * Keep the approvals queue in step when a teacher's request is decided from the gradebook.
     * When decided from the approvals page the action is already closed, so nothing happens here.
     */
    private function resolveTeacherAction(ScoreAmendment $amendment, string $status, User $admin, ?string $reason = null): void
    {
        $action = TeacherAction::withoutGlobalScopes()
            ->with('teacher')
            ->where('entity_type', 'score_amendment')
            ->where('entity_id', $amendment->id)
            ->where('status', 'pending')
            ->first();

        if (! $action) {
            return;
        }

        $action->update([
            'status' => $status,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if (! $action->teacher) {
            return;
        }

        try {
            $notifications = app(NotificationService::class);
            $title = $notifications->resolveEntityTitle('score_amendment', $amendment->id);

            $status === 'approved'
                ? $notifications->notifySubmissionApproved($action->teacher, 'score_amendment', $title)
                : $notifications->notifySubmissionRejected($action->teacher, 'score_amendment', $title, $reason);
        } catch (Throwable $e) {
            Log::warning('Score correction review notification failed — decision was saved', ['amendment_id' => $amendment->id, 'error' => $e->getMessage()]);
        }
    }

    private function ensureLocked(StudentSubjectScore $score): void
    {
        if (! $score->is_locked) {
            throw ValidationException::withMessages(['score' => __('This score is not locked — edit it directly in the gradebook.')]);
        }
    }

    private function validatedScore(StudentSubjectScore $score, float $newScore): float
    {
        $max = (float) $score->max_score;

        if ($newScore < 0 || $newScore > $max) {
            throw ValidationException::withMessages(['new_score' => __('The score must be between 0 and :max.', ['max' => $max + 0])]);
        }

        if (round((float) $score->score, 2) === round($newScore, 2)) {
            throw ValidationException::withMessages(['new_score' => __('The new score is the same as the current score.')]);
        }

        return round($newScore, 2);
    }

    /**
     * Bulk lock/unlock run as single queries, which bypass model events — log them explicitly.
     *
     * @param  array<string, mixed>  $details
     */
    private function audit(User $by, string $action, int $classId, array $details): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'school_id' => $by->school_id,
            'user_id' => $by->id,
            'action' => $action,
            'entity_type' => 'SchoolClass',
            'entity_id' => $classId,
            'old_values' => null,
            'new_values' => $details,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
