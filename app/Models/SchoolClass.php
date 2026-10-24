<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\UndoableDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use BelongsToTenant, HasFactory, UndoableDeletes;

    protected $table = 'classes';

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'level_id',
        'name',
        'slug',
        'sort_order',
        'capacity',
        'teacher_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'level_id' => 'integer',
            'teacher_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'level_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'class_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject', 'class_id', 'subject_id')
            ->withPivot('teacher_id');
    }

    public function undoableTitle(): string
    {
        return $this->name;
    }

    protected function undoableFileColumns(): array
    {
        return [];
    }
}
