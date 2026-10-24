<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ExamQuestion extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'exam_id',
        'school_id',
        'type',
        'question_text',
        'question_image_url',
        'question_image_public_id',
        'options',
        'correct_answer',
        'marking_guide',
        'sample_answer',
        'min_words',
        'max_words',
        'explanation',
        'points',
        'sort_order',
        'section_label',
    ];

    protected function casts(): array
    {
        return [
            'exam_id' => 'integer',
            'school_id' => 'integer',
            'options' => 'array',
            'points' => 'integer',
            'sort_order' => 'integer',
            'min_words' => 'integer',
            'max_words' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    // ── Helpers ──

    public function isObjective(): bool
    {
        return in_array($this->type, ['multiple_choice', 'true_false', 'fill_blank', 'matching']);
    }

    public function isTheory(): bool
    {
        return in_array($this->type, ['short_answer', 'theory']);
    }

    public function isAutoGradable(): bool
    {
        return in_array($this->type, ['multiple_choice', 'true_false', 'fill_blank', 'matching']);
    }

    protected static function booted(): void
    {
        static::saved(function (ExamQuestion $question) {
            Cache::forget("exam_{$question->exam_id}_question_types");
        });

        static::deleted(function (ExamQuestion $question) {
            Cache::forget("exam_{$question->exam_id}_question_types");
        });
    }
}
