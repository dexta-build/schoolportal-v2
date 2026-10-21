<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AuditObserver;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Every create/update/delete of a score is written to the audit log (old → new, who, when)
#[ObservedBy(AuditObserver::class)]
class StudentSubjectScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'subject_id',
        'session_id',
        'term_id',
        'score_component_id',
        'score',
        'max_score',
        'source_type',
        'source_exam_id',
        'source_attempt_id',
        'is_locked',
        'locked_by',
        'locked_at',
        'entered_by',
    ];

    protected function casts(): array
    {
        // IDs cast explicitly: some hosting MySQL drivers return every column as a string
        return [
            'school_id' => 'integer',
            'student_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'score_component_id' => 'integer',
            'source_exam_id' => 'integer',
            'source_attempt_id' => 'integer',
            'entered_by' => 'integer',
            'locked_by' => 'integer',
            'score' => 'decimal:2',
            'max_score' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ScoreAmendment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function scoreComponent(): BelongsTo
    {
        return $this->belongsTo(ScoreComponent::class);
    }

    public function sourceExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'source_exam_id');
    }

    public function sourceAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'source_attempt_id');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Calculate weighted score for this component: (score / max_score) * weight
     */
    public function weightedScore(): float
    {
        if ($this->score === null || $this->max_score <= 0) {
            return 0.0;
        }

        $weight = $this->scoreComponent?->weight ?? 0;

        return round(($this->score / $this->max_score) * $weight, 2);
    }
}
