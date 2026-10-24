<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class StudentTermReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'session_id',
        'term_id',
        'report_type',
        'subject_scores_snapshot',
        'grading_scale_id',
        'grading_scale_snapshot',
        'total_weighted_score',
        'average_weighted_score',
        'overall_grade',
        'overall_grade_label',
        'subjects_count',
        'position',
        'out_of',
        'psychomotor_ratings',
        'affective_ratings',
        'attendance_present',
        'attendance_absent',
        'attendance_total',
        'teacher_comment',
        'teacher_id',
        'principal_comment',
        'signature_url',
        'signature_public_id',
        'status',
        'needs_regeneration',
        'revision_count',
        'revised_at',
        'approved_by',
        'approved_at',
        'published_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        // IDs cast explicitly: some hosting MySQL drivers return every column as a string
        return [
            'school_id' => 'integer',
            'student_id' => 'integer',
            'class_id' => 'integer',
            'session_id' => 'integer',
            'term_id' => 'integer',
            'teacher_id' => 'integer',
            'approved_by' => 'integer',
            'subject_scores_snapshot' => 'array',
            'grading_scale_snapshot' => 'array',
            'report_type' => 'string',
            'grading_scale_id' => 'integer',
            'total_weighted_score' => 'decimal:2',
            'average_weighted_score' => 'decimal:2',
            'subjects_count' => 'integer',
            'position' => 'integer',
            'out_of' => 'integer',
            'psychomotor_ratings' => 'array',
            'affective_ratings' => 'array',
            'attendance_present' => 'integer',
            'attendance_absent' => 'integer',
            'attendance_total' => 'integer',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'finalized_at' => 'datetime',
            'needs_regeneration' => 'boolean',
            'revision_count' => 'integer',
            'revised_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(StudentTermReportRevision::class)->orderByDesc('revision_number');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
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

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Status checks ──

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function shouldUseStoredGradeSnapshot(): bool
    {
        return $this->isFinalized() || $this->isPublished();
    }

    public function resolvedOverallGradeItem(?GradingScale $currentScale = null): ?object
    {
        if ($this->shouldUseStoredGradeSnapshot() && $this->overall_grade) {
            return (object) [
                'grade' => $this->overall_grade,
                'label' => $this->overall_grade_label,
            ];
        }

        return $currentScale?->items
            ->first(fn ($item) => $item->min_score <= ($this->average_weighted_score ?? 0)
                && $item->max_score >= ($this->average_weighted_score ?? 0));
    }

    public function resolvedGradingItems(?GradingScale $currentScale = null): Collection
    {
        if ($this->shouldUseStoredGradeSnapshot()) {
            $snapshotItems = $this->grading_scale_snapshot['items'] ?? null;

            if (is_array($snapshotItems) && $snapshotItems !== []) {
                return collect($snapshotItems)->map(fn (array $item): object => (object) $item);
            }
        }

        return $currentScale?->items ?? collect();
    }

    // ── Report type checks ──

    public function isMidterm(): bool
    {
        return $this->report_type === 'midterm';
    }

    public function isFullTerm(): bool
    {
        return $this->report_type === 'full_term';
    }

    public function isSession(): bool
    {
        return $this->report_type === 'session';
    }
}
