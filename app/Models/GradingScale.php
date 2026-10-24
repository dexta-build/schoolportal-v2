<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'name',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GradingScaleItem::class)->orderBy('sort_order');
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(SchoolLevel::class, 'grading_scale_level', 'grading_scale_id', 'level_id')
            ->withPivot('school_id')
            ->orderBy('school_levels.sort_order');
    }
}
