<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'grading_scale_id',
        'school_id',
        'grade',
        'label',
        'min_score',
        'max_score',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grading_scale_id' => 'integer',
            'min_score' => 'integer',
            'max_score' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
