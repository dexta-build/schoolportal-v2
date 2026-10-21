<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The content of an approved or published report card as it was before a score correction
 * changed it — kept so the earlier version is never lost.
 */
class StudentTermReportRevision extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'student_term_report_id',
        'revision_number',
        'status_at_revision',
        'subject_scores_snapshot',
        'total_weighted_score',
        'average_weighted_score',
        'overall_grade',
        'position',
        'out_of',
        'revised_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'student_term_report_id' => 'integer',
            'revised_by' => 'integer',
            'subject_scores_snapshot' => 'array',
            'total_weighted_score' => 'decimal:2',
            'average_weighted_score' => 'decimal:2',
            'revision_number' => 'integer',
            'position' => 'integer',
            'out_of' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(StudentTermReport::class, 'student_term_report_id');
    }

    public function revisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
