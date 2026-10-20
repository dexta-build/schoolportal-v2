<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAttempt extends Model
{
    use BelongsToTenant;

    public const LATE_ANSWER_GRACE_SECONDS = 120;

    protected $fillable = [
        'exam_id',
        'student_id',
        'school_id',
        'attempt_number',
        'score',
        'total_points',
        'percentage',
        'passed',
        'started_at',
        'submitted_at',
        'time_spent_seconds',
        'status',
        'completion_reason',
        'tab_switches',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'exam_id' => 'integer',
            'student_id' => 'integer',
            'school_id' => 'integer',
            'attempt_number' => 'integer',
            'score' => 'integer',
            'total_points' => 'integer',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'time_spent_seconds' => 'integer',
            'tab_switches' => 'integer',
        ];
    }

    // ── Relationships ──

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'attempt_id');
    }

    public function accessReset(): HasOne
    {
        return $this->hasOne(ExamAccessReset::class, 'attempt_id');
    }

    // ── Status Checks ──

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'timed_out']);
    }

    public function isGrading(): bool
    {
        return $this->status === 'grading';
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['submitted', 'timed_out', 'grading', 'graded', 'grading_failed']);
    }

    public function isFullyGraded(): bool
    {
        return in_array($this->status, ['submitted', 'timed_out', 'graded']);
    }

    // ── Helpers ──

    public function hasTimedOut(): bool
    {
        if (! $this->exam->time_limit_minutes || ! $this->isInProgress()) {
            return false;
        }

        // Carbon 3 diffs are signed: always diff from the earlier date so elapsed is positive
        return $this->started_at->diffInSeconds(now()) >= ($this->exam->time_limit_minutes * 60);
    }

    /**
     * When the attempt stops accepting work: the earlier of the time limit and the availability deadline.
     */
    public function expiresAt(): ?CarbonInterface
    {
        $times = [];

        if ($this->exam->time_limit_minutes) {
            $times[] = $this->started_at->copy()->addMinutes($this->exam->time_limit_minutes);
        }

        $deadline = $this->accessReset?->available_until ?? $this->exam->available_until;
        if ($deadline) {
            $times[] = $deadline;
        }

        return $times === [] ? null : min($times);
    }

    /**
     * Whether answers sent just after expiry should still be saved. Covers the browser's
     * final auto-submit arriving a few seconds late, or retried saves after a brief outage.
     */
    public function acceptsLateAnswers(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt === null || now()->lte($expiresAt->copy()->addSeconds(self::LATE_ANSWER_GRACE_SECONDS));
    }

    public function hasExpired(): bool
    {
        if (! $this->isInProgress()) {
            return false;
        }

        if ($this->hasTimedOut()) {
            return true;
        }

        $deadline = $this->accessReset?->available_until ?? $this->exam->available_until;

        return $deadline?->isPast() ?? false;
    }

    public function isResumable(): bool
    {
        return $this->isInProgress() && ! $this->hasExpired();
    }

    public function wasTimeElapsed(): bool
    {
        return $this->completion_reason === 'time_elapsed'
            || ($this->completion_reason === null && $this->status === 'timed_out');
    }

    public function remainingSeconds(): ?int
    {
        if (! $this->isInProgress()) {
            return null;
        }

        $remaining = [];

        if ($this->exam->time_limit_minutes) {
            $elapsed = (int) $this->started_at->diffInSeconds(now());
            $remaining[] = ($this->exam->time_limit_minutes * 60) - $elapsed;
        }

        $deadline = $this->accessReset?->available_until ?? $this->exam->available_until;
        if ($deadline) {
            $remaining[] = (int) now()->diffInSeconds($deadline, false);
        }

        return $remaining === [] ? null : max(0, (int) min($remaining));
    }

    public function answeredCount(): int
    {
        return $this->answers()->whereNotNull('selected_answer')->count();
    }
}
