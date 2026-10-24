<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use BelongsToTenant, HasFactory;

    public const LATE_ANSWER_GRACE_SECONDS = 120;

    public $timestamps = false;

    protected $fillable = [
        'quiz_id',
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
    ];

    protected function casts(): array
    {
        return [
            'quiz_id' => 'integer',
            'student_id' => 'integer',
            'school_id' => 'integer',
            'attempt_number' => 'integer',
            'score' => 'integer',
            'total_points' => 'integer',
            'time_spent_seconds' => 'integer',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    public function hasExpired(): bool
    {
        return $this->status === 'in_progress' && $this->expiresAt()?->isPast() === true;
    }

    public function expiresAt(): ?CarbonInterface
    {
        return $this->quiz->time_limit_minutes
            ? $this->started_at->copy()->addMinutes((int) $this->quiz->time_limit_minutes)
            : null;
    }

    /**
     * Whether answers sent just after the time limit should still be saved. Covers the browser's
     * final auto-submit arriving a few seconds late, or retried saves after a brief outage.
     */
    public function acceptsLateAnswers(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt === null || now()->lte($expiresAt->copy()->addSeconds(self::LATE_ANSWER_GRACE_SECONDS));
    }
}
