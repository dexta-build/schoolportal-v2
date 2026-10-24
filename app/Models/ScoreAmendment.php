<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditObserver;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change to a locked score: entered directly by an admin, requested by a teacher,
 * or produced by a CBT being re-graded after the scores were locked.
 */
#[ObservedBy(AuditObserver::class)]
class ScoreAmendment extends Model
{
    use BelongsToTenant;

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_TEACHER_REQUEST = 'teacher_request';

    public const SOURCE_CBT = 'cbt';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'school_id',
        'student_subject_score_id',
        'student_id',
        'class_id',
        'subject_id',
        'term_id',
        'score_component_id',
        'old_score',
        'new_score',
        'reason',
        'source',
        'status',
        'source_attempt_id',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'student_subject_score_id' => 'integer',
            'student_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'term_id' => 'integer',
            'score_component_id' => 'integer',
            'source_attempt_id' => 'integer',
            'requested_by' => 'integer',
            'reviewed_by' => 'integer',
            'old_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectScore::class, 'student_subject_score_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scoreComponent(): BelongsTo
    {
        return $this->belongsTo(ScoreComponent::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_TEACHER_REQUEST => __('Teacher request'),
            self::SOURCE_CBT => __('CBT re-grade'),
            default => __('Admin correction'),
        };
    }
}
