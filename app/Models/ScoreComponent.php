<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScoreComponent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'name',
        'short_name',
        'max_score',
        'weight',
        'sort_order',
        'is_active',
        'include_in_midterm',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'max_score' => 'integer',
            'weight' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'include_in_midterm' => 'boolean',
        ];
    }
}
