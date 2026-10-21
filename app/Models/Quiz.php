<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quiz extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'school_id',
        'class_id',
        'session_id',
        'term_id',
        'title',
        'description',
        'source_type',
        'source_document_url',
        'source_document_public_id',
        'source_prompt',
        'time_limit_minutes',
        'passing_score',
        'max_attempts',
        'shuffle_questions',
        'shuffle_options',
        'show_correct_answers',
        'is_published',
        'published_at',
        'expires_at',
        'created_by',
        'approved_by',
        'approved_at',
        'status',
        'total_questions',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'class_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'time_limit_minutes' => 'integer',
            'passing_score' => 'integer',
            'max_attempts' => 'integer',
            'total_questions' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_correct_answers' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function latestTeacherAction(): HasOne
    {
        return $this->hasOne(TeacherAction::class, 'entity_id')
            ->where('entity_type', 'quiz')
            ->latestOfMany();
    }

    // ── Scopes ──

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    // ── Helpers ──

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAvailableForStudent(): bool
    {
        return $this->is_published && ! $this->isExpired();
    }

    public function attemptsForStudent(int $studentId): int
    {
        return $this->attempts()
            ->where('student_id', $studentId)
            ->whereIn('status', ['submitted', 'timed_out'])
            ->count();
    }

    public function canStudentAttempt(int $studentId): bool
    {
        $maxAttempts = max(1, (int) ($this->max_attempts ?: 1));

        return $this->isAvailableForStudent()
            && $this->attemptsForStudent($studentId) < $maxAttempts;
    }

    public function bestAttemptForStudent(int $studentId): ?QuizAttempt
    {
        return $this->attempts()
            ->where('student_id', $studentId)
            ->whereIn('status', ['submitted', 'timed_out'])
            ->orderByDesc('percentage')
            ->first();
    }
}
