<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'school_id',
        'selected_answer',
        'theory_answer',
        'is_correct',
        'points_earned',
        'answered_at',
        'teacher_comment',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_id' => 'integer',
            'question_id' => 'integer',
            'school_id' => 'integer',
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
            'answered_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // ── Helpers ──

    public function isAnswered(): bool
    {
        return ($this->selected_answer !== null && $this->selected_answer !== '')
            || ($this->theory_answer !== null && $this->theory_answer !== '');
    }

    public function isGraded(): bool
    {
        return $this->is_correct !== null;
    }

    public function needsManualGrading(): bool
    {
        return $this->isAnswered() && ! $this->isGraded() && $this->question->isTheory();
    }
}
