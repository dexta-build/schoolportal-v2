<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exam extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'school_id',
        'class_id',
        'subject_id',
        'score_component_id',
        'session_id',
        'term_id',
        'title',
        'description',
        'category',
        'source_type',
        'source_prompt',
        'source_document_url',
        'source_document_public_id',
        'time_limit_minutes',
        'max_score',
        'passing_score',
        'max_attempts',
        'shuffle_questions',
        'shuffle_options',
        'show_correct_answers',
        'difficulty',
        'available_from',
        'available_until',
        'prevent_tab_switch',
        'prevent_copy_paste',
        'randomize_per_student',
        'max_tab_switches',
        'is_published',
        'published_at',
        'created_by',
        'approved_by',
        'approved_at',
        'status',
        'total_questions',
        'total_points',
        'instructions',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'score_component_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'max_score' => 'integer',
            'time_limit_minutes' => 'integer',
            'passing_score' => 'integer',
            'max_attempts' => 'integer',
            'max_tab_switches' => 'integer',
            'total_questions' => 'integer',
            'total_points' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_correct_answers' => 'boolean',
            'prevent_tab_switch' => 'boolean',
            'prevent_copy_paste' => 'boolean',
            'randomize_per_student' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function scoreComponent(): BelongsTo
    {
        return $this->belongsTo(ScoreComponent::class);
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
        return $this->hasMany(ExamQuestion::class)->orderBy('sort_order');
    }

    public function latestTeacherAction(): HasOne
    {
        return $this->hasOne(TeacherAction::class, 'entity_id')
            ->where('entity_type', 'exam')
            ->latestOfMany();
    }

    // ── Scopes ──

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeAvailable($query)
    {
        return $query->published()
            ->where(function ($q) {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', now());
            });
    }

    public function scopeAvailableForStudent($query, int $studentId)
    {
        return $query->published()
            ->where(function ($query) use ($studentId): void {
                $query->where(function ($window): void {
                    $window->where(function ($from): void {
                        $from->whereNull('available_from')
                            ->orWhere('available_from', '<=', now());
                    })->where(function ($until): void {
                        $until->whereNull('available_until')
                            ->orWhere('available_until', '>=', now());
                    });
                })->orWhereHas('accessResets', function ($reset) use ($studentId): void {
                    $reset->where('student_id', $studentId)->active();
                })->orWhereHas('attempts', function ($attempt) use ($studentId): void {
                    $attempt->where('student_id', $studentId)
                        ->where('status', 'in_progress');
                });
            });
    }

    /**
     * Published exams that haven't opened yet (available_from is in the future).
     */
    public function scopeUpcoming($query)
    {
        return $query->published()
            ->whereNotNull('available_from')
            ->where('available_from', '>', now());
    }

    /**
     * Published exams whose deadline has passed (available_until is in the past).
     * Used to show students their results even after the window closes.
     */
    public function scopeClosed($query)
    {
        return $query->published()
            ->whereNotNull('available_until')
            ->where('available_until', '<', now());
    }

    /**
     * Published exams visible to students: available now OR upcoming.
     * Excludes expired exams (available_until in the past).
     */
    public function scopeVisibleToStudents($query)
    {
        return $query->published()
            ->where(function ($q) {
                $q->whereNull('available_until')
                    ->orWhere('available_until', '>=', now());
            });
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForSubject($query, int $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeAssessments($query)
    {
        return $query->where('category', 'assessment');
    }

    public function scopeAssignments($query)
    {
        return $query->where('category', 'assignment');
    }

    public function scopeExams($query)
    {
        return $query->where('category', 'exam');
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ── Helpers ──

    public function isAvailable(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        if ($this->available_from && $this->available_from->isFuture()) {
            return false;
        }

        if ($this->available_until && $this->available_until->isPast()) {
            return false;
        }

        return true;
    }

    public function isAssessment(): bool
    {
        return $this->category === 'assessment';
    }

    public function isAssignment(): bool
    {
        return $this->category === 'assignment';
    }

    public function isExam(): bool
    {
        return $this->category === 'exam';
    }

    public function questionsBySection(): Collection
    {
        return $this->questions()->get()->groupBy(fn ($q) => $q->section_label ?? 'General');
    }

    public function objectiveQuestions(): HasMany
    {
        return $this->questions()->whereIn('type', ['multiple_choice', 'true_false', 'fill_blank', 'matching']);
    }

    public function theoryQuestions(): HasMany
    {
        return $this->questions()->whereIn('type', ['short_answer', 'theory']);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function accessResets(): HasMany
    {
        return $this->hasMany(ExamAccessReset::class);
    }

    public function attemptsFor(int $studentId): HasMany
    {
        return $this->attempts()->where('student_id', $studentId);
    }

    public function latestAttemptFor(int $studentId): ?ExamAttempt
    {
        return $this->attemptsFor($studentId)->latest('attempt_number')->first();
    }

    public function bestAttemptForStudent(int $studentId): ?ExamAttempt
    {
        return $this->attemptsFor($studentId)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded', 'grading_failed'])
            ->orderByDesc('percentage')
            ->first();
    }

    public function attemptsCountForStudent(int $studentId): int
    {
        return $this->completedAttemptsFor($studentId);
    }

    public function completedAttemptsFor(int $studentId): int
    {
        return $this->attemptsFor($studentId)
            ->whereIn('status', ['submitted', 'timed_out', 'grading', 'graded', 'grading_failed'])
            ->count();
    }

    public function activeResetForStudent(int $studentId): ?ExamAccessReset
    {
        return $this->accessResets()
            ->where('student_id', $studentId)
            ->active()
            ->oldest('available_until')
            ->first();
    }

    public function hasActiveResetForStudent(int $studentId): bool
    {
        return $this->accessResets()
            ->where('student_id', $studentId)
            ->active()
            ->exists();
    }

    public function resumableAttemptForStudent(int $studentId): ?ExamAttempt
    {
        return $this->attemptsFor($studentId)
            ->where('status', 'in_progress')
            ->oldest('attempt_number')
            ->get()
            ->first(fn (ExamAttempt $attempt): bool => $attempt->isResumable());
    }

    public function isAvailableForStudent(int $studentId): bool
    {
        if (! $this->is_published) {
            return false;
        }

        return $this->isAvailable()
            || $this->hasActiveResetForStudent($studentId)
            || $this->resumableAttemptForStudent($studentId) !== null;
    }

    public function canStudentAttempt(int $studentId): bool
    {
        if (! $this->isAvailableForStudent($studentId)) {
            return false;
        }

        if ($this->resumableAttemptForStudent($studentId)) {
            return true;
        }

        if (! $this->isAvailable()) {
            return $this->hasActiveResetForStudent($studentId);
        }

        $latestAttempt = $this->latestAttemptFor($studentId);
        if ($latestAttempt?->wasTimeElapsed() && ! $this->hasActiveResetForStudent($studentId)) {
            return false;
        }

        return $this->completedAttemptsFor($studentId) < $this->allowedAttemptsForStudent($studentId);
    }

    public function allowedAttemptsForStudent(int $studentId): int
    {
        $baseAttempts = max(1, (int) ($this->max_attempts ?: 1));
        $usedResetAttempts = $this->accessResets()
            ->where('student_id', $studentId)
            ->whereNotNull('used_at')
            ->count();
        $activeResetAttempts = $this->accessResets()
            ->where('student_id', $studentId)
            ->active()
            ->count();

        return $baseAttempts + $usedResetAttempts + $activeResetAttempts;
    }
}
