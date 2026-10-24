<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBank extends Model
{
    use BelongsToTenant;

    protected $table = 'question_bank';

    /** @var list<string> */
    protected $fillable = [
        'school_id',
        'subject_id',
        'class_id',
        'created_by',
        'type',
        'question_text',
        'question_image_url',
        'options',
        'correct_answer',
        'explanation',
        'marking_guide',
        'sample_answer',
        'points',
        'min_words',
        'max_words',
        'difficulty',
        'tags',
        'times_used',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'subject_id' => 'integer',
            'class_id' => 'integer',
            'created_by' => 'integer',
            'options' => 'array',
            'tags' => 'array',
            'points' => 'integer',
            'min_words' => 'integer',
            'max_words' => 'integer',
            'times_used' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function isObjective(): bool
    {
        return in_array($this->type, ['multiple_choice', 'true_false', 'fill_blank', 'matching'], true);
    }

    public function isTheory(): bool
    {
        return in_array($this->type, ['short_answer', 'theory'], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'multiple_choice' => 'MCQ',
            'true_false' => 'True/False',
            'fill_blank' => 'Fill Blank',
            'short_answer' => 'Short Answer',
            'theory' => 'Theory/Essay',
            'matching' => 'Matching',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    public function difficultyColor(): string
    {
        return match ($this->difficulty) {
            'easy' => 'green',
            'medium' => 'amber',
            'hard' => 'red',
            default => 'zinc',
        };
    }
}
